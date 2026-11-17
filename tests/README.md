# Browser regression tests

`searchable-select.cjs` loads the repository's bundled jQuery, jQuery UI, styles,
and shared widget in a real browser. It needs Node.js, `@playwright/test`, and
the matching Playwright Chromium browser. Install those in your test environment,
then run from the repository root:

```sh
node tests/searchable-select.cjs
```

If Playwright is installed outside this checkout, set `NODE_PATH` to that
environment's `node_modules` directory. Set `BROWSER=webkit` to run the same
checks with Playwright WebKit after installing its browser.

The tests cover reopening after selection or Escape, preserving an active
search, native and jQuery change listeners, bubbling and inline handlers,
same-value selection, keyboard selection, clean search dismissal, native form
values, wrapping and external labels, independent instances, disable/reset,
destroy, and repeated initialization. Each fixture and browser is closed on exit;
no HTTP server or appliance is needed.
