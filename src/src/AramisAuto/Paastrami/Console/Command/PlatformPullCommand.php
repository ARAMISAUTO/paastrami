<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PlatformPullCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:pull')
            ->setDescription("Mise à jour du dépôt Git d'une plateforme")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addArgument('name', InputArgument::REQUIRED, 'Nom de la plateforme');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirPlatform = sprintf('%s/platforms/%s', $input->getOption('working-directory'), $input->getArgument('name'));

        // On vérifie que la plateforme existe
        if (!is_dir($dirPlatform.'/repository')) {
            throw new \RuntimeException(
                sprintf(
                    'La plateforme n\'existe pas %s',
                    json_encode(
                        ['platform' => $input->getArgument('name'), 'directory' => $dirPlatform],
                        JSON_UNESCAPED_SLASHES
                    )
                )
            );
        }

        // Mise à jour du dépôt Git
        chdir($dirPlatform.'/repository');
        $process = new Process('git pull');
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }
}
