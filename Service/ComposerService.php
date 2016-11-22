<?php

namespace Modera\Module\Service;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\Module\Console\Application;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
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
            'packagist-url' => 'https://packagist.org',

        ), isset($extra['modera-module']) ? $extra['modera-module'] : array());

        return $options;
    }

    /**
     * @param array $extra
     * @param string $type
     * @param string $packageDir
     * @return array
     */
    protected static function combineRegisterBundles(array $extra, $type, $packageDir)
    {
        $bundles = array();
        if (isset($extra[$type])) {
            if (isset($extra[$type]['register-bundle'])) {
                if (is_array($extra[$type]['register-bundle'])) {
                    $bundles = array_merge($bundles, $extra[$type]['register-bundle']);
                } else {
                    $bundles[] = $extra[$type]['register-bundle'];
                }
            }

            if (isset($extra[$type]['include'])) {
                $patterns = array();
                foreach ($extra[$type]['include'] as $path) {
                    $patterns[] = $packageDir . DIRECTORY_SEPARATOR . $path;
                }

                $files = array_map(
                    function ($files, $pattern) {
                        return $files;
                    },
                    array_map('glob', $patterns),
                    $patterns
                );

                foreach (array_reduce($files, 'array_merge', array()) as $path) {
                    $file = new JsonFile($path);
                    $json = $file->read();
                    if (isset($json['extra'])) {
                        $bundles = array_merge($bundles, static::combineRegisterBundles($json['extra'], $type, dirname($path)));
                    }
                }
            }
        }

        return $bundles;
    }

    /**
     * @param Composer $composer
     * @param string|null $type
     * @return array
     */
    public static function getRegisterBundles(Composer $composer, $type = null)
    {
        $bundles = array();
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $packages = static::getInstalledPackages($composer, $type);
        $options = static::getOptions($composer);

        foreach ($packages as $package) {
            $bundles = array_merge(
                $bundles,
                static::combineRegisterBundles(
                    $package->getExtra(), $options['type'], $vendorDir . DIRECTORY_SEPARATOR . $package->getName()
                )
            );
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
     * @param Composer $composer
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
     * @param Composer $composer
     * @param $name
     * @return bool|null
     */
    public static function isInstalledAsDependency(Composer $composer, $name)
    {
        $package = static::getInstalledPackageByName($composer, $name);
        if ($package) {
            if (in_array($name, array_keys($composer->getPackage()->getRequires()))) {
                return false;
            }

            return true;
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
            '--update-with-dependencies' => true,
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
            'packages'      => array($name),
            '--working-dir' => $workingDir,
            '--update-with-dependencies' => true,
        ));
        $input->setInteractive(false);

        $result = $app->run($input, $output);
        if (0 == $result) {
            return true;
        }

        return false;
    }
}
