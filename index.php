<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'service'  => SITE_NAME,
    'version'  => '1.0.0',
    'endpoint' => '/api/connect',
    'docs'     => '/README.md',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
