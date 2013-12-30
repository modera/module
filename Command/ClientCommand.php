<?php

namespace Modera\Module\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\Module\Repository\ModuleRepository;
use React\Http\Request;
use React\Http\Response;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
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
                new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'),
                new InputOption('--server-port', null, InputOption::VALUE_REQUIRED, '', 8080),
                new InputOption('--port', null, InputOption::VALUE_REQUIRED, '', 8081),
            ))
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     * @throws \RuntimeException
     */
    protected function getWorkingDir(InputInterface $input, OutputInterface $output)
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
     * @return mixed
     */
    protected function getServerPort(InputInterface $input, OutputInterface $output)
    {
        return $input->getParameterOption('--server-port', 8080);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    protected function getPort(InputInterface $input, OutputInterface $output)
    {
        return $input->getParameterOption('--port', 8081);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = $this->getWorkingDir($input, $output);
        $serverPort = $this->getServerPort($input, $output);
        $port       = $this->getPort($input, $output);

        $output->writeln('<info>Modera module client started</info>');
        $output->writeln('    <info>working-dir: ' . $workingDir . '</info>');
        $output->writeln('    <info>server-port: ' . $serverPort . '</info>');
        $output->writeln('    <info>client-port: ' . $port . '</info>');

        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../Resources/templates');
        $twig = new \Twig_Environment($loader);

        $loop = \React\EventLoop\Factory::create();
        $socket = new \React\Socket\Server($loop);
        $http = new \React\Http\Server($socket);
        $http->on('request', function (Request $request, Response $response) use ($twig, $workingDir, $serverPort) {

            $path = $request->getPath();
            if (in_array($path, array('/call', '/status'))) {
                $params = $request->getQuery();
                $response->writeHead(200, array('Content-Type' => 'application/json'));
                try {
                    $moduleRepository = new ModuleRepository($workingDir);
                    $moduleRepository->connect($serverPort, function($remote, $connection) use ($path, $params, $response) {
                        $remote->{substr($path, 1)}($params, function($resp) use ($connection, $response) {
                            $connection->end();
                            $response->end(json_encode($resp));
                        });
                    });
                } catch (\Exception $e) {
                    $response->end(json_encode(array(
                        'success' => false,
                        'msg'     => $e->getMessage(),
                    )));
                }

            } else {
                $response->writeHead(200, array('Content-Type' => 'text/html'));
                try {
                    $moduleRepository = new ModuleRepository($workingDir);
                    $response->end($twig->render('index.html.twig', array(
                        'repo' => $moduleRepository,
                    )));
                } catch (\Exception $e) {
                    $response->end($e->getMessage());
                }
            }
        });
        $socket->listen($port);
        $loop->run();
    }
}