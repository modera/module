<?php

namespace Modera\Module\Repository;

use Packagist\Api\Client;
use Packagist\Api\Result\Package;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Modera\Module\Adapter\ComposerAdapter;
use Modera\Module\Service\ComposerService;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.net>
 */
class ModuleRepository
{
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var Client
     */
    private $packagist;

    /**
     * @var string
     */
    private $workingDir;

    /**
     * @var string
     */
    private $defaultWorkingDir;

    /**
     * @var string
     */
    private $pathToComposer;

    /**
     * @var array
     */
    private $options = array();

    /**
     * @param string $workingDir
     * @param string $pathToComposer
     */
    public function __construct($workingDir = null, $pathToComposer = null)
    {
        $this->defaultWorkingDir = getcwd();
        $this->workingDir        = $workingDir ?: $this->defaultWorkingDir;
        $this->pathToComposer    = $pathToComposer;

        putenv("COMPOSER_HOME=" . $this->workingDir . "/app/cache/.composer");

        chdir($this->workingDir);

        ComposerAdapter::checkComposer($this->pathToComposer);
        $this->io = new \Composer\IO\BufferIO();
        $this->options = ComposerService::getOptions($this->getComposer());

        chdir($this->defaultWorkingDir);
    }

    /**
     * @return Composer
     */
    private function getComposer()
    {
        if (!$this->composer) {
            $this->composer = ComposerAdapter::createComposer($this->io);
        }

        return $this->composer;
    }

    /**
     * @return Client
     */
    private function getPackagist()
    {
        if (!$this->packagist) {
            $this->packagist = new Client();
            $this->packagist->setPackagistUrl($this->options['packagist-url']);
        }

        return $this->packagist;
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->io->getOutput();
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public function formatVersion(PackageInterface $package)
    {
        return ComposerService::formatPackageVersion($package);
    }

    /**
     * @param $name
     * @return null|CompletePackage
     */
    public function getInstalledByName($name)
    {
        chdir($this->workingDir);
        $package = ComposerService::getInstalledPackageByName($this->getComposer(), $name);
        chdir($this->defaultWorkingDir);

        return $package;
    }

    /**
     * @return CompletePackage[]
     */
    public function getInstalled()
    {
        chdir($this->workingDir);
        $composer = $this->getComposer();
        $packages = ComposerService::getInstalledPackages($composer, $this->options['type']);
        chdir($this->defaultWorkingDir);

        return $packages;
    }

    /**
     * @return array
     */
    public function getAvailable()
    {
        $client = $this->getPackagist();
        $data = $client->all(array('type' => $this->options['type']));

        return $data;
    }

    /**
     * @param $name
     * @return null|Package
     */
    public function getPackage($name)
    {
        $client = $this->getPackagist();
        try {
            return $client->get($name);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @param string $version
     * @return bool
     */
    public function requirePackage($name, $version = 'dev-master')
    {
        chdir($this->workingDir);
        $installed = ComposerService::requirePackage($name, $version, $this->io);
        chdir($this->defaultWorkingDir);

        return $installed;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function removePackage($name)
    {
        chdir($this->workingDir);
        $removed = ComposerService::removePackage($name, $this->io);
        chdir($this->defaultWorkingDir);

        return $removed;
    }
}