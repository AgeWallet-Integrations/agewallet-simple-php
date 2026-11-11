<?php
/**
 * Script for BuiltInServer, used by TokenExchangeTest as token_url: writes the request's method, Content-Type and raw
 * body as JSON to ?record=<name>, then replies with the contents of ?reply=<name> (both files in AW_TEST_DIR).
 * Answers 404 unless it runs under php -S with AW_TEST_DIR set (BuiltInServer::start()).
 */

$dir = getenv('AW_TEST_DIR');
if (PHP_SAPI !== 'cli-server' || !$dir || !isset($_GET['record'], $_GET['reply'])) {
    http_response_code(404);
    exit;
}

file_put_contents($dir . '/' . basename($_GET['record']), json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'body' => file_get_contents('php://input'),
]));

echo (string) @file_get_contents($dir . '/' . basename($_GET['reply']));
