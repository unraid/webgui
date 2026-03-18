# Dynamix Docker Manager

## Ghost/dead container filtering in WebGUI

Docker Engine can leave stale container metadata in a `dead` state after certain shutdown races (commonly seen with `--rm`/autoremove flows). These entries may appear in `docker ps -a` but are typically not actionable.

This behavior is documented upstream in Moby:

- <https://github.com/moby/moby/pull/51692>
- <https://github.com/moby/moby/pull/51693>
- <https://github.com/moby/moby/commit/9f5f4f5a4273e920d5d77c1e73db8bebe65982bb>

Unraid WebGUI tracks this in:

- <https://github.com/unraid/webgui/pull/2577>

Current WebGUI behavior for Docker list rendering:

- Filters `dead`/stale entries from the UI list.
- Does **not** auto-delete or otherwise mutate Docker container state.
