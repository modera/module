<?php

namespace Modera\Module\Command;

use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Command\InstallCommand as Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @copyright 2013 Modera Foundation
 * @author Sergei Vizel <sergei.vizel@modera.org>
 */
class RemoveCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('remove')
            ->setDescription('Removes the project dependencies from the composer.lock and composer.json.')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::REQUIRED, 'Required package name, e.g. foo/bar'),
                new InputOption('prefer-source', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.'),
                new InputOption('prefer-dist', null, InputOption::VALUE_NONE, 'Forces installation from package dist even for dev versions.'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Outputs the operations but will not execute anything (implicitly enables --verbose).'),
                new InputOption('dev', null, InputOption::VALUE_NONE, 'Enables installation of require-dev packages (enabled by default, only present for BC).'),
                new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Disables installation of require-dev packages.'),
                new InputOption('no-plugins', null, InputOption::VALUE_NONE, 'Disables all plugins.'),
                new InputOption('no-custom-installers', null, InputOption::VALUE_NONE, 'DEPRECATED: Use no-plugins instead.'),
                new InputOption('no-scripts', null, InputOption::VALUE_NONE, 'Skips the execution of all scripts defined in composer.json file.'),
                new InputOption('no-progress', null, InputOption::VALUE_NONE, 'Do not output download progress.'),
                new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_NONE, 'Shows more details including new commits pulled in when updating packages.'),
                new InputOption('optimize-autoloader', 'o', InputOption::VALUE_NONE, 'Optimize autoloader during autoloader dump')
            ))
            ->setHelp(<<<EOT
The <info>remove</info> command reads the composer.lock and composer.json from
the current directory, processes it, and removes all the
libraries and dependencies outlined in that file.

<info>php composer.phar remove</info>

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('package');

        $file = Factory::getComposerFile();
        $json = new JsonFile($file);
        $composer = $json->read();

        $requireKey = 'require';
        $baseRequirements = array_key_exists($requireKey, $composer) ? $composer[$requireKey] : array();

        if (isset($baseRequirements[$name])) {

            // Update composer.json
            unset($baseRequirements[$name]);
            $composer[$requireKey] = $baseRequirements;
            $json->write($composer);

            // Update composer.lock
            $lockFile = "json" === pathinfo($file, PATHINFO_EXTENSION)
                ? substr($file, 0, -4).'lock'
                : $file . '.lock';

            $lockJson = new JsonFile($lockFile);
            $locker = $lockJson->read();

            $packageKey = null;
            foreach ($locker['packages'] as $key => $package) {
                if ($name == $package['name']) {
                    $packageKey = $key;
                    break;
                }
            }
            if (null !== $packageKey) {
                unset($locker['packages'][$packageKey]);
                $locker['packages'] = array_values($locker['packages']);
            }

            $packageKey = null;
            foreach ($locker['packages-dev'] as $key => $package) {
                if ($name == $package['name']) {
                    $packageKey = $key;
                    break;
                }
            }
            if (null !== $packageKey) {
                unset($locker['packages-dev'][$packageKey]);
                $locker['packages-dev'] = array_values($locker['packages-dev']);
            }

            if (isset($locker['stability-flags'][$name])) {
                unset($locker['stability-flags'][$name]);
            }

            $locker['hash'] = md5_file($file);
            $lockJson->write($locker);

            return parent::execute($input, $output);
        }

        return false;
    }
}
