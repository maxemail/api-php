<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\ClientInterface as GuzzleClient;

/**
 * Maxemail API Client
 *
 * @package Maxemail\Api
 * @copyright 2007-2025 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license LGPL-3.0
 */
class Service
{
    use JsonTrait;

    public function __construct(
        private readonly string $service,
        private readonly GuzzleClient $httpClient,
    ) {
    }

    /**
     * Call service method
     */
    public function __call(string $name, array $params): string|int|bool|array|\stdClass
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
