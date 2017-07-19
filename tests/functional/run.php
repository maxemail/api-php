<?php
declare(strict_types=1);

$loader = require __DIR__ . '/../../vendor/autoload.php';

$config = require __DIR__ . '/config.php';

require __DIR__ . '/TestLogger.php';
$logger = new TestLogger();

$api = new \Mxm\Api($config['api']);
$api->setLogger($logger);

$emailTree = $api->tree->fetchRoot('email', array());

$emailRoot = $emailTree[0];

$testResult = ($emailRoot->text === 'email' && $emailRoot->rootNode === true);

if (!$testResult) {
    echo "Email root not found \n";
    exit(1);
}

echo "Test complete \n";
exit(0);
