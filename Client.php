<?php

namespace Modera\Module;

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
}