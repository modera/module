<?php

namespace Modera\Module\Composer;

use Composer\Composer;
use Composer\Script\CommandEvent;
use Modera\Module\Service\ComposerService;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class ScriptHandler
{
    /**
     * @param CommandEvent $event
     */
    public static function registerBundles(CommandEvent $event)
    {
        static::createRegisterBundlesFile($event->getComposer());
    }

    /**
     * @param Composer $composer
     */
    protected static function createRegisterBundlesFile(Composer $composer)
    {
        $options = ComposerService::getOptions($composer);

        $file = $options['file'];
        $bundles = $options['register-bundles'];

        $data = array('<?php return array(');
        foreach ($bundles as $bundleClassName) {
            $data[] = '    new ' . $bundleClassName . '(),';
        }
        $data[] = ');';

        if (file_exists($file)) {
            file_put_contents($file, implode("\n", $data) . "\n");
        }
    }
}
