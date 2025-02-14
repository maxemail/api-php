<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Maxemail API Client
 *
 * @package Maxemail\Api
 * @copyright 2007-2025 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license LGPL-3.0
 */
class Middleware
{
    use JsonTrait;

    /**
     * @see https://michaelstivala.com/logging-guzzle-requests/
     */
    public static function addLogging(HandlerStack $stack, LoggerInterface $logger): void
    {
        $messageFormats = [
            '{method}: {uri} HTTP/{version} {req_body}', // request
            'RESPONSE: {code} - {res_body}', // response
        ];

        // Using push() to put middleware onto top of stack, so loggers are first to run after handler
        // Order of loggers needs to be reversed, as last to put pushed on top will be first to execute
        foreach (array_reverse($messageFormats, true) as $idx => $messageFormat) {
            $middleware = GuzzleMiddleware::log(
                $logger,
                new MessageFormatter($messageFormat),
                LogLevel::DEBUG,
            );
            $stack->push($middleware, 'log' . $idx);
        }
    }

    /**
     * Read Maxemail's deprecation notices from the HTTP Warning header
     * to log as warning and trigger deprecation notice
     */
    public static function addWarningLogging(HandlerStack $stack, LoggerInterface $logger): void
    {
        $middleware = GuzzleMiddleware::mapResponse(function (ResponseInterface $response) use ($logger): ResponseInterface {
            if ($response->hasHeader('Warning')) {
                foreach ($response->getHeader('Warning') as $message) {
                    // Code, agent, message, [date]
                    $parts = str_getcsv($message, ' ', '"', '\\');
                    if ($parts[0] !== '299') {
                        continue;
                    }
                    if (stripos($parts[1], 'mxmapi/') !== 0) {
                        continue;
                    }
                    $logger->warning($parts[2]);
                    trigger_error($parts[2], E_USER_DEPRECATED);
                }
            }

            return $response;
        });
        $stack->push($middleware, 'mxm-deprecated');
    }

    /**
     * Add parser for Maxemail 4xx-level errors
     */
    public static function addMaxemailErrorParser(HandlerStack $stack): void
    {
        $middleware = GuzzleMiddleware::mapResponse(function (ResponseInterface $response): ResponseInterface {
            $code = $response->getStatusCode();
            if ($code < 400 || $code >= 500) {
                // Allow success response to continue, and 500-level errors to be handled by Guzzle
                return $response;
            }

            // Response body should be JSON with a 'msg' element detailing the error
            try {
                $error = self::decodeJson((string)$response->getBody());
                if ($error instanceof \stdClass && isset($error->msg)) {
                    throw new Exception\ClientException($error->msg, $response->getStatusCode());
                }
            } catch (\UnexpectedValueException) {
                // Failed to decode as Maxemail error, leave Guzzle to handle it
            }
            return $response;
        });
        $stack->push($middleware, 'mxm-error');
    }
}
