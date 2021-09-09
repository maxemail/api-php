<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
class Service
{
    use JsonTrait;

    /**
     * @var string
     */
    private $service;

    /**
     * @var GuzzleClient
     */
    private $httpClient;

    public function __construct(string $service, GuzzleClient $httpClient)
    {
        $this->service = $service;
        $this->httpClient = $httpClient;
    }

    /**
     * Call service method
     *
     * @return mixed
     */
    public function __call(string $name, array $params)
    {
        $data = [
            'method' => $name,
        ];
        foreach ($params as $i => $param) {
            if (is_array($param)) {
                $param = json_encode($param);
            }
            $data['arg' . $i] = $param;
        }

        $json = $this->postRequest($data);

        return static::decodeJson($json);
    }

    /**
     * @return string JSON response
     */
    private function postRequest(array $data): string
    {
        $response = $this->httpClient->request('POST', $this->service, [
            'form_params' => $data,
        ]);
        return (string)$response->getBody();
    }
}
