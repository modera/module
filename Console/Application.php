<?php

namespace Modera\Module\Console;

use Composer\Console\Application as BaseApplication;
use Modera\Module\Command;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class Application extends BaseApplication
{
    private static $logo = '
    __  ___          __
   /  |/  /___  ____/ /__  _________ _
  / /|_/ / __ \/ __  / _ \/ ___/ __ `/
 / /  / / /_/ / /_/ /  __/ /  / /_/ /
/_/  /_/\____/\__,_/\___/_/   \__,_/

';

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\ClientCommand();
        $commands[] = new Command\PackageCommand();

        return $commands;
    }

    public function getHelp()
    {
        return self::$logo . \Symfony\Component\Console\Application::getHelp();
    }
}
