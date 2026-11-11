<?php
/**
 * PHPUnit bootstrap: PHPUnit's autoloader, then agewallet-client.php in include mode (no standalone output) with a
 * session that starts without cookies or cache headers, then the helpers in tests/Support/.
 */

ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
ini_set('session.save_path', sys_get_temp_dir());

require __DIR__ . '/../vendor/autoload.php';

define('AGEWALLET_INCLUDE', true);
require __DIR__ . '/../agewallet-client.php';

// The config as agewallet-client.php ships it; ClientTestCase::setUp() starts each test from it.
$GLOBALS['agewallet_shipped_config'] = $GLOBALS['agewallet_config'];

require __DIR__ . '/Support/Keys.php';
require __DIR__ . '/Support/ClientTestCase.php';
require __DIR__ . '/Support/BuiltInServer.php';
