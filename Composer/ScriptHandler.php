<?php

namespace Modera\Module\Composer;

use Composer\Composer;
use Composer\Script\Event;
use Composer\Script\CommandEvent;
use Composer\Script\PackageEvent;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Modera\Module\Service\ComposerService;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class ScriptHandler extends AbstractScriptHandler
{
    /**
     * @var array
     */
    private static $scripts = array();

    /**
     * @param Event $event
     */
    public static function eventDispatcher(Event $event)
    {
        if ($event instanceof PackageEvent) {
            $operation = $event->getOperation();
            if ($operation instanceof UpdateOperation) {
                $package = $operation->getTargetPackage();
            } else {
                $package = $operation->getPackage();
            }

            $options = ComposerService::getOptions($event->getComposer());
            if ($package->getType() != $options['type']) {
                return;
            }

            $extra = $package->getExtra();

            if (is_array($extra) && isset($extra[$options['type']])) {
                if (isset($extra[$options['type']]['scripts'])) {
                    if (isset($extra[$options['type']]['scripts'][$event->getName()])) {

                        $scripts = $extra[$options['type']]['scripts'][$event->getName()];
                        if (!is_array($scripts)) {
                            $scripts = array($scripts);
                        }
                        foreach ($scripts as $script) {
                            static::$scripts[$event->getName()][] = array(
                                'script' => $script,
                                'event'  => $event,
                            );
                        }

                    }
                }
            }
        } else {
            foreach (static::$scripts as $eventName => $scripts) {

                echo '>>> '. $event->getName() . ':' . $eventName . PHP_EOL;

                foreach ($scripts as $data) {
                    if (is_callable($data['script'])) {

                        echo '>>> '. $data['script'] . PHP_EOL;

                        $className = substr($data['script'], 0, strpos($data['script'], '::'));
                        $methodName = substr($data['script'], strpos($data['script'], '::') + 2);
                        $className::$methodName($data['event']);
                    }
                }
            }
        }
    }

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

    /**
     * Clears the Symfony cache.
     *
     * @param $event CommandEvent A instance
     */
    public static function clearCache(CommandEvent $event)
    {
        $options = static::getOptions($event);
        $appDir = $options['symfony-app-dir'];

        if (!is_dir($appDir)) {
            echo 'The symfony-app-dir ('.$appDir.') specified in composer.json was not found in '.getcwd().', can not clear the cache.'.PHP_EOL;

            return;
        }

        static::executeCommand($event, $appDir, 'cache:clear --env=prod --no-warmup', $options['process-timeout']);
    }
}
