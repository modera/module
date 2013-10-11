<?php

namespace Modera\Module\Repository;

use Buzz\Browser;
use Buzz\Client\Curl;
use Composer\Factory;
use Composer\Composer;
use Composer\Installer;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Json\JsonManipulator;
use Composer\Package\Version\VersionParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Modera\Module\Adapter\ComposerAdapter;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.net>
 */
class ModuleRepository
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var string
     */
    private $moduleType;

    /**
     * @var null|string
     */
    private $workingDir = null;

    /**
     * @var null|string
     */
    private $defaultWorkingDir = null;

    /**
     * @var null|string
     */
    private $pathToComposer = null;

    /**
     * @var string
     */
    private $packagistUrl;

    /**
     * @param ContainerInterface $container
     * @param null $moduleType
     * @param null $pathToComposer
     * @param null $workingDir
     */
    public function __construct(ContainerInterface $container, $moduleType = null, $packagistUrl = null, $pathToComposer = null, $workingDir = null)
    {
        $this->container = $container;
        $this->moduleType = $moduleType ?: 'modera-module';
        $this->packagistUrl = $packagistUrl ?: 'https://packages.modera.org';
        $this->pathToComposer = $pathToComposer;

        $this->defaultWorkingDir = getcwd();
        if (!$workingDir) {
            $workingDir = dirname($this->container->get('kernel')->getRootdir());
        }
        $this->workingDir = $workingDir;

        putenv("COMPOSER_HOME=$workingDir/app/cache/.composer");

        ComposerAdapter::checkComposer($this->pathToComposer);

        $this->io = new \Composer\IO\BufferIO();
    }

    /**
     * @return Composer
     */
    private function createComposer()
    {
        $this->composer = ComposerAdapter::createComposer($this->io);

        return $this->composer;
    }

    /**
     * @return Composer
     */
    private function getComposer()
    {
        if (!$this->composer) {
            $this->composer = $this->createComposer();
        }

        return $this->composer;
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->io->getOutput();
    }

    /**
     * @return array
     */
    public function getInstalled()
    {
        chdir($this->workingDir);

        $result = array();
        $installedRepo = $this->getComposer()->getRepositoryManager()->getLocalRepository();

        $packages = array();
        foreach ($installedRepo->getPackages() as $package) {
            if (strpos($package->getType(), $this->moduleType) === false) {
                continue;
            }
            if (!isset($packages[$package->getName()])
                || !is_object($packages[$package->getName()])
                || version_compare($packages[$package->getName()]->getVersion(), $package->getVersion(), '<')
            ) {
                $packages[$package->getName()] = $package;
            }
        }

        $versionParser = new VersionParser;
        foreach ($packages as $key => $package) {
            list($version, $reference) = array_merge(
                explode(' ', $versionParser->formatVersion($package)),
                array(null)
            );
            $result[$key] = array(
                'name'      => $package->getPrettyName(),
                'version'   => $version,
                'reference' => $reference,
            );
        }

        chdir($this->defaultWorkingDir);

        return array(
            'results' => $result,
            'total'   => count($result),
        );
    }

    /**
     * @param int $page
     * @return array
     */
    public function getAvailable($page = 1)
    {
        $client = new Curl;
        $browser = new Browser($client);
        $response = $browser->get($this->packagistUrl . '/search.json?type=' . $this->moduleType . '&page=' . $page);
        $data = json_decode($response->getContent(), true);

        return array(
            'results' => $data['results'],
            'total'   => $data['total'],
        );
    }

    /**
     * @param $name
     * @param null $version
     * @return array|null
     */
    public function getPackageInfo($name, $version = null)
    {
        $client = new Curl;
        $browser = new Browser($client);
        $response = $browser->get($this->packagistUrl . '/p/' . $name . '.json');
        $info = json_decode($response->getContent(), true);
        if (isset($info['status']) && $info['status'] == 'error') {
            return null;
        }
        if (!isset($info['packages'][$name])) {
            return null;
        }
        $packages = $info['packages'][$name];

        if ($version && isset($packages[$version])) {
            $package = $packages[$version];
        } else {
            $package = current($packages);
        }

        return $package;
    }

    /**
     * @param $name
     * @param string $version
     * @throws \InvalidArgumentException
     */
    public function install($name, $version = 'dev-master')
    {
        chdir($this->workingDir);

        $file = Factory::getComposerFile();
        $json = new JsonFile($file);
        $composer = $json->read();
        $composerBackup = file_get_contents($json->getPath());

        $packages = array($name . ':' . $version);

        $result = array();
        $requires = $this->normalizeRequirements($packages);
        foreach ($requires as $key => $requirement) {
            if (!isset($requirement['version'])) {
                throw new \InvalidArgumentException('The requirement ' . $requirement['name'] . ' must contain a version constraint');
            }
            $result[] = $requirement['name'] . ' ' . $requirement['version'];
        }
        $requirements = $result;

        $requireKey = 'require';
        $baseRequirements = array_key_exists($requireKey, $composer) ? $composer[$requireKey] : array();
        $requirements = $this->formatRequirements($requirements);

        // validate requirements format
        $versionParser = new VersionParser();
        foreach ($requirements as $constraint) {
            $versionParser->parseConstraints($constraint);
        }

        if (!$this->updateFileCleanly($json, $baseRequirements, $requirements, $requireKey)) {
            foreach ($requirements as $package => $version) {
                $baseRequirements[$package] = $version;
            }

            $composer[$requireKey] = $baseRequirements;
            $json->write($composer);
        }

        // Update packages
        $composer = $this->createComposer();
        $composer->getDownloadManager()->setOutputProgress(true);

        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'require', new ArrayInput(array()), new NullOutput());
        $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

        $install = Installer::create($this->io, $composer);

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
            $this->io->write("\n" . '<error>Installation failed, reverting '.$file.' to its original content.</error>');
            file_put_contents($json->getPath(), $composerBackup);
        }
        $res = ob_get_contents();
        ob_end_clean();

        $this->io->write($res);

        chdir($this->defaultWorkingDir);
    }

    /**
     * @param $name
     * @param $version
     */
    public function uninstall($name, $version)
    {

    }

    /**
     * @param $name
     * @param $version
     */
    public function update($name, $version)
    {

    }

    /**
     * @param array $requirements
     * @return \array[]
     */
    private function normalizeRequirements(array $requirements)
    {
        $parser = new VersionParser();

        return $parser->parseNameVersionPairs($requirements);
    }

    /**
     * @param array $requirements
     * @return array
     */
    private function formatRequirements(array $requirements)
    {
        $requires = array();
        $requirements = $this->normalizeRequirements($requirements);
        foreach ($requirements as $requirement) {
            $requires[$requirement['name']] = $requirement['version'];
        }

        return $requires;
    }

    /**
     * @param $json
     * @param array $base
     * @param array $new
     * @param $requireKey
     * @return bool
     */
    private function updateFileCleanly($json, array $base, array $new, $requireKey)
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