<?php

namespace Modera\Module;

use Modera\Module\Repository\ModuleRepository;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class Server
{
    private $moduleRepository;

    /**
     * @param ModuleRepository $moduleRepository
     */
    public function __construct(ModuleRepository $moduleRepository)
    {
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
     * @return string
     */
    private function formatOutput()
    {
        fseek($this->moduleRepository->getOutput()->getStream(), 0);

        $output = stream_get_contents($this->moduleRepository->getOutput()->getStream());

        $output = preg_replace_callback("{(?<=^|\n|\x08)(.+?)(\x08+)}", function ($matches) {
            $pre = strip_tags($matches[1]);

            if (strlen($pre) === strlen($matches[2])) {
                return '';
            }

            // TODO reverse parse the string, skipping span tags and \033\[([0-9;]+)m(.*?)\033\[0m style blobs
            return rtrim($matches[1])."\n";
        }, $output);

        return $output;
    }

    /**
     * @param $params
     * @param $cb
     */
    public function call($params, $cb)
    {
        $params = $this->params($params);
        $response = array('success' => false, 'error' => 'Error');

        if (in_array($params['method'], array('require', 'remove'))) {
            $this->moduleRepository->setOutput(new StreamOutput(fopen('php://memory', 'rw')));

            if ('require' == $params['method']) {
                $package = $this->moduleRepository->getPackage($params['name']);
                if ($package) {
                    $this->moduleRepository->requirePackage($params['name'], $params['version']);
                    $response = array('success' => true, 'data' => $this->formatOutput());
                } else {
                    $response['error'] = 'Package not found.';
                }
            }
            else if ('remove' == $params['method']) {
                $this->moduleRepository->removePackage($params['name']);
                $response = array('success' => true, 'data' => $this->formatOutput());
            }
        }

        if (is_callable($cb)) {
            $cb($response);
        }
    }
}