<?php
function get_cpu_packages(string $separator = ','): array {
    $packages = [];

    foreach (glob("/sys/devices/system/cpu/cpu[0-9]*/topology/thread_siblings_list") as $path) {
        $pkg_id   = (int)file_get_contents(dirname($path) . "/physical_package_id");
        $siblings = str_replace(",", $separator, trim(file_get_contents($path)));

        if (!in_array($siblings, $packages[$pkg_id] ?? [])) {
            $packages[$pkg_id][] = $siblings;
        }
    }

    // Sort groups within each package by first CPU number
    foreach ($packages as &$list) {
        $keys = array_map(fn($s) => (int)explode($separator, $s)[0], $list);
        array_multisort($keys, SORT_ASC, SORT_NUMERIC, $list);
    }
    unset($list);

    return $packages;
}
