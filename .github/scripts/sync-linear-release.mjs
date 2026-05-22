#!/usr/bin/env node

import { readFileSync, appendFileSync } from "node:fs";

const GRAPHQL_ENDPOINT = "https://api.linear.app/graphql";
const RELEASE_PIPELINE_BY_CHANNEL = {
  internal: "OS Prereleases",
  public: "OS Stable Releases",
};
const TARGET_STAGE_BY_CHANNEL = {
  internal: "In Progress",
  public: "Released",
};

const env = process.env;

const linearApiKey = requiredEnv("LINEAR_API_KEY");
const releaseChannel = requiredEnv("RELEASE_CHANNEL");
const releaseName = requiredEnv("RELEASE_NAME");
const tagName = requiredEnv("TAG_NAME");
const tagSha = requiredEnv("TAG_SHA");
const issueIdsPath = requiredEnv("ISSUE_IDS_PATH");
const featureOsUrlsPath = env.FEATUREOS_URLS_PATH;
const githubPrUrlsPath = env.GITHUB_PR_URLS_PATH;

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
const syncResult = await syncIssuesToRelease(release, { issueIdentifiers, featureOsUrls, githubPrUrls });

setOutput("release_id", release.id);
setOutput("release_url", release.url || "");
setOutput("release_name", release.name || releaseName);
setOutput("release_version", release.version || tagName);
setOutput("synced_issue_identifiers", syncResult.synced.length > 0 ? syncResult.synced.join(", ") : "none");
setOutput("skipped_issue_identifiers", syncResult.skipped.length > 0 ? syncResult.skipped.join(", ") : "none");

console.log(`Synced Linear release ${release.name} (${release.version || tagName})`);
console.log(`Attached issues: ${syncResult.synced.length > 0 ? syncResult.synced.join(", ") : "none"}`);
console.log(`Skipped issues: ${syncResult.skipped.length > 0 ? syncResult.skipped.join(", ") : "none"}`);

async function upsertRelease({ pipeline, targetStage }) {
  const existing = await findRelease(pipeline.id, tagName, releaseName);
  const description = [
    "Synced from unraid/webgui tag automation.",
    "",
    `Tag: ${tagName}`,
    `Commit: ${tagSha}`,
    env.PREVIOUS_TAG ? `Previous tag: ${env.PREVIOUS_TAG}` : undefined,
    env.RANGE_SPEC ? `Commit range: ${env.RANGE_SPEC}` : undefined,
  ].filter(Boolean).join("\n");

  if (!existing) {
    return createRelease({
      pipelineId: pipeline.id,
      name: releaseName,
      version: tagName,
      description,
      commitSha: tagSha,
      stageId: targetStage.id,
    });
  }

  const input = {
    name: existing.name === releaseName ? undefined : releaseName,
    description,
    commitSha: existing.commitSha === tagSha ? undefined : tagSha,
  };

  if (!isTerminalReleaseStage(existing.stage) && existing.stage?.id !== targetStage.id) {
    input.stageId = targetStage.id;
  }

  if (Object.values(input).some((value) => value !== undefined)) {
    return updateRelease(existing.id, input);
  }

  return existing;
}

async function syncIssuesToRelease(release, { issueIdentifiers, featureOsUrls, githubPrUrls }) {
  const synced = [];
  const skipped = [];
  const seenIssueIds = new Set();

  for (const identifier of issueIdentifiers) {
    const issue = await findIssue(identifier);
    if (!issue || issue.archivedAt) {
      skipped.push(`${identifier} (not found)`);
      continue;
    }

    await syncIssueToRelease(issue, release, synced, seenIssueIds);
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

      await syncIssueToRelease(issue, release, synced, seenIssueIds);
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

      await syncIssueToRelease(issue, release, synced, seenIssueIds);
    }
  }

  return { synced, skipped };
}

async function syncIssueToRelease(issue, release, synced, seenIssueIds) {
  if (seenIssueIds.has(issue.id)) {
    return;
  }
  seenIssueIds.add(issue.id);

  const releaseIds = new Set((issue.releases?.nodes || []).map((item) => item.id));
  if (!releaseIds.has(release.id)) {
    await updateIssue(issue.id, { addedReleaseIds: [release.id] });
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
        }
      }
    }
  `, { id, input: dropUndefined(input) });

  if (!data.releaseUpdate.success || !data.releaseUpdate.release) {
    throw new Error(`Linear release update failed for ${id}`);
  }

  return data.releaseUpdate.release;
}

async function findIssue(identifier) {
  const data = await graphql(`
    query FindIssue($id: String!) {
      issue(id: $id) {
        id
        identifier
        archivedAt
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
