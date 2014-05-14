<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class PlatformInitCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:init')
            ->setDescription("Création de l'arborescence de base pour la plateforme et clonage du dépôt Git")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branche du dépôt', 'master')
            ->addArgument('name', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addArgument('git', InputArgument::REQUIRED, 'URL du dépôt Git de la plateforme')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(
            sprintf(
                '<info>Création d\'une nouvelle plateforme</info> - platform="%s", working-directory="%s"',
                $input->getArgument('name'),
                $input->getOption('working-directory')
            )
        );
        $dirPlatform = sprintf('%s/platforms/%s', $input->getOption('working-directory'), $input->getArgument('name'));

        // On vérifie que la plateforme n'existe pas déjà
        if (is_dir($dirPlatform)) {
            throw new \RuntimeException(
                sprintf(
                    'La plateforme existe déjà - platform="%s" directory="%s"',
                    $input->getArgument('name'),
                    $dirPlatform
                )
            );
        }

        // Création du répertoire de la plateforme
        $fs = new Filesystem();
        $fs->mkdir($dirPlatform, 0755);
        $fs->mkdir($dirPlatform.'/environments', 0755);

        // Clonage du dépôt Git
        $output->writeln(
            sprintf(
                '<info>Récupération des sources de la plateforme</info> - platform="%s", git="%s", branch="%s"',
                $input->getArgument('name'),
                $input->getArgument('git'),
                $input->getOption('branch')
            )
        );
        $process = new Process(
            sprintf(
                'git clone -b %s %s %s/platforms/%s/repository',
                $input->getOption('branch'),
                $input->getArgument('git'),
                $input->getOption('working-directory'),
                $input->getArgument('name')
            )
        );
        $process->run();
        if (!$process->isSuccessful()) {
            $fs->remove($dirPlatform);
            throw new \RuntimeException($process->getErrorOutput());
        } else {
            $output->write($process->getOutput());
        }

        // TODO : Génération des images des machines virtuelles

        // Log
        $output->writeln(
            sprintf('<info>La plateforme a bien été créée</info> - platform="%s"', $input->getArgument('name'))
        );

        // Aide pour la suite
        $output->writeln(
            sprintf(
                '<comment>Pour créer un environnement sur cette plateforme : paastrami env:init --working-directory=%s %s NAME SITES1 ... [SITESN]</comment>',
                $input->getOption('working-directory'),
                $input->getArgument('name')
            )
        );
    }
}
