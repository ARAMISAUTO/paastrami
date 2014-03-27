<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvUpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:up')
            ->setDescription("Builds and starts environment")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addOption('ip-range', null, InputOption::VALUE_REQUIRED, "Plage d'IP", '192.168.0.2-254')
            ->addOption('sources', null, InputOption::VALUE_REQUIRED, "Chemin relatif vers le répertoire qui va héberger les sources des sites", 'var/www')
            ->addOption('provision', null, InputOption::VALUE_NONE, 'Force provisioning of boxes')
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addArgument('environment', InputArgument::REQUIRED, "Nom de l'environnement")
            ->addArgument(
                'sites',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                "Liste des sites à configurer. Format: nom:branche"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Build environment
        $environment->build($input->getOption('ip-range'), $input->getArgument('sites'), $input->getOption('sources'));

        // Start boxes
        $environment->up($input->getOption('provision'));
    }
}
