<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvSshCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:ssh')
            ->setDescription("Connects to a machine via SSH")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name')
            ->addArgument('machine', InputArgument::REQUIRED, 'Machine name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Build Vagrant command
        $command = sprintf('vagrant ssh %s', $input->getArgument('machine'));

        // Execute command
        chdir($environment->getDirectory());
        passthru($command);
    }
}
