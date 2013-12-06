<?php

namespace Modera\Module;

use Modera\Module\Repository\ModuleRepository;
use Modera\Module\Console\Output\RemoteOutput;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class Server
{
    /**
     * @var ModuleRepository
     */
    private $moduleRepository;

    /**
     * @var int
     */
    private $serverId;

    /**
     * @var int
     */
    private $port;

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
     * @param ModuleRepository $moduleRepository
     */
    public function __construct(ModuleRepository $moduleRepository, $port)
    {
        $this->port = $port;
        $this->serverId = getmypid();
        $this->moduleRepository = $moduleRepository;
    }

    /**
     * @param $params
     * @return array
     */
    private function params($params)
    {
        return array_merge(
            array(
                'method'  => '',
                'name'    => '',
                'version' => '',
                'hash'    => '',
            ), (array) $params
        );
    }

    /**
     * @param $serverId
     * @param $params
     */
    public function updateOutput($serverId, $output)
    {
        $output = (array) $output;
        if ($this->serverId == $serverId) {

            if (substr_count($output['msg'], "\x08")) {
                if ($this->output['backspace']) {
                    $this->output['msg'] = substr($this->output['msg'], 0, -substr_count($output['msg'], "\x08"));
                }
                $this->output['backspace'] = true;
            } else {
                $this->output['msg'] .= $output['msg'];
            }

            $this->output['success'] = $output['success'];
            $this->output['working'] = $output['working'];
        }
    }

    /**
     * @param $params
     * @param $cb
     */
    public function status($params, $cb)
    {
        $response = array(
            'success' => false,
            'working' => false,
            'msg'     => '',
        );

        $params = $this->params($params);
        if ($params == $this->output['params']) {
            $msg = $this->output['msg'];
            if ('remove' == $params['method']) {
                $msg = str_replace('Installing dependencies', 'Removing dependencies', $msg);
            }
            $response = array(
                'success' => $this->output['success'],
                'working' => $this->output['working'],
                'msg'     => $msg,
            );
        }

        if (is_callable($cb)) {
            $cb($response);
        }
    }

    /**
     * @param $params
     * @param $cb
     */
    public function call($params, $cb)
    {
        $params = $this->params($params);
        $response = array('success' => false, 'msg' => 'Error');

        if ($this->output['working']) {

            $response['msg'] = 'The "' . $this->output['params']['method'] . '" method is already running!';

        } else {

            if (in_array($params['method'], array('require', 'remove'))) {
                $this->output['backspace'] = false;
                $this->output['success'] = false;
                $this->output['working'] = true;
                $this->output['params'] = $params;
                $this->output['msg'] = '';

                $response['success'] = true;
                $response['msg'] = ucfirst($params['method']) . ': ' . $params['name'];
                if ('require' == $params['method']) {
                    $response['msg'] .= ':' . $params['version'];
                }
                echo $response['msg'] . "\n";
            }

        }

        if (is_callable($cb)) {
            $cb($response);
        }

        if (true === $response['success']) {

            // make fork
            if (function_exists('pcntl_fork')) {
                if (($pid = pcntl_fork()) == -1) { die('Fork failed'); } else if ($pid) { return; }
            }

            // fork process
            $self = $this;
            $output = new RemoteOutput(function($message) use ($self) {
                $data = array(
                    'success' => $self->output['success'],
                    'working' => $self->output['working'],
                    'msg'     => $message,
                );

                if (function_exists('pcntl_fork')) {
                    $self->moduleRepository->connect($self->port, function($remote, $connection) use ($data, $self) {
                        $remote->updateOutput($self->serverId, $data);
                        $connection->end();
                    });
                } else {
                    $self->updateOutput($self->serverId, $data);
                }
            });

            $isSuccess = false;
            if ('require' == $params['method']) {
                $package = $this->moduleRepository->getPackage($params['name']);
                if ($package) {
                    $isSuccess = $this->moduleRepository->requirePackage($params['name'], $params['version'], $output);
                } else {
                    $output->writeln('Package not found.');
                }
            }
            else if ('remove' == $params['method']) {
                $isSuccess = $this->moduleRepository->removePackage($params['name'], $output);
            }

            $this->output['success'] = $isSuccess;
            $this->output['working'] = false;
            $message = $isSuccess ? 'Finished: SUCCESS' : 'Finished: FAILURE';
            $output->writeln($message);
            echo $message . "\n\n";

            if (function_exists('pcntl_fork')) {
                exit(0);
            }
        }
    }
}