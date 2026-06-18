<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
function input_secure_users($sec) {
  global $name, $users;
  echo "<dl>";
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "read-only";
    echo "<dt>{$user['name']}</dt>";
    echo "<dd><select name='userAccess.$idx'>";
    echo mk_option($userAccess, "read-write", _("Read/Write"));
    echo mk_option($userAccess, "read-only", _("Read-only"));
    echo "</select></dd>";
  }
  echo "</dl>";
}
function input_private_users($sec) {
  global $name, $users;
  echo "<dl>";
  $read_list = explode(",", $sec[$name]['readList']);
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $read_list))
      $userAccess = "read-only";
    elseif (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "no-access";
    echo "<dt>{$user['name']}</dt>";
    echo "<dd><select name='userAccess.$idx'>";
    echo mk_option($userAccess, "read-write", _("Read/Write"));
    echo mk_option($userAccess, "read-only", _("Read-only"));
    echo mk_option($userAccess, "no-access", _("No Access"));
    echo "</select></dd>";
  }
  echo "</dl>";
}
?>