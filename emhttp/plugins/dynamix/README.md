**Dynamix webGui**

The *Dynamix* webGui is the latest iteration of the Unraid OS System
Management Utility.  Built upon *Simple Features*, it provides real-time
screen updates, tabbed viewing and many more enhancements.

## Searchable dropdowns

`jquery.searchable-select.js` enhances an existing single-select field with
case-insensitive substring search over option labels and values. Underscores
match spaces. The field keeps WebGUI's native dropdown styling in header and
sidebar themes. It uses the bundled jQuery UI widget and autocomplete libraries;
no extra dependency is needed.

Load the shared assets only on pages that use the control:

```php
<link type="text/css" rel="stylesheet" href="<?php autov('/webGui/styles/jquery.searchable-select.css') ?>">
<script src="<?php autov('/webGui/javascript/jquery.searchable-select.js') ?>"></script>
```

Initialize a labeled select after its options are rendered. Supply a translated
button label from the page:

```javascript
$(function() {
  if ($.fn.searchableSelect) {
    $('#my-select').searchableSelect({buttonLabel:"_(Show options)_"});
  }
});
```

Each select has its own input and menu. Calling initialization again does not
create a second control. Accessible names come from the native select's
`aria-labelledby`, `aria-label`, or associated labels; the `label` option can
override the name. `buttonLabel` defaults to `Show options`.

The original select remains the only submitted value. Choosing a different
option emits its normal `change` event once. Typing or dismissing a search does
not change the selection or mark the settings form as changed. Disabled options,
including options in a disabled group, are excluded from results. Native
required-field errors are presented on the visible input. Form reset restores
the selected label without emitting a change.

Use the jQuery UI widget methods for lifecycle changes:

```javascript
$('#my-select').val('new-value').trigger('change');
$('#my-select').searchableSelect('refresh'); // After editing options or attributes.
$('#my-select').searchableSelect('disable');
$('#my-select').searchableSelect('enable');
$('#my-select').searchableSelect('destroy'); // Restore the native select.
```

`refresh` also synchronizes an externally changed `disabled` property and closes
an open search. `destroy` removes the generated input, menu, and event handlers
while preserving the native selection. Multiple-select fields remain native.
If the assets or autocomplete dependency are unavailable, the guarded
initialization above leaves the original dropdown available.
