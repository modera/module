<?php

namespace Modera\Module\Adapter;

use Composer;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class ComposerAdapter
{
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public static function createConsoleIO(InputInterface $input = null, OutputInterface $output = null)
    {
        if ($input == null) {
            $input = new ArrayInput(array());
        }

        if ($output == null) {
            $output = new NullOutput();
        }

        return new Composer\IO\ConsoleIO($input, $output, new HelperSet());
    }

    /**
     * @param IOInterface $io
     * @param mixed $config
     * @param bool $disablePlugins
     * @return Composer\Composer
     * @throws \Exception|\InvalidArgumentException
     */
    public static function createComposer(IOInterface $io = null, $config = null, $disablePlugins = false)
    {
        try {

            if (null === $io) {
                $io = static::createConsoleIO();
            }

            return Composer\Factory::create($io, $config, $disablePlugins);

        } catch (\InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * @param string|null $pathToComposer
     * @throws \RuntimeException
     */
    public static function checkComposer($pathToComposer = null)
    {
        if (!class_exists('Composer\Factory')) {
            if (false === $pathToComposer = self::whichComposer($pathToComposer)) {
                throw new \RuntimeException("Could not find composer.phar");
            }

            \Phar::loadPhar($pathToComposer, 'composer.phar');
            $loader = new UniversalClassLoader();
            $namespaces = include('phar://composer.phar/vendor/composer/autoload_namespaces.php');
            $loader->registerNamespaces(array_merge(
                array(
                    'Composer' => 'phar://composer.phar/src/'
                ),
                $namespaces
            ));
            $loader->register(true);
        }
    }

    /**
     * @param $pathToComposer
     * @return bool|string
     */
    protected static function whichComposer($pathToComposer)
    {
        if (file_exists($pathToComposer)) {
            return $pathToComposer;
        }

        $composerFile = 'composer.phar';
        for ($i = 0; $i < 3; $i++) {
            if ($i > 0) {
                $composerFile = '../' . $composerFile;
            }

            if (file_exists($composerFile)) {
                return $composerFile;
            }
        }

        $composerExecs = array('composer.phar', 'composer');

        $isUnix = DIRECTORY_SEPARATOR == '/' ? true : false;

        foreach ($composerExecs as $composerExec) {
            $pathToComposer = exec(
                sprintf($isUnix ? "which %s" : "for %%i in (%s) do @echo.%%~\$PATH:i", $composerExec)
            );

            if (file_exists($pathToComposer)) {
                return $pathToComposer;
            }
        }

        return false;
    }
}
