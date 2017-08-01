<?php
declare(strict_types=1);

namespace Emailcenter\MaxemailApi;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Maxemail API Client
 *
 * @package    Emailcenter/MaxemailApi
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://www.emailcenteruk.com)
 * @license    LGPL-3.0
 */
class Middleware
{
    use JsonTrait;

    /**
     * @see https://michaelstivala.com/logging-guzzle-requests/
     * @param HandlerStack $stack
     * @return void
     */
    public static function addLogging(HandlerStack $stack, LoggerInterface $logger)
    {
        $messageFormats = [
            '{method}: {uri} HTTP/{version} {req_body}', // request
            'RESPONSE: {code} - {res_body}' // response
        ];

        // Using push() to put middleware onto top of stack, so loggers are first to run after handler
        // Order of loggers needs to be reversed, as last to put pushed on top will be first to execute
        foreach (array_reverse($messageFormats, true) as $idx => $messageFormat) {
            $middleware = GuzzleMiddleware::log(
                $logger,
                new MessageFormatter($messageFormat),
                LogLevel::DEBUG
            );
            $stack->push($middleware, 'log' . $idx);
        };
    }

    /**
     * Add parser for Maxemail 4xx-level errors
     * @param HandlerStack $stack
     */
    public static function addMaxemailErrorParser(HandlerStack $stack)
    {
        $middleware = GuzzleMiddleware::mapResponse(function (ResponseInterface $response) use ($stack) {
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
            } catch (\UnexpectedValueException $e) {
                // Failed to decode as Maxemail error, leave Guzzle to handle it
            }
            return $response;
        });
        $stack->push($middleware, 'mxm-error');
    }
}
