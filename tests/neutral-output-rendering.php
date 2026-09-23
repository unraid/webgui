#!/usr/bin/env php
<?php
declare(strict_types=1);

function source(string $repo, string $path): string
{
  $content = file_get_contents("$repo/$path");
  if ($content === false) throw new RuntimeException("Could not read $path.");
  return $content;
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (str_contains($haystack, $needle)) throw new RuntimeException($message);
}

$repo = dirname(__DIR__);
$wireless = source($repo, 'emhttp/plugins/dynamix/include/Wireless.php');
$wirelessPage = source($repo, 'emhttp/plugins/dynamix/Wireless.page');
$logging = source($repo, 'emhttp/logging.htm');
$spice = source($repo, 'emhttp/plugins/dynamix.vm.manager/spice.html');
$ups = source($repo, 'emhttp/plugins/dynamix.apcupsd/include/UPSstatus.php');
$upsPage = source($repo, 'emhttp/plugins/dynamix.apcupsd/UPSdetails.page');
$control = source($repo, 'emhttp/plugins/dynamix/include/Control.php');
$browse = source($repo, 'emhttp/plugins/dynamix/include/Browse.php');
$browsePage = source($repo, 'emhttp/plugins/dynamix/Browse.page');
$templates = source($repo, 'emhttp/plugins/dynamix/include/Templates.php');
$docker = source($repo, 'emhttp/plugins/dynamix.docker.manager/include/DockerContainers.php');
$dockerPage = source($repo, 'emhttp/plugins/dynamix.docker.manager/DockerContainers.page');
$shares = source($repo, 'emhttp/plugins/dynamix/include/ShareList.php');
$pluginPage = source($repo, 'emhttp/plugins/dynamix.plugin.manager/PluginHelpers.page');

assertContainsText("'items'", $wireless, 'Wireless responses must carry values as data.');
assertNotContainsText('onclick="manage_wifi', $wireless, 'Wireless names must not be inserted into inline handlers.');
assertContainsText('render_wifi_section', $wirelessPage, 'Wireless sections must use the DOM renderer.');
assertContainsText('textContent = entry.network', $wirelessPage, 'Wireless names must be assigned as text.');
assertContainsText("'visible' => !\$load", $wireless, 'Wireless scan results must preserve the initial refresh behavior.');
assertNotContainsText("$('#connected').html", $wirelessPage, 'Wireless responses must not be inserted as HTML.');

assertContainsText('new URLSearchParams', $logging, 'Log completion text must come from a parsed query value.');
assertContainsText('button.textContent = done', $logging, 'Log completion text must be assigned as text.');
assertContainsText('textContent = (vmname)', $spice, 'Console labels must be assigned as text.');

assertContainsText("json_encode(['summary' => \$status", $ups, 'UPS status must be returned as structured data.');
assertNotContainsText('<td $green>', $ups, 'UPS values must not be assembled as markup.');
assertContainsText('renderUPS(data)', $upsPage, 'UPS details must use the DOM renderer.');
assertContainsText('summaryCell.textContent', $upsPage, 'UPS values must be assigned as text.');

assertContainsText('die(json_encode($jobs))', $control, 'File manager jobs must be returned as structured data.');
assertNotContainsText('<i id="queue_', $control, 'File manager values must not be assembled into handlers.');
assertContainsText('renderJobs(jobs)', $browsePage, 'File manager jobs must use the DOM renderer.');
assertContainsText('textContent = text', $browsePage, 'File manager job values must be assigned as text.');

assertContainsText("htmlspecialchars(\$ext, ENT_QUOTES, 'UTF-8')", $browse, 'File extensions must be encoded for attributes.');
assertNotContainsText('var source = "{$0}"', $templates, 'File paths must not be interpolated into script source.');
assertNotContainsText('href="{$0}"', $templates, 'File paths must not be interpolated into attributes.');
assertContainsText('window.dfm_file_source = source', $browsePage, 'File paths must cross into templates as runtime data.');

assertContainsText('implode("\n",$TSinfo)', $docker, 'Container status details must be plain text.');
assertContainsText('contentAsHTML: false', $dockerPage, 'Container status tooltips must not interpret content as markup.');
assertNotContainsText('contentAsHTML: true', $dockerPage, 'Container status tooltips must not interpret content as markup.');

assertContainsText("htmlspecialchars(\$name, ENT_QUOTES, 'UTF-8')", $shares, 'Share names must be encoded as text.');
assertContainsText('message.textContent = result.updateMessage', $pluginPage, 'Update metadata must be assigned as text.');
assertNotContainsText('result.updateMessage+"<span', $pluginPage, 'Update metadata must not be concatenated into markup.');

echo "Neutral output rendering regression checks passed.\n";
