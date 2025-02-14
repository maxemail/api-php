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
    private static function decodeJson(string $json): string|int|bool|array|\stdClass
    {
        try {
            return json_decode($json, false, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Exception\UnexpectedValueException("Problem decoding JSON : {$e->getMessage()} : '{$json}'");
        }
    }
}
