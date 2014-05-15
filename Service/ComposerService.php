<?php

namespace Modera\Module\Service;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\Module\Console\Application;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
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

            'type'          => 'modera-module',
            'file'          => 'app/modules/bundles.php',
            'packagist-url' => 'https://packages.modera.org',

        ), isset($extra['modera-module']) ? $extra['modera-module'] : array());

        return $options;
    }

    /**
     * @param Composer $composer
     * @param string|null $type
     * @return array
     */
    public function getRegisterBundles(Composer $composer, $type = null)
    {
        $bundles = array();
        $packages = static::getInstalledPackages($composer, $type);
        foreach ($packages as $package) {
            $extra = $package->getExtra();
            if (isset($extra['modera-module']) && isset($extra['modera-module']['register-bundle'])) {
                $bundles[] = $extra['modera-module']['register-bundle'];
            }
        }

        return $bundles;
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
     * @param $workingDir
     * @param $name
     * @param $version
     * @param OutputInterface $output
     * @return bool
     */
    public static function requirePackage($workingDir, $name, $version, OutputInterface $output = null)
    {
        if (!$output) {
            $output = new NullOutput();
        }

        $app = new Application();
        $app->setAutoExit(false);

        $input = new ArrayInput(array(
            'command'       => 'require',
            'packages'      => array($name . ':' . $version),
            '--working-dir' => $workingDir,
        ));
        $input->setInteractive(false);

        $result = $app->run($input, $output);
        if (0 == $result) {
            return true;
        }

        return false;
    }

    /**
     * @param $workingDir
     * @param $name
     * @param OutputInterface $output
     * @return bool
     */
    public static function removePackage($workingDir, $name, OutputInterface $output = null)
    {
        if (!$output) {
            $output = new NullOutput();
        }

        $app = new Application();
        $app->setAutoExit(false);

        $input = new ArrayInput(array(
            'command'       => 'remove',
            'package'       => $name,
            '--working-dir' => $workingDir,
        ));
        $input->setInteractive(false);

        $result = $app->run($input, $output);
        if (0 == $result) {
            return true;
        }

        return false;
    }
}
