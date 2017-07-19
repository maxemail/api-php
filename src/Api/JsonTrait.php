<?php

namespace Mxm\Api;

use Mxm\Api;

/**
 * MXM JSON API Client
 *
 * @package    Mxm/Api
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://www.emailcenteruk.com)
 * @license    LGPL-3.0
 */
trait JsonTrait
{
    /**
     * Process JSON API response
     *
     * @param string $body
     * @param int $httpCode
     * @return string
     * @throws \RuntimeException
     */
    protected function processJsonResponse($body, $httpCode)
    {
        if ((int)$httpCode != 200) {
            try {
                $message = $this->decodeJson($body);
                if ($message instanceof \stdClass && isset($message->msg)) {
                    $body = $message->msg;
                }
            } catch (\UnexpectedValueException $e) {
                // Void
                // Failed to decode, leave content as the raw response
            }
            throw new \RuntimeException($body, $httpCode);
        }

        return $body;
    }

    /**
     * Decode JSON
     *
     * @param string $json
     * @return mixed
     * @throws \UnexpectedValueException
     */
    protected function decodeJson($json)
    {
        $result = json_decode($json, false);

        if ($result === null) {
            // Error checking
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $error = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_NONE:
                default:
                    // Value is really null
                    $error = '';
                    break;
            }

            if (!empty($error)) {
                throw new \UnexpectedValueException("Problem decoding json ($json), {$error}");
            }
        }

        return $result;
    }
}
