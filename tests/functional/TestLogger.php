<?php
declare(strict_types=1);

/**
 * Simple logger to STDERR
 * @link http://jamiehannaford.com/php/fig/logging/logging-with-psr-3/
 */
class TestLogger extends \Psr\Log\AbstractLogger
{
    protected $stream;

    public function __construct()
    {
        $this->stream = STDERR;
    }

    public function setStream($stream)
    {
        $this->stream = $stream;
    }

    public function log($level, $message, array $context = [])
    {
        $message = date('c') . " [{$level}] : {$message} : " . var_export($context, true) . "\n";
        fwrite($this->stream, $message);
    }
}
