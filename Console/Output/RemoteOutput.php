<?php

namespace Modera\Module\Console\Output;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class RemoteOutput extends Output
{
    /**
     * @var callable
     */
    protected $closure;

    /**
     * @param callable $closure
     * @param bool|int $verbosity
     * @param bool $decorated
     * @param OutputFormatterInterface $formatter
     */
    public function __construct(\Closure $closure, $verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
    {
        $this->closure = $closure;

        parent::__construct($verbosity, $decorated, $formatter);
    }

    /**
     * @param string $message
     * @param bool $newline
     */
    protected function doWrite($message, $newline)
    {
        $closure = $this->closure;
        $closure($message . ($newline ? PHP_EOL : ''));
    }
}

