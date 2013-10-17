<?php

namespace Modera\Module\Service;

use Composer\Factory;
use Composer\Composer;
use Composer\Installer;
use Composer\Json\JsonFile;
use Composer\IO\NullIO;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Json\JsonManipulator;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Modera\Module\Adapter\ComposerAdapter;

class ComposerService
{
    /**
     * @param Composer $composer
     * @return array
     */
    public static function getOptions(Composer $composer)
    {
        $extra = $composer->getPackage()->getExtra();
        $options = array_merge(array(

            'type'             => 'modera-module',
            'file'             => 'app/modules/bundles.php',
            'packagist-url'    => 'https://packages.modera.org',
            'register-bundles' => array(),

        ), isset($extra['modera-module']) ? $extra['modera-module'] : array());

        $packages = static::getInstalledPackages($composer, $options['type']);
        foreach ($packages as $package) {
            $extra = $package->getExtra();
            if (isset($extra['modera-module']) && isset($extra['modera-module']['register-bundle'])) {
                $options['register-bundles'][] = $extra['modera-module']['register-bundle'];
            }
        }

        return $options;
    }

    /**
     * @param Composer $composer
     * @param null|string $type
     * @return CompletePackage[]
     */
    public static function getInstalledPackages(Composer $composer, $type = null)
    {
        $packages = array();
        $repo = $composer->getRepositoryManager()->getLocalRepository();
        foreach ($repo->getPackages() as $package) {
            if ($type && strpos($package->getType(), $type) === false) {
                continue;
            }
            if (!isset($packages[$package->getName()])
                || !is_object($packages[$package->getName()])
                || version_compare($packages[$package->getName()]->getVersion(), $package->getVersion(), '<')
            ) {
                $packages[$package->getName()] = $package;
            }
        }

        return $packages;
    }

    /**
     * @param $name
     * @return null|CompletePackage
     */
    public static function getInstalledPackageByName(Composer $composer, $name)
    {
        $repo = $composer->getRepositoryManager()->getLocalRepository();
        $packages = $repo->findPackages($name, null);
        foreach ($packages as $package) {
            $installer = $composer->getInstallationManager()->getInstaller($package->getType());
            if ($installer->isInstalled($repo, $package)) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public static function formatPackageVersion(PackageInterface $package)
    {
        $versionParser = new VersionParser;
        return $versionParser->formatVersion($package);
    }

    /**
     * @param string $name
     * @param string $version
     * @param IOInterface $io
     * @return bool
     */
    public static function requirePackage($name, $version, IOInterface $io = null)
    {
        $installed = true;

        $file = Factory::getComposerFile();
        $jsonFile = new JsonFile($file);
        $composerData = $jsonFile->read();
        $composerBackup = file_get_contents($jsonFile->getPath());

        $requireKey = 'require';
        $baseRequirements = array_key_exists($requireKey, $composerData) ? $composerData[$requireKey] : array();
        $requirements = array($name => $version);

        // validate requirements format
        $versionParser = new VersionParser();
        foreach ($requirements as $constraint) {
            $versionParser->parseConstraints($constraint);
        }

        if (!static::updateFileCleanly($jsonFile, $baseRequirements, $requirements, $requireKey)) {
            foreach ($requirements as $package => $version) {
                $baseRequirements[$package] = $version;
            }

            $composerData[$requireKey] = $baseRequirements;
            $jsonFile->write($composerData);
        }

        // Update packages
        if (null === $io) {
            $io = new NullIO();
        }
        $composer = ComposerAdapter::createComposer($io);
        $composer->getDownloadManager()->setOutputProgress(true);

        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'require', new ArrayInput(array()), new NullOutput());
        $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

        $install = Installer::create($io, $composer);
        $install
            ->setVerbose(false)
            ->setPreferSource(false)
            ->setPreferDist(false)
            ->setDevMode(true)
            ->setUpdate(true)
            ->setUpdateWhitelist(array_keys($requirements));
        ;

        ob_start();
        if (!$install->run()) {
            $io->write("\n" . '<error>Installation failed, reverting '.$file.' to its original content.</error>');
            file_put_contents($jsonFile->getPath(), $composerBackup);
            $installed = false;
        }
        $res = ob_get_contents();
        ob_end_clean();

        $io->write($res);

        return $installed;
    }

    /**
     * @param string $name
     * @param IOInterface $io
     * @return bool
     */
    public static function removePackage($name, IOInterface $io = null)
    {
        $removed = true;

        $file = Factory::getComposerFile();
        $json = new JsonFile($file);
        $composer = $json->read();

        $requireKey = 'require';
        $baseRequirements = array_key_exists($requireKey, $composer) ? $composer[$requireKey] : array();

        if (isset($baseRequirements[$name])) {

            // Update composer.json
            unset($baseRequirements[$name]);
            $composer[$requireKey] = $baseRequirements;
            $json->write($composer);

            // Update composer.lock
            $lockFile = "json" === pathinfo($file, PATHINFO_EXTENSION)
                ? substr($file, 0, -4).'lock'
                : $file . '.lock';

            $lockJson = new JsonFile($lockFile);
            $locker = $lockJson->read();
            $packageKey = null;
            foreach ($locker['packages'] as $key => $package) {
                if ($name == $package['name']) {
                    $packageKey = $key;
                    break;
                }
            }
            if (null !== $packageKey) {
                unset($locker['packages'][$packageKey]);
                $locker['packages'] = array_values($locker['packages']);
            }
            $locker['hash'] = md5_file($file);
            $lockJson->write($locker);

            // Update packages
            if (null === $io) {
                $io = new NullIO();
            }
            $composer = ComposerAdapter::createComposer($io);
            $composer->getDownloadManager()->setOutputProgress(true);

            $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'install', new ArrayInput(array()), new NullOutput());
            $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

            $install = Installer::create($io, $composer);
            $install
                ->setVerbose(true)
                ->setPreferSource(false)
                ->setPreferDist(false)
                ->setDevMode(true)
                ->setRunScripts(true)
                ->setOptimizeAutoloader(false)
            ;

            ob_start();
            if (!$install->run()) {
                $removed = false;
            }
            $res = ob_get_contents();
            ob_end_clean();

            $io->write($res);
        }

        return $removed;
    }

    /**
     * @param $json
     * @param array $base
     * @param array $new
     * @param $requireKey
     * @return bool
     */
    private static function updateFileCleanly($json, array $base, array $new, $requireKey)
    {
        $contents = file_get_contents($json->getPath());
        $manipulator = new JsonManipulator($contents);
        foreach ($new as $package => $constraint) {
            if (!$manipulator->addLink($requireKey, $package, $constraint)) {
                return false;
            }
        }

        file_put_contents($json->getPath(), $manipulator->getContents());

        return true;
    }
}
