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
    private $logo = '
   ______                       __          _______            __
  / ____/___  ____  ____  ___  / /  ___    /__  __/___   ___  / /
 / /   / __ \/ __ \/ ___/ __ \/ /  / _ \     / / / __ \/ __ \/ /
/ /___/ /_/ / / / (__ )/ /_/ / /__/  __/    / / / /_/ / /_/ / /__
\____/\____/_/ /_/____/\____/____/\___/    /_/  \____/\____/____/
';

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\RemoveCommand();

        return $commands;
    }

    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }
}