<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class HostApacheCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('host:apache')
            ->setDescription('Génération de la configuration Apache d\'un environnement')
            ->addArgument('environnement', InputArgument::REQUIRED, 'Nom de l\'environnement')
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domaine', 'localhost.localdomain')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
