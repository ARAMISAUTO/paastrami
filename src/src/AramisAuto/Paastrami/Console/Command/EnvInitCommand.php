<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvInitCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:init')
            ->setDescription('Creates environment directory structure and copies files from platform')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addArgument('environment', InputArgument::REQUIRED, "Nom de l'environnement");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Initialize environment
        $environment->init();
    }
}
