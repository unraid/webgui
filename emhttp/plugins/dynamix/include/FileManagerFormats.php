<?PHP
/* Copyright 2026, Marc Gutt, Gutt.IT
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

// Archive formats of the compress dialog of the File Manager (format => label). The formats in $dfm_single_formats compress
// one file only, they are offered if exactly one file (no folder) is selected.
$dfm_formats = [
  'zip'     => 'ZIP (.zip)',
  'tar'     => 'TAR (.tar)',
  'tar.gz'  => 'TAR.GZ (.tar.gz)',
  'tar.bz2' => 'TAR.BZ2 (.tar.bz2)',
  'tar.xz'  => 'TAR.XZ (.tar.xz)',
  'tar.zst' => 'TAR.ZST (.tar.zst - recommended for appdata)',
  'tar.lz4' => 'TAR.LZ4 (.tar.lz4)',
  'gz'      => 'GZ (.gz)',
  'xz'      => 'XZ (.xz)',
  'bz2'     => 'BZ2 (.bz2)',
  'zst'     => 'ZST (.zst)',
  'lz4'     => 'LZ4 (.lz4)'
];
$dfm_single_formats = ['gz', 'xz', 'bz2', 'zst', 'lz4'];

// Formats which the user selected on the File Manager settings page. Unknown values are ignored and ZIP is the fallback, so the
// dialog always offers a format for folders.
$dfm_settings = parse_plugin_cfg('dynamix', true)['filemanager'] ?? [];
$dfm_enabled  = array_intersect(array_keys($dfm_formats), array_map('trim', explode(',', ($dfm_settings['multi'] ?? '').','.($dfm_settings['single'] ?? ''))));
if (!array_diff($dfm_enabled, $dfm_single_formats)) $dfm_enabled[] = 'zip';

// <option> list of the compress dialog
$dfm_options = '';
foreach ($dfm_formats as $dfm_format => $dfm_label) {
  if (!in_array($dfm_format, $dfm_enabled)) continue;
  $dfm_class = in_array($dfm_format, $dfm_single_formats) ? ' class="dfm_single_format"' : '';
  $dfm_options .= "<option value=\"$dfm_format\"$dfm_class>$dfm_label</option>\n";
}
