<?php

namespace Modera\Module\Console;

use Composer\Console\Application as BaseApplication;
use Modera\Module\Command;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
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
        $commands[] = new Command\RemoveCommand();
        $commands[] = new Command\ServerCommand();
        $commands[] = new Command\ClientCommand();

        return $commands;
    }

    public function getHelp()
    {
        return self::$logo . \Symfony\Component\Console\Application::getHelp();
    }
}
