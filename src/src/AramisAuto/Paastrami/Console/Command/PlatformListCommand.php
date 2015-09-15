<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class PlatformListCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:list')
            ->setDescription('Lists available platforms')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @see http://symfony.com/fr/doc/current/components/console/helpers/tablehelper.html
        $table = $this->getHelperSet()->get('table');
        $table->setHeaders(['name', 'boxes', 'environments']);

        // Find platforms directories
        $finder = new Finder();
        $platforms = [];
        $dirsPlatforms = $finder
            ->directories()
            ->depth('< 1')
            ->in($input->getOption('working-directory').'/platforms');

        // Build table
        foreach ($dirsPlatforms as $dir) {
            $platform = new Platform($dir->getFilename(), $input->getOption('working-directory'));

            // Platform environments
            $environments = [];
            foreach ($platform->getEnvironments() as $environment) {
                $environments[] = $environment->getName();
            }

            $table->addRow(
                [
                    $platform->getName(),
                    implode(',', array_keys($platform->getMachines())),
                    implode(',', $environments),
                ]
            );
        }

        // Render table
        $table->render($output);
    }
}
