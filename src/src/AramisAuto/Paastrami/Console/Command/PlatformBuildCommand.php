<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PlatformBuildCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:build')
            ->setDescription("Génération des images de la plateforme")
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate platform
        $platform = new Platform(
            $input->getArgument('platform'),
            sprintf('%s/platforms/%s', $input->getOption('working-directory'), $input->getArgument('platform'))
        );

        // Build platform
        $platform->build();
    }
}
