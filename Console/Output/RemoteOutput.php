<?php

namespace Modera\Module\Console\Output;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class RemoteOutput extends Output
{
    /**
     * @var callable
     */
    protected $remote;

    /**
     * @param callable $remote
     * @param bool|int $verbosity
     * @param bool $decorated
     * @param OutputFormatterInterface $formatter
     */
    public function __construct(\Closure $remote, $verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
    {
        $this->remote = $remote;

        parent::__construct($verbosity, $decorated, $formatter);
    }

    /**
     * @param string $message
     * @param bool $newline
     */
    protected function doWrite($message, $newline)
    {
        $remote = $this->remote;
        $remote($message . ($newline ? PHP_EOL : ''));
    }
}

