<?php

namespace Modera\Module\Command;

use React\Http\Server as HttpServer;
use React\Http\Request as HttpRequest;
use React\Http\Response as HttpResponse;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory as EventLoopFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\Module\Client as ModuleClient;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ClientCommand extends Command
{
    /**
     * Configuration of command
     */
    protected function configure()
    {
        $this
            ->setName("modera:module:client")
            ->setDescription("Run Modera module client")
            ->setDefinition(array(
                new InputOption('--ui', null, InputOption::VALUE_OPTIONAL, '', false),
                new InputOption('--path-prefix', null, InputOption::VALUE_OPTIONAL, '', null),
                new InputOption('--listen-host', null, InputOption::VALUE_REQUIRED, '', '0.0.0.0'),
                new InputOption('--listen-port', null, InputOption::VALUE_REQUIRED, '', 8080),
                new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'),
            ))
        ;
    }

    /**
     * @param InputInterface $input
     * @return string
     * @throws \RuntimeException
     */
    protected function getWorkingDir(InputInterface $input)
    {
        $workingDir = $input->getParameterOption(array('--working-dir', '-d'));
        if (false !== $workingDir && !is_dir($workingDir)) {
            throw new \RuntimeException('Invalid working directory specified.');
        }

        if (!$workingDir) {
            $workingDir = getcwd();
        }

        return $workingDir;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host       = $input->getOption('listen-host');
        $port       = $input->getOption('listen-port');
        $pathPrefix = $input->getOption('path-prefix');
        $workingDir = $this->getWorkingDir($input);

        $output->writeln('<info>Client started:</info>');
        $output->writeln('    <comment>listen:' . '</comment> ' . $host . ':' . $port);
        $output->writeln('    <comment>working-dir:' . '</comment> ' . $workingDir);

        $client = new ModuleClient($workingDir);

        $loop   = EventLoopFactory::create();
        $socket = new SocketServer($loop);
        $http   = new HttpServer($socket);

        $http->on('request', function(HttpRequest $request, HttpResponse $response) use ($input, $client, $workingDir, $pathPrefix)
        {
            $path = $request->getPath();
            if ($pathPrefix) {
                $path = str_replace($pathPrefix, '', $path);
            }

            if (in_array($path, array('/call', '/status', '/update-status'))) {
                $response->writeHead(200, array('Content-Type' => 'application/json'));
                $params = $request->getQuery();

                try {
                    switch ($path) {
                        case '/call':
                            $headers = $request->getHeaders();
                            $url  = 'http://' . $headers['Host'] . ':' . $input->getOption('listen-port');
                            $resp = $client->callMethod($params, $url . '/update-status');
                            break;
                        case '/status':
                            $resp = $client->getStatus($params);
                            break;
                        case '/update-status':
                            $resp = $client->updateStatus($params);
                            break;
                    }
                } catch (\Exception $e) {
                    $resp = array(
                        'success' => false,
                        'msg'     => $e->getMessage()
                    );
                }

                $resp = json_encode($resp);
                if (isset($params['callback'])) {
                    $resp = $params['callback'] . '(' . $resp . ')';
                }

                $response->end($resp);

            } else if ($input->getOption('ui')) {
                if ('/api' == $path) {
                    $response->writeHead(200, array('Content-Type' => 'application/json'));
                    $params = $request->getQuery();

                    try {
                        $resp = $client->apiMethod($params);
                    } catch (\Exception $e) {
                        $resp = array(
                            'success' => false,
                            'msg'     => $e->getMessage()
                        );
                    }

                    $response->end(json_encode($resp));

                } else {
                    $response->writeHead(200, array('Content-Type' => 'text/html'));
                    try {
                        $resp = $this->getTwigEnv()->render('index.html.twig', array(
                            'pathPrefix' => $pathPrefix ?: '',
                        ));
                    } catch (\Exception $e) {
                        $resp = $e->getMessage();
                    }

                    $response->end($resp);
                }

            } else {
                $response->writeHead(200, array('Content-Type' => 'text/html'));
                $response->end('Client started');
            }
        });
        $socket->listen($port, $host);
        $loop->run();
    }

    /**
     * @return \Twig_Environment
     */
    private function getTwigEnv()
    {
        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../Resources/templates');
        return new \Twig_Environment($loader);
    }
}