#!/usr/bin/env php
<?php
declare(strict_types=1);

// Dependencies
$loader = require __DIR__ . '/../../vendor/autoload.php';

// Config
$config = require __DIR__ . '/config.php';

// API
$api = new \Mxm\Api($config['api']);

// Logger
$logger = new \Phlib\Logger\LoggerType\CliColor('api-test');
$api->setLogger($logger);

// Test email tree
$emailTree = $api->tree->fetchRoot('email', []);
$emailRoot = $emailTree[0];
$testResult = ($emailRoot->text === 'email' && $emailRoot->rootNode === true);
if (!$testResult) {
    $logger->error('Email root not found');
    exit(1);
}

// Test upload/download
$sampleFile = __DIR__ . '/__files/sample-file.csv';
$key = $api->getHelper()->uploadFile($sampleFile);
//$logger->info('Uploaded file', ['key' => $key]);

$downloadFile = $api->getHelper()->downloadFile('file', $key);
//$logger->info('Downloaded file', ['filename' => $downloadFile]);

if (file_get_contents($sampleFile) !== file_get_contents($downloadFile)) {
    $logger->error('Files did not match');
    exit(1);
}

$logger->notice('Test complete');
exit(0);
