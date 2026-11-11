<?php
/**
 * Script for BuiltInServer, used by StartAuthTest: loads agewallet-client.php in include mode, merges the config
 * overrides from the JSON file ?config=<name> in AW_TEST_DIR, prints $_SESSION as JSON at shutdown, and calls
 * agewallet_start_auth(), which sends the Location header and exits.
 * Answers 404 unless it runs under php -S with AW_TEST_DIR set (BuiltInServer::start()).
 */

$dir = getenv('AW_TEST_DIR');
if (PHP_SAPI !== 'cli-server' || !$dir) {
    http_response_code(404);
    exit;
}

define('AGEWALLET_INCLUDE', true);
require __DIR__ . '/../../agewallet-client.php';

if (isset($_GET['config'])) {
    $overrides = json_decode((string) file_get_contents($dir . '/' . basename($_GET['config'])), true);
    $GLOBALS['agewallet_config'] = array_merge($GLOBALS['agewallet_config'], is_array($overrides) ? $overrides : []);
}

register_shutdown_function(static function (): void {
    echo json_encode($_SESSION);
});

agewallet_start_auth();
