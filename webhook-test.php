<?php
// webhook-test.php
$payload = file_get_contents('php://input');
$headers = getallheaders();
file_put_contents(__DIR__ . '/webhook.log', $payload . "\n---\n", FILE_APPEND);
file_put_contents(__DIR__ . '/webhook.log', print_r($headers, true) . "\n===\n", FILE_APPEND);
echo json_encode(['status' => 'received']);
