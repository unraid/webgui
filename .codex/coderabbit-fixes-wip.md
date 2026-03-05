# CodeRabbit Fixes WIP

## Context

- Repo: unraid/webgui
- Branch: change-flash-name
- PR: 2564
- PR URL: https://github.com/unraid/webgui/pull/2564
- Generated at: 2026-03-05 14:50:57 +00:00

## Inputs Pulled

- [ ] Unresolved CodeRabbit review threads pulled (GraphQL blocked: 403 without `gh` auth)
- [x] Top-level CodeRabbit review notes pulled
- [x] Top-level nitpicks extracted into queue

## Fix Queue

| Item ID | Type | File | Line | Summary | Status | Link | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| CR-001 | thread | emhttp/languages/en_US/helptext.txt | 109 | Replace "USB Flash device" with "boot device" for consistent terminology | DONE | https://github.com/unraid/webgui/pull/2564#discussion_r2890266837 | `git diff -- emhttp/languages/en_US/helptext.txt` |
| CR-002 | thread | emhttp/plugins/dynamix.plugin.manager/scripts/plugin | 112 | Fix possessive grammar: "users boot device" -> "user's boot device" (2 occurrences) | DONE | https://github.com/unraid/webgui/pull/2564#discussion_r2890266853 | `git diff -- emhttp/plugins/dynamix.plugin.manager/scripts/plugin` |
| CR-003 | thread | emhttp/plugins/dynamix/BootInfo.page | 37 | Re-enable backup button on failure path so retry works | DONE | https://github.com/unraid/webgui/pull/2564#discussion_r2890266875 | `git diff -- emhttp/plugins/dynamix/BootInfo.page` |
| CR-004 | thread | emhttp/plugins/dynamix/scripts/monitor | 299 | Use token-safe rw regex for /proc/mounts option matching | DONE | https://github.com/unraid/webgui/pull/2564#discussion_r2890266882 | `git diff -- emhttp/plugins/dynamix/scripts/monitor` |
| CR-005 | thread | emhttp/plugins/dynamix/scripts/rsyslog_config | 52-56 | Ensure `*.debug ?flash` insertion is independent of template insertion | DONE | https://github.com/unraid/webgui/pull/2564#pullrequestreview-3896956960 | `git diff -- emhttp/plugins/dynamix/scripts/rsyslog_config` |
| CR-006 | thread | emhttp/plugins/dynamix/nchan/device_list | 495-513 | Exclude boot/flash rows from spin-toggle controls | DONE | https://github.com/unraid/webgui/pull/2564#pullrequestreview-3896956960 | `git diff -- emhttp/plugins/dynamix/nchan/device_list` |
| NIT-001 | nitpick | emhttp/plugins/dynamix/BootParameters.page | 52 | Legacy anchor compatibility shim for `#boot-parameters` | BLOCKED | https://github.com/unraid/webgui/pull/2564#pullrequestreview-3896956960 | Blocked by skill policy: no legacy shims/fallback compatibility |
| NIT-002 | nitpick | emhttp/plugins/dynamix/nchan/device_list | 1101,1223 | Remove redundant boot label assignment before immediate overwrite | DONE | https://github.com/unraid/webgui/pull/2564#pullrequestreview-3896956960 | `git diff -- emhttp/plugins/dynamix/nchan/device_list` |

## Execution Log

### 1. Item: CR-001
- Action: Updated help text sentence to use "boot device".
- Validation: `git diff -- emhttp/languages/en_US/helptext.txt`
- Result: DONE

### 2. Item: CR-002
- Action: Updated both possessive grammar occurrences in plugin manager script docs.
- Validation: `git diff -- emhttp/plugins/dynamix.plugin.manager/scripts/plugin`
- Result: DONE

### 3. Item: CR-003
- Action: Updated failure branch in `backup()` to restore button text and `disabled=false` using a single `$btn` handle.
- Validation: `git diff -- emhttp/plugins/dynamix/BootInfo.page`
- Result: DONE

### 4. Item: CR-004
- Action: Replaced `^rw` with `(^|,)rw(,|$)` in boot rw check awk expression.
- Validation: `git diff -- emhttp/plugins/dynamix/scripts/monitor`
- Result: DONE

### 5. Item: CR-005
- Action: Moved `*.debug ?flash` ensure logic outside template-missing branch and guarded with `grep`.
- Validation: `git diff -- emhttp/plugins/dynamix/scripts/rsyslog_config`
- Result: DONE

### 6. Item: CR-006
- Action: Tightened spin-toggle guard to `!$flash && !$boot`.
- Validation: `git diff -- emhttp/plugins/dynamix/nchan/device_list`
- Result: DONE

### 7. Item: NIT-001
- Action: Assessed legacy anchor suggestion.
- Validation: Policy check against skill contract (`NO FALLBACKS / LEGACY SUPPORT`).
- Result: BLOCKED (intentional by policy)

### 8. Item: NIT-002
- Action: Removed duplicate `$bootLabel = ucfirst($bootPoolName);` assignments in both code paths.
- Validation: `git diff -- emhttp/plugins/dynamix/nchan/device_list`
- Result: DONE

## Final Checks

- [x] Queue reviewed: no `TODO` left
- [x] Remaining `BLOCKED` items documented with reason
- [x] Re-pulled CodeRabbit threads and reviews (REST pull repeated; thread-resolved state unavailable without GraphQL auth)
- [x] No unhandled top-level nitpick remains
