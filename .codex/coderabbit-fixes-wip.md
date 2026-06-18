# CodeRabbit Fixes WIP

## Context

- Repo: unraid/webgui
- Branch: feat/backend-task-queue
- PR: 2665
- PR URL: https://github.com/unraid/webgui/pull/2665
- Generated at: 2026-06-18

## Inputs Pulled

- [x] Unresolved robot review threads pulled (7)
- [x] Top-level robot review notes and PR conversation comments pulled
- [x] Top-level actionable review-body/PR comments extracted into queue (5 nitpicks)
- [x] User asked whether to include human review comments
- [x] Human review comments included in queue: no (user chose robot-only)
- [x] Existing non-CodeRabbit thread replies checked before adding duplicate feedback (none from bots/humans; only a github-actions test-plugin notice)

## Fix Queue

| Item ID | Type | File | Line | Summary | Status | Link | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| CR-001 | thread | BodyInlineJS.php | 214 | XSS in swal title via task.title (html:true) | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502042 | escapeTaskHtml(task.title) in swal title; php -l OK |
| CR-002 | thread | BodyInlineJS.php | 279-291 | t.id unescaped in onclick handlers | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502054 | safeId=escapeTaskHtml(t.id) used in all 5 handlers; php -l OK |
| CR-003 | thread | HeadInlineJS.php | 183-192 | createTask silent failure on POST error | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502071 | .fail() hides spinner + error swal; php -l OK |
| CR-004 | thread | TaskQueue.php | 123 | Command injection via unescaped $args in bash -c | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502088 | escapeshellarg() over whole bash -c payload (preserves multi-arg word-split); reply posted explaining deviation from literal suggestion; php -l OK |
| CR-005 | thread | nchan/tasks | 27-30 | Empty/non-numeric PID breaks /proc liveness check | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502115 | ctype_digit() guard before file_exists; php -l OK |
| CR-006 | thread | default-base.css | 1919-1924 | Add min-width:0 for reliable ellipsis | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3436502123 | min-width:0 added to .op-tray .op-title |
| CR-007 | thread | nchan/tasks | 62-67 | Daemon sleeps forever on queued-only restart | DONE | https://github.com/unraid/webgui/pull/2665#discussion_r3437357364 | queued-without-running recovery pass before advance/active check; php -l OK |
| RVW-001 | review-body | TaskQueue.php | 147-177 | Race: concurrent create/launch can break one-running-per-type | DONE | https://github.com/unraid/webgui/pull/2665#pullrequestreview-4525730471 | per-type flock around dedupe->create->launch; php -l OK |
| RVW-002 | review-body | TaskCommand.php | 35-51 | abort/dismiss return no JSON body | BLOCKED | https://github.com/unraid/webgui/pull/2665#pullrequestreview-4525730471 | Declined: response body is unused; canonical success signal is the `tasks` nchan broadcast. Adding unused API surface conflicts with no-speculative-contract policy. Reply: https://github.com/unraid/webgui/pull/2665#issuecomment-4745856704 |
| RVW-003 | review-body | TaskCommand.php | 38-44 | Validate PID numeric before kill | DONE | https://github.com/unraid/webgui/pull/2665#pullrequestreview-4525730471 | ctype_digit()+(int)>1 guard before kill; php -l OK |
| RVW-004 | review-body | nchan/tasks | 32-37 | _ERROR_ marker substring false positives | DONE | https://github.com/unraid/webgui/pull/2665#pullrequestreview-4525730471 | match _ERROR_ as discrete \x1e record (canonical sentinel, same as routeMessage); reads tail in PHP, removing exec last-line fragility; php -l OK |
| RVW-005 | review-body | BodyInlineJS.php | 126-177 | Unescaped HTML in nchan message rendering | BLOCKED | https://github.com/unraid/webgui/pull/2665#pullrequestreview-4525730471 | Declined: renderMessage is a deliberate HTML/span protocol (addToID injects `<span id=...>`); blanket escaping breaks the live-log structure. Data originates from trusted server-side processes on a server-controlled channel, not untrusted client input. Reply posted. |

## Execution Log

1. CR-001 swal title — escaped task.title with escapeTaskHtml. DONE.
2. CR-002 onclick ids — introduced safeId=escapeTaskHtml(t.id), used everywhere. DONE.
3. CR-003 createTask — added .fail() with spinner hide + error swal. DONE.
4. CR-004 command injection — wrapped the whole `sleep .3 && $name $args$suffix` payload in escapeshellarg() (superior to literal escapeshellarg($args), which would collapse multiple space-separated args). DONE + thread reply.
5. CR-005 task_pid_alive — ctype_digit() numeric guard. DONE.
6. CR-007 queued-only recovery — added recovery pass + $changed=true so launch publishes. DONE.
7. RVW-004 _ERROR_ — discrete \x1e record match read in PHP. DONE.
8. CR-006 CSS — min-width:0. DONE.
9. RVW-001 race — per-type flock around critical section, released on every return path. DONE.
10. RVW-003 PID kill — ctype_digit()+(int)>1. DONE.
11. RVW-002 — BLOCKED (unused response surface). Reply posted.
12. RVW-005 — BLOCKED (deliberate HTML protocol, trusted source). Reply posted.

Validation: `php -l` clean on TaskQueue.php, TaskCommand.php, nchan/tasks, BodyInlineJS.php, HeadInlineJS.php.

## Final Checks

- [x] Queue reviewed: no `TODO` left
- [x] Remaining `BLOCKED` items documented with reason (RVW-002, RVW-005)
- [x] Every `BLOCKED`/not-valid CodeRabbit suggestion has a PR reply with the reason it was not applied
- [x] Re-pulled CodeRabbit threads and reviews
- [x] No unhandled top-level review-body comment remains
