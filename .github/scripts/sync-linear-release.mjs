#!/usr/bin/env node

import { readFileSync, appendFileSync } from "node:fs";

const GRAPHQL_ENDPOINT = "https://api.linear.app/graphql";
const RELEASE_PIPELINE_BY_CHANNEL = {
  internal: "OS Prereleases",
  public: "OS Stable Releases",
};
const STABLE_RELEASE_PIPELINE = "OS Stable Releases";
const TARGET_STAGE_BY_CHANNEL = {
  internal: "In Progress",
  public: "Released",
};
const PLANNED_RELEASE_STAGE = "Planned";

const env = process.env;

const linearApiKey = requiredEnv("LINEAR_API_KEY");
const releaseChannel = requiredEnv("RELEASE_CHANNEL");
const releaseName = requiredEnv("RELEASE_NAME");
const tagName = requiredEnv("TAG_NAME");
const tagSha = requiredEnv("TAG_SHA");
const issueIdsPath = requiredEnv("ISSUE_IDS_PATH");
const featureOsUrlsPath = env.FEATUREOS_URLS_PATH;
const githubPrUrlsPath = env.GITHUB_PR_URLS_PATH;
const prSummaryPath = env.PR_SUMMARY_PATH;
const logPath = env.LOG_PATH;

const pipelineName = RELEASE_PIPELINE_BY_CHANNEL[releaseChannel];
if (!pipelineName) {
  throw new Error(`Unsupported release channel: ${releaseChannel}`);
}

const targetStageName = TARGET_STAGE_BY_CHANNEL[releaseChannel];
const issueIdentifiers = readIssueIdentifiers(issueIdsPath);
const featureOsUrls = featureOsUrlsPath ? readLines(featureOsUrlsPath) : [];
const githubPrUrls = githubPrUrlsPath ? readLines(githubPrUrlsPath) : [];

const pipeline = await findReleasePipeline(pipelineName);
const targetStage = findStage(pipeline, targetStageName);
const release = await upsertRelease({ pipeline, targetStage });
await syncWebguiReleaseNotes(pipeline, release);
const relatedReleases = await resolveRelatedReleases(pipeline);
const syncResult = await syncIssuesToRelease(release, relatedReleases, { issueIdentifiers, featureOsUrls, githubPrUrls });

setOutput("release_id", release.id);
setOutput("release_url", release.url || "");
setOutput("release_name", release.name || releaseName);
setOutput("release_version", release.version || tagName);
setOutput("synced_issue_identifiers", syncResult.synced.length > 0 ? syncResult.synced.join(", ") : "none");
setOutput("skipped_issue_identifiers", syncResult.skipped.length > 0 ? syncResult.skipped.join(", ") : "none");

console.log(`Synced Linear release ${release.name} (${release.version || tagName})`);
console.log(`Attached issues: ${syncResult.synced.length > 0 ? syncResult.synced.join(", ") : "none"}`);
console.log(`Skipped issues: ${syncResult.skipped.length > 0 ? syncResult.skipped.join(", ") : "none"}`);

async function resolveRelatedReleases(primaryPipeline) {
  if (releaseChannel !== "internal") {
    return {};
  }

  const stableVersion = stableVersionForPrerelease(tagName);
  const stablePipeline = await findReleasePipeline(STABLE_RELEASE_PIPELINE);
  const stableRelease = await upsertRelease({
    pipeline: stablePipeline,
    targetStage: findStage(stablePipeline, PLANNED_RELEASE_STAGE),
    name: `Unraid OS ${stableVersion} Stable`,
    version: stableVersion,
    description: [
      "Synced from unraid/webgui prerelease tag automation.",
      "",
      `Prerelease tag: ${tagName}`,
      `Prerelease commit: ${tagSha}`,
      "Stable companion release tracks work accumulated through the prerelease series.",
    ].join("\n"),
    commitSha: undefined,
  });

  const nextPrereleaseVersion = nextPrereleaseVersionFor(tagName);
  const nextPrereleaseRelease = nextPrereleaseVersion
    ? await upsertRelease({
      pipeline: primaryPipeline,
      targetStage: findStage(primaryPipeline, PLANNED_RELEASE_STAGE),
      name: nextPrereleaseVersion,
      version: nextPrereleaseVersion,
      description: [
        "Planned next prerelease opened by unraid/webgui tag automation.",
        "",
        `Created from tag: ${tagName}`,
        `Source commit: ${tagSha}`,
      ].join("\n"),
      commitSha: undefined,
    })
    : undefined;

  return { stableRelease, nextPrereleaseRelease };
}

async function upsertRelease(options) {
  const {
    pipeline,
    targetStage,
    name = releaseName,
    version = tagName,
    description,
  } = options;
  const commitSha = Object.prototype.hasOwnProperty.call(options, "commitSha") ? options.commitSha : tagSha;
  const existing = await findRelease(pipeline.id, version, name);
  const releaseDescription = description || [
    "Synced from unraid/webgui tag automation.",
    "",
    `Tag: ${version}`,
    commitSha ? `Commit: ${commitSha}` : undefined,
    env.PREVIOUS_TAG ? `Previous tag: ${env.PREVIOUS_TAG}` : undefined,
    env.RANGE_SPEC ? `Commit range: ${env.RANGE_SPEC}` : undefined,
  ].filter(Boolean).join("\n");

  if (!existing) {
    return createRelease({
      pipelineId: pipeline.id,
      name,
      version,
      description: releaseDescription,
      commitSha,
      stageId: targetStage.id,
    });
  }

  const input = {
    name: existing.name === name ? undefined : name,
    description: releaseDescription,
    commitSha: commitSha && existing.commitSha !== commitSha ? commitSha : undefined,
  };

  if (!isTerminalReleaseStage(existing.stage) && existing.stage?.id !== targetStage.id) {
    input.stageId = targetStage.id;
  }

  if (Object.values(input).some((value) => value !== undefined)) {
    return updateRelease(existing.id, input);
  }

  return existing;
}

async function syncIssuesToRelease(release, relatedReleases, { issueIdentifiers, featureOsUrls, githubPrUrls }) {
  const synced = [];
  const skipped = [];
  const seenIssueIds = new Set();

  for (const identifier of issueIdentifiers) {
    const issue = await findIssue(identifier);
    if (!issue || issue.archivedAt) {
      skipped.push(`${identifier} (not found)`);
      continue;
    }

    await syncIssueToReleases(issue, release, relatedReleases, synced, seenIssueIds);
  }

  for (const url of featureOsUrls) {
    const issues = await findIssuesForAttachmentUrl(url);
    if (issues.length === 0) {
      skipped.push(`${url} (no linked Linear issue)`);
      continue;
    }

    for (const issue of issues) {
      if (issue.archivedAt) {
        skipped.push(`${issue.identifier} (archived)`);
        continue;
      }

      await syncIssueToReleases(issue, release, relatedReleases, synced, seenIssueIds);
    }
  }

  for (const url of githubPrUrls) {
    const issues = await findIssuesForAttachmentUrl(url);
    if (issues.length === 0) {
      continue;
    }

    for (const issue of issues) {
      if (issue.archivedAt) {
        skipped.push(`${issue.identifier} (archived)`);
        continue;
      }

      await syncIssueToReleases(issue, release, relatedReleases, synced, seenIssueIds);
    }
  }

  for (const issue of await findIssuesForRelease(release.id)) {
    if (issue.archivedAt) {
      continue;
    }
    await syncIssueToReleases(issue, release, relatedReleases, synced, seenIssueIds);
  }

  return { synced, skipped };
}

async function syncIssueToReleases(issue, release, relatedReleases, synced, seenIssueIds) {
  if (seenIssueIds.has(issue.id)) {
    return;
  }
  seenIssueIds.add(issue.id);

  const releaseIds = new Set((issue.releases?.nodes || []).map((item) => item.id));
  const addedReleaseIds = [];
  const removedReleaseIds = [];

  for (const targetRelease of [release, relatedReleases.stableRelease]) {
    if (targetRelease && !releaseIds.has(targetRelease.id)) {
      addedReleaseIds.push(targetRelease.id);
    }
  }

  if (relatedReleases.nextPrereleaseRelease) {
    const nextReleaseId = relatedReleases.nextPrereleaseRelease.id;
    if (shouldCarryIssueToNextPrerelease(issue)) {
      if (!releaseIds.has(nextReleaseId)) {
        addedReleaseIds.push(nextReleaseId);
      }
    } else if (releaseIds.has(nextReleaseId)) {
      removedReleaseIds.push(nextReleaseId);
    }
  }

  if (addedReleaseIds.length > 0 || removedReleaseIds.length > 0) {
    await updateIssue(issue.id, dropUndefined({
      addedReleaseIds: addedReleaseIds.length > 0 ? addedReleaseIds : undefined,
      removedReleaseIds: removedReleaseIds.length > 0 ? removedReleaseIds : undefined,
    }));
  }

  synced.push(issue.identifier);
}

async function findReleasePipeline(name) {
  const data = await graphql(`
    query ListReleasePipelines {
      releasePipelines(first: 50, includeArchived: false) {
        nodes {
          id
          name
          slugId
          url
          stages(first: 50, includeArchived: false) {
            nodes {
              id
              name
              type
            }
          }
        }
      }
    }
  `);

  const pipeline = data.releasePipelines.nodes.find((item) => item.name === name || item.slugId === name);
  if (!pipeline) {
    throw new Error(`Linear release pipeline not found: ${name}`);
  }

  return pipeline;
}

function findStage(pipeline, name) {
  const stage = pipeline.stages.nodes.find((item) => item.name === name);
  if (!stage) {
    throw new Error(`Linear release stage not found in ${pipeline.name}: ${name}`);
  }

  return stage;
}

async function findRelease(pipelineId, version, name) {
  const data = await graphql(`
    query FindRelease($pipelineId: ID!, $version: String!, $name: String!) {
      releases(
        first: 20
        includeArchived: false
        filter: {
          and: [
            { pipeline: { id: { eq: $pipelineId } } }
            {
              or: [
                { version: { eq: $version } }
                { name: { eq: $name } }
              ]
            }
          ]
        }
      ) {
        nodes {
          id
          name
          version
          description
          commitSha
          url
          stage {
            id
            name
            type
          }
          releaseNotes {
            id
            title
            documentContent {
              content
            }
          }
        }
      }
    }
  `, { pipelineId, version, name });

  return data.releases.nodes.find((release) => release.version === version)
    || data.releases.nodes.find((release) => release.name === name)
    || null;
}

async function createRelease(input) {
  const data = await graphql(`
    mutation CreateRelease($input: ReleaseCreateInput!) {
      releaseCreate(input: $input) {
        success
        release {
          id
          name
          version
          description
          commitSha
          url
          stage {
            id
            name
            type
          }
          releaseNotes {
            id
            title
            documentContent {
              content
            }
          }
        }
      }
    }
  `, { input });

  if (!data.releaseCreate.success || !data.releaseCreate.release) {
    throw new Error(`Linear release create failed for ${input.name}`);
  }

  return data.releaseCreate.release;
}

async function updateRelease(id, input) {
  const data = await graphql(`
    mutation UpdateRelease($id: String!, $input: ReleaseUpdateInput!) {
      releaseUpdate(id: $id, input: $input) {
        success
        release {
          id
          name
          version
          description
          commitSha
          url
          stage {
            id
            name
            type
          }
          releaseNotes {
            id
            title
            documentContent {
              content
            }
          }
        }
      }
    }
  `, { id, input: dropUndefined(input) });

  if (!data.releaseUpdate.success || !data.releaseUpdate.release) {
    throw new Error(`Linear release update failed for ${id}`);
  }

  return data.releaseUpdate.release;
}

async function syncWebguiReleaseNotes(pipeline, release) {
  const content = buildWebguiReleaseNotes();
  if (!content || !release?.id) {
    return;
  }

  const title = `Version ${tagName}`;
  const existingNote = findReleaseNote(release, title);
  const nextContent = renderManagedSection(
    existingNote?.documentContent?.content || "",
    "notification-worker-webgui-release-notes",
    title,
    content,
  );

  if (existingNote?.id) {
    if (nextContent !== (existingNote.documentContent?.content || "") || existingNote.title !== title) {
      await updateReleaseNote(existingNote.id, {
        releaseId: release.id,
        title,
        content: nextContent,
      });
    }
    return;
  }

  await createReleaseNote({
    pipelineId: pipeline.id,
    releaseId: release.id,
    title,
    content: nextContent,
  });
}

function buildWebguiReleaseNotes() {
  const prSummaries = prSummaryPath ? readOptionalLines(prSummaryPath) : [];
  const commitSubjects = logPath ? readCommitSubjects(logPath) : [];
  const metadata = [
    `Tag: \`${tagName}\``,
    `Commit: \`${tagSha}\``,
    env.PREVIOUS_TAG ? `Previous tag: \`${env.PREVIOUS_TAG}\`` : undefined,
    env.RANGE_SPEC ? `Commit range: \`${env.RANGE_SPEC}\`` : undefined,
  ].filter(Boolean);
  const sections = [["## Release Metadata", ...metadata]];

  if (prSummaries.length > 0) {
    sections.push(["## WebGUI Pull Requests", ...prSummaries]);
  }
  if (issueIdentifiers.length > 0) {
    sections.push(["## Linked Linear Issues", ...issueIdentifiers.map((id) => `- ${id}`)]);
  }
  if (featureOsUrls.length > 0) {
    sections.push(["## Linked FeatureOS Posts", ...featureOsUrls.map((url) => `- ${url}`)]);
  }
  if (prSummaries.length === 0 && commitSubjects.length > 0) {
    sections.push(["## Commit Summary", ...commitSubjects.slice(0, 25).map((subject) => `- ${subject}`)]);
  }

  return sections.map((section) => section.join("\n")).join("\n\n").trim();
}

function findReleaseNote(release, title) {
  const normalizedTitle = title.trim().toLowerCase();
  const marker = managedSectionStartMarker("notification-worker-webgui-release-notes", title);
  return (release.releaseNotes || []).find((note) => (note.title || "").trim().toLowerCase() === normalizedTitle)
    || (release.releaseNotes || []).find((note) => (note.documentContent?.content || "").includes(marker));
}

async function createReleaseNote(input) {
  const data = await graphql(`
    mutation CreateReleaseNote($input: ReleaseNoteCreateInput!) {
      releaseNoteCreate(input: $input) {
        success
        releaseNote {
          id
          title
        }
      }
    }
  `, {
    input: dropUndefined({
      pipelineId: input.pipelineId,
      releaseIds: [input.releaseId],
      title: input.title,
      content: input.content,
    }),
  });

  if (!data.releaseNoteCreate.success) {
    throw new Error(`Linear release note create failed for ${input.releaseId}`);
  }
}

async function updateReleaseNote(id, input) {
  const data = await graphql(`
    mutation UpdateReleaseNote($id: String!, $input: ReleaseNoteUpdateInput!) {
      releaseNoteUpdate(id: $id, input: $input) {
        success
        releaseNote {
          id
          title
        }
      }
    }
  `, {
    id,
    input: dropUndefined({
      releaseIds: [input.releaseId],
      title: input.title,
      content: input.content,
    }),
  });

  if (!data.releaseNoteUpdate.success) {
    throw new Error(`Linear release note update failed for ${id}`);
  }
}

async function findIssue(identifier) {
  const data = await graphql(`
    query FindIssue($id: String!) {
      issue(id: $id) {
        id
        identifier
        archivedAt
        state {
          name
          type
        }
        releases(first: 50) {
          nodes {
            id
          }
        }
      }
    }
  `, { id: identifier });

  return data.issue || null;
}

async function findIssuesForRelease(releaseId) {
  const data = await graphql(`
    query FindIssuesForRelease($id: String!) {
      release(id: $id) {
        issues(first: 100) {
          nodes {
            id
            identifier
            archivedAt
            state {
              name
              type
            }
            releases(first: 50) {
              nodes {
                id
              }
            }
          }
        }
      }
    }
  `, { id: releaseId });

  return data.release?.issues?.nodes || [];
}

async function findIssuesForAttachmentUrl(url) {
  const urls = candidateAttachmentUrls(url);
  const issuesById = new Map();

  for (const candidate of urls) {
    const data = await graphql(`
      query FindAttachmentsForUrl($url: String!) {
        attachmentsForURL(url: $url, first: 20, includeArchived: false) {
          nodes {
            id
            url
            issue {
              id
              identifier
              archivedAt
              state {
                name
                type
              }
              releases(first: 50) {
                nodes {
                  id
                }
              }
            }
          }
        }
      }
    `, { url: candidate });

    for (const attachment of data.attachmentsForURL.nodes || []) {
      if (attachment.issue?.id) {
        issuesById.set(attachment.issue.id, attachment.issue);
      }
    }
  }

  return [...issuesById.values()];
}

async function updateIssue(id, input) {
  const data = await graphql(`
    mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
      issueUpdate(id: $id, input: $input) {
        success
        issue {
          id
          identifier
        }
      }
    }
  `, { id, input });

  if (!data.issueUpdate.success) {
    throw new Error(`Linear issue update failed for ${id}`);
  }
}

async function graphql(query, variables = {}) {
  const response = await fetch(GRAPHQL_ENDPOINT, {
    method: "POST",
    headers: {
      "Authorization": linearApiKey,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ query, variables }),
  });
  const payload = await response.json();

  if (!response.ok || payload.errors) {
    const message = payload.errors?.map((error) => error.message).join("; ") || response.statusText;
    throw new Error(`Linear GraphQL request failed: ${message}`);
  }

  return payload.data;
}

function isTerminalReleaseStage(stage) {
  const type = (stage?.type || "").toLowerCase();
  const name = (stage?.name || "").toLowerCase();
  return type === "completed" || type === "canceled" || name === "released" || name === "canceled";
}

function shouldCarryIssueToNextPrerelease(issue) {
  const stateName = (issue.state?.name || "").trim().toLowerCase();
  if (new Set([
    "internal release",
    "internal validated",
    "public release",
    "released",
    "canceled",
    "cancelled",
    "duplicate",
  ]).has(stateName)) {
    return false;
  }

  const stateType = (issue.state?.type || "").trim().toLowerCase();
  return stateType !== "completed" && stateType !== "canceled";
}

function stableVersionForPrerelease(version) {
  return version.split("-")[0];
}

function nextPrereleaseVersionFor(version) {
  const match = version.match(/^(.+-)(\d+)$/);
  if (!match) {
    return undefined;
  }
  return `${match[1]}${Number(match[2]) + 1}`;
}

function readIssueIdentifiers(path) {
  return readLines(path)
    .filter((value) => /^[A-Z][A-Z0-9]+-[0-9]+$/.test(value));
}

function readLines(path) {
  return readFileSync(path, "utf8")
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .filter((value, index, values) => values.indexOf(value) === index);
}

function readOptionalLines(path) {
  try {
    return readLines(path);
  } catch {
    return [];
  }
}

function readCommitSubjects(path) {
  try {
    return readFileSync(path, "utf8")
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line && !line.startsWith("Merge pull request #"))
      .filter((value, index, values) => values.indexOf(value) === index);
  } catch {
    return [];
  }
}

function renderManagedSection(content, markerPrefix, title, body) {
  const normalizedTitle = title.trim() || "Release Notes";
  const normalizedBody = body.trim();
  if (!normalizedBody) {
    return content;
  }

  const startMarker = managedSectionStartMarker(markerPrefix, normalizedTitle);
  const endMarker = `<!-- ${markerPrefix}:end:${stableMarkerHash(normalizedTitle)} -->`;
  const section = [
    startMarker,
    `# ${normalizedTitle}`,
    "",
    normalizedBody,
    endMarker,
  ].join("\n").trim();
  const existing = content.trim();
  const pattern = new RegExp(`${escapeRegExp(startMarker)}[\\s\\S]*?${escapeRegExp(endMarker)}`, "m");
  if (pattern.test(existing)) {
    return existing.replace(pattern, section).trim();
  }

  return [existing, section].filter(Boolean).join("\n\n").trim();
}

function managedSectionStartMarker(markerPrefix, title) {
  return `<!-- ${markerPrefix}:start:${stableMarkerHash(title.trim() || "Release Notes")} -->`;
}

function stableMarkerHash(value) {
  let hash = 5381;
  for (let index = 0; index < value.length; index += 1) {
    hash = ((hash << 5) + hash) ^ value.charCodeAt(index);
  }
  return (hash >>> 0).toString(16);
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function candidateAttachmentUrls(url) {
  const candidates = new Set([url]);
  try {
    const parsed = new URL(url);
    parsed.search = "";
    parsed.hash = "";
    candidates.add(parsed.toString());
    candidates.add(parsed.toString().replace(/\/$/, ""));
  } catch {
    // Keep the original raw URL when parsing fails.
  }
  return [...candidates].filter(Boolean);
}

function requiredEnv(name) {
  const value = env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

function setOutput(name, value) {
  if (env.GITHUB_OUTPUT) {
    appendFileSync(env.GITHUB_OUTPUT, `${name}=${value}\n`);
  }
}

function dropUndefined(input) {
  return Object.fromEntries(Object.entries(input).filter(([, value]) => value !== undefined));
}
