<?php

declare(strict_types=1);

namespace Maxemail\Api;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
trait JsonTrait
{
    private static function decodeJson(string $json): string|int|bool|\stdClass
    {
        $result = json_decode($json, false);
        $errorCode = json_last_error();

        if ($errorCode === JSON_ERROR_NONE) {
            return $result;
        }

        // Generate error message
        switch ($errorCode) {
            case JSON_ERROR_DEPTH:
                $error = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Invalid or malformed JSON';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error';
                break;
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            case JSON_ERROR_UTF16:
                $error = 'Malformed UTF-16 characters, possibly incorrectly encoded';
                break;
            default:
                $error = 'Unknown error';
                // Find the const name
                $constants = get_defined_constants(true);
                foreach ($constants['json'] as $name => $value) {
                    if (!strncmp($name, 'JSON_ERROR_', 11) && $value === $errorCode) {
                        $error = $name;
                        break;
                    }
                }
                break;
        }

        throw new Exception\UnexpectedValueException("Problem decoding JSON : {$error} : '{$json}'");
    }
}
