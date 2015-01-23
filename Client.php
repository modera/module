<?php

namespace Modera\Module;

use Modera\Module\Repository\ModuleRepository;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class Client
{
    /**
     * @var string
     */
    private $workingDir;

    /**
     * @var array
     */
    private $output = array(
        'backspace' => false,
        'success'   => false,
        'working'   => false,
        'params'    => array(),
        'msg'       => ''
    );

    /**
     * @param string $workingDir
     */
    public function __construct($workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * @param $params
     * @return array
     */
    public function apiMethod($params)
    {
        $response = array(
            'success' => false,
            'msg'     => 'Error',
        );

        $action = isset($params['action']) ? $params['action'] : '';
        switch ($action) {
            case 'list':

                $moduleRepository = new ModuleRepository($this->workingDir);

                $data = $moduleRepository->getAvailable();
                $filter = isset($params['filter']) ? $params['filter'] : null;
                if ($filter) {
                    $data = array_filter($data, function ($name) use ($filter) {
                        return stripos($name, $filter) !== false;
                    });
                }
                $total = count($data);

                $result = array();
                if ($total) {
                    $limit = isset($params['limit']) ? $params['limit'] : 5;
                    $page = isset($params['page']) ? $params['page'] : 1;
                    $offset = ($limit * $page) - $limit;

                    foreach (array_slice($data, $offset, $limit) as $name) {
                        $info = $this->getPackageInfo($name);
                        if ($info) {
                            $result[$info['name']] = $info;
                        }
                    }
                }

                $response = array(
                    'success' => true,
                    'data'    => $result,
                    'total'   => $total,
                );

                break;

            case 'info':

                $response = array(
                    'success' => true,
                    'data'    => $this->getPackageInfo($params['name']),
                );

                break;
        }

        return $response;
    }

    /**
     * @param array $params
     * @param string $outputUrl
     * @return array
     */
    public function callMethod($params, $outputUrl)
    {
        $params = $this->prepareParams($params);

        $response = array(
            'success' => false,
            'msg'     => 'Error'
        );

        if ($this->output['working']) {
            $response['msg'] = 'The "' . $this->output['params']['method'] . '" method is already running!';

        } else if (in_array($params['method'], array('require', 'remove'))) {

            $this->output['backspace'] = false;
            $this->output['success']   = false;
            $this->output['working']   = true;
            $this->output['params']    = $params;
            $this->output['msg']       = '';

            $response['success'] = true;
            $response['msg'] = ucfirst($params['method']) . ': ' . $params['name'];

            if ('require' == $params['method']) {
                $response['msg'] .= ':' . $params['version'];
            }
            echo $response['msg'] . "\n";
        }

        if (true === $response['success']) {
            $command = implode(' ', array(
                $this->createCommand($params),
                '--working-dir=' . $this->workingDir,
                '--output-url=' . $outputUrl
            ));
            $outputFile = '/dev/null';
            $response['pid'] = shell_exec(sprintf('%s > %s 2>&1 & echo $!', $command, $outputFile));
        }

        return $response;
    }

    /**
     * @param array $params
     * @return array
     */
    public function getStatus($params)
    {
        $params = $this->prepareParams($params);

        $response = array(
            'success' => false,
            'working' => false,
            'msg'     => '',
        );

        if ($params == $this->output['params']) {
            $msg = $this->output['msg'];
            $response = array(
                'success' => $this->output['success'],
                'working' => $this->output['working'],
                'msg'     => $msg,
            );
        }

        return $response;
    }

    /***
     * @param array $status
     * @return array
     */
    public function updateStatus($status)
    {
        $defaults = array(
            'success' => false,
            'working' => false,
            'msg'     => ''
        );
        $status = array_intersect_key((array) $status, $defaults) + $defaults;

        if (substr_count($status['msg'], "\x08")) {
            if ($this->output['backspace']) {
                $this->output['msg'] = substr($this->output['msg'], 0, -substr_count($status['msg'], "\x08"));
            }
            $this->output['backspace'] = true;
        } else {
            $this->output['msg'] .= $status['msg'];
        }

        $this->output['success'] = $status['success'];
        $this->output['working'] = $status['working'];

        return array(
            'success' => true
        );
    }

    /**
     * @param $params
     * @return array
     */
    private function prepareParams($params)
    {
        $defaults = array(
            'method'  => '',
            'name'    => '',
            'version' => '',
            'hash'    => '',
        );

        return array_intersect_key((array) $params, $defaults) + $defaults;
    }

    /**
     * @return string
     */
    private function getConsolePath()
    {
        $trace = array_reverse(debug_backtrace());
        return $trace[0]['file'];
    }

    /**
     * @param array $params
     * @return string
     */
    private function createCommand($params)
    {
        $command = $this->getConsolePath() . ' modera:module:package ' . $params['name'];
        if ('require' == $params['method']) {
            $command .= ' ' . $params['version'];
        }

        return $command . ' --method=' . $params['method'];
    }

    /**
     * @param $name
     * @return array|null
     */
    private function getPackageInfo($name)
    {
        $moduleRepository = new ModuleRepository($this->workingDir);

        $package = $moduleRepository->getPackage($name);
        if (!$package) {
            return null;
        }

        $result = array(
            'name'        => $package->getName(),
            'description' => $package->getDescription(),
            'installed'   => null,
            'versions'    => array(),
        );

        $installed = $moduleRepository->getInstalledByName($package->getName());
        if ($installed) {

            $branchAlias = '';
            $version = $installed->getPrettyVersion();
            if ($installed instanceof \Composer\Package\AliasPackage) {
                $branchAlias = $version;
                $version = $installed->getAliasOf()->getPrettyVersion();
            }

            $require = array();
            foreach ($installed->getRequires() as $link) {
                /* @var \Composer\Package\Link $link */
                $require[$link->getTarget()] = $link->getConstraint()->getPrettyString();
            }

            $result['installed'] = array(
                'version'      => $version,
                'branchAlias'  => $branchAlias,
                'reference'    => $installed->getSourceReference(),
                'isDependency' => $moduleRepository->isInstalledAsDependency($installed->getName()),
                'require'      => $require,
            );
        }

        $versions = $package->getVersions();
        foreach($versions as $p) {
            /* @var \Packagist\Api\Result\Package\Version $p */
            $createdAt = new \DateTime($p->getTime());

            $version = $p->getVersion();
            $result['versions'][$version] = array(
                'version'     => $version,
                'branchAlias' => $moduleRepository->getVersionAlias($p),
                'reference'   => $p->getSource()->getReference(),
                'createdAt'   => $createdAt->format(\DateTime::RFC1123),
                'require'     => $p->getRequire(),
            );
        }

        return $result;
    }
}