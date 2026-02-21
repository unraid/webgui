<?php
/**
 * PHPUnit bootstrap file for Unraid WebGUI tests
 */

// Include Composer autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Define test constants
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Mock global functions that may not be available in test environment
if (!function_exists('_var')) {
    function _var($array, $key, $default = '') {
        return $array[$key] ?? $default;
    }
}

if (!function_exists('my_logger')) {
    function my_logger($message, $logger = 'test') {
        // No-op in tests
    }
}

if (!function_exists('_')) {
    function _($text, $mode = 0) {
        return $text;
    }
}

if (!function_exists('my_explode')) {
    function my_explode($delimiter, $string, $limit = PHP_INT_MAX) {
        return explode($delimiter, $string, $limit);
    }
}

if (!function_exists('my_preg_split')) {
    function my_preg_split($pattern, $string) {
        return preg_split($pattern, $string);
    }
}