<?php

$loader = require dirname(__DIR__) . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$api = new \Mxm\Api($config['api']);

$emailTree = $api->tree->fetchRoot('email', array());

$emailRoot = $emailTree[0];

$testResult = ($emailRoot->text === 'email' && $emailRoot->rootNode === true);

if (!$testResult) {
    echo "Email root not found \n";
    exit(1);
}

echo "Test complete \n";
exit(0);
