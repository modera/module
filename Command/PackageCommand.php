<?php

namespace Modera\Module\Command;

use Modera\Module\Repository\ModuleRepository;
use Modera\Module\Console\Output\RemoteOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class PackageCommand extends Command
{
    /**
     * Configuration of command
     */
    protected function configure()
    {
        $this
            ->setName("modera:module:package")
            ->setDefinition(array(
                new InputArgument('name', InputArgument::REQUIRED, 'The package name', null),
                new InputArgument('version', InputArgument::OPTIONAL, 'The package version', null),
                new InputOption('--method', null, InputOption::VALUE_REQUIRED, 'Allowed methods: require, remove', 'require'),
                new InputOption('--output-url', null, InputOption::VALUE_OPTIONAL, 'Url for output', null),
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
        $name      = $input->getArgument('name');
        $version   = $input->getArgument('version');
        $method    = $input->getOption('method');
        $outputUrl = $input->getOption('output-url');

        if (!in_array($method, array('require', 'remove'))) {
            $output->writeln('<error>Method "' . $method . '" not allowed</error>');
            return 1;
        }

        $status = array(
            'success' => false,
            'working' => true,
            'msg'     => ''
        );

        $workingDir = $this->getWorkingDir($input);
        $moduleRepository = new ModuleRepository($workingDir);

        $remoteOutput = new RemoteOutput(function($message) use (&$status, $output, $outputUrl) {
            $output->write($message);
            if ($outputUrl) {
                file_get_contents($outputUrl . '?' . http_build_query(array(
                    'success' => $status['success'],
                    'working' => $status['working'],
                    'msg'     => $message,
                )));
            }
        });

        $isSuccess = false;
        if ('require' == $method) {
            $package = $moduleRepository->getPackage($name);
            if ($package) {
                $isSuccess = $moduleRepository->requirePackage($name, $version ?: 'dev-master', $remoteOutput);
            } else {
                $remoteOutput->writeln('Package not found.');
            }
        }
        else if ('remove' == $method) {
            $isSuccess = $moduleRepository->removePackage($name, $remoteOutput);
        }

        $status['success'] = $isSuccess;
        $status['working'] = false;

        $remoteOutput->writeln($isSuccess ? 'Finished: SUCCESS' : 'Finished: FAILURE');
    }
}