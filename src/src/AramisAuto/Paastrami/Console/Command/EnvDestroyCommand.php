<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvDestroyCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:destroy')
            ->setDescription('Deletes the environment')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Halt environment
        $environment->halt();

        // Destroy environment
        $environment->destroy();
    }
}
