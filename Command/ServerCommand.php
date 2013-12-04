<?php

namespace Modera\Module\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\Module\Repository\ModuleRepository;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
abstract class ServerCommand extends Command
{
    /**
     * Configuration of command
     */
    protected function configure()
    {
        $this
            ->setName("modera:module:server")
            ->setDescription("Run Modera module server")
            ->setDefinition(array(
                new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'),
                new InputOption('--port', null, InputOption::VALUE_REQUIRED, '', 8080),
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
    protected function getPort(InputInterface $input, OutputInterface $output)
    {
        return $input->getParameterOption('--port', 8080);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = $this->getWorkingDir($input, $output);
        $port       = $this->getPort($input, $output);

        $output->writeln('<info>Modera module server started</info>');
        $output->writeln('    <info>working-dir: ' . $workingDir . '</info>');
        $output->writeln('    <info>port: ' . $port . '</info>');

        $moduleRepository = new ModuleRepository($workingDir);
        $moduleRepository->listen($port);
    }
}