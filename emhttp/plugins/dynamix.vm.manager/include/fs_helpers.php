<?php
/* Filesystem helper utilities for dynamix.vm.manager
 * - files_identical($a,$b)
 * - copy_if_different($src,$dst, $dry_run=false)
 * - dir_copy($src,$dst)  (recursive, skips identical files)
 * - dir_remove($dir)     (recursive remove)
 */

function files_identical($a, $b) {
    if (!file_exists($a) || !file_exists($b)) return false;
    if (filesize($a) !== filesize($b)) return false;
    $ha = @md5_file($a);
    $hb = @md5_file($b);
    if ($ha === false || $hb === false) return false;
    return $ha === $hb;
}

function copy_if_different($src, $dst, $dry_run = false) {
    $result = [
        'src' => $src,
        'dst' => $dst,
        'would_copy' => false,
        'copied' => false,
        'error' => null
    ];

    if (!file_exists($src)) {
        $result['error'] = 'source not found';
        return $result;
    }

    $dst_dir = dirname($dst);
    if (!is_dir($dst_dir)) {
        if ($dry_run) {
            $result['would_copy'] = true;
            return $result;
        }
        if (!@mkdir($dst_dir, 0755, true)) {
            $result['error'] = 'failed to create dest dir';
            return $result;
        }
    }

    if (file_exists($dst)) {
        if (files_identical($src, $dst)) {
            return $result; // identical, nothing to do
        }
        $result['would_copy'] = true;
    } else {
        $result['would_copy'] = true;
    }

    if ($dry_run) return $result;

    if (@copy($src, $dst)) {
        $result['copied'] = true;
    } else {
        $result['error'] = 'copy_failed';
    }

    return $result;
}

function dir_copy($src, $dst) {
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) {
        if (!@mkdir($dst, 0755, true)) return false;
    }
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . DIRECTORY_SEPARATOR . $item;
        $d = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($s)) {
            if (!dir_copy($s, $d)) return false;
        } else {
            if (file_exists($d)) {
                if (files_identical($s, $d)) continue;
            }
            if (!@copy($s, $d)) return false;
        }
    }
    return true;
}

function dir_remove($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            dir_remove($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}
