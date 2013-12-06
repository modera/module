<?php

namespace Modera\Module\Repository;

use Packagist\Api\Client;
use Packagist\Api\Result\Package;
use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Modera\Module\Adapter\ComposerAdapter;
use Modera\Module\Service\ComposerService;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class ModuleRepository
{
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
        $this->options = ComposerService::getOptions($this->getComposer());

        chdir($this->defaultWorkingDir);
    }

    /**
     * @return Composer
     */
    private function getComposer()
    {
        if (!$this->composer) {
            $this->composer = ComposerAdapter::createComposer();
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
     * @param $port
     * @param callable $cb
     */
    public function connect($port, \Closure $cb)
    {
        $loop = new \React\EventLoop\StreamSelectLoop();
        $dnode = new \DNode\DNode($loop);
        $dnode->on('error', function($e) {
            throw $e;
        });
        $dnode->connect($port, $cb);
        $loop->run();
    }

    /**
     * @param $port
     */
    public function listen($port)
    {
        $loop = new \React\EventLoop\StreamSelectLoop();
        $server = new \DNode\DNode($loop, new \Modera\Module\Server($this, $port));
        $server->listen($port);
        $loop->run();
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
        $packages = ComposerService::getInstalledPackages($this->getComposer(), $this->options['type']);
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
    public function requirePackage($name, $version = 'dev-master', OutputInterface $output = null)
    {
        return ComposerService::requirePackage($this->workingDir, $name, $version, $output);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function removePackage($name, OutputInterface $output = null)
    {
        return ComposerService::removePackage($this->workingDir, $name, $output);
    }
}