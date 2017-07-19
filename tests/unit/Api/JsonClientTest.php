<?php
declare(strict_types=1);

namespace Mxm\Api;

use PHPUnit\Framework\TestCase;

class JsonClientTest extends TestCase
{
    private $testConfig = [
        'host'   => 'maxemail.emailcenteruk.com',
        'user'   => 'api@user.com',
        'pass'   => 'apipass',
        'useSsl' => true
    ];

    public function testSetGetLogger()
    {
        /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $api = new JsonClient('service', $this->testConfig);

        $api->setLogger($logger);

        $this->assertEquals($logger, $api->getLogger());
    }
}
