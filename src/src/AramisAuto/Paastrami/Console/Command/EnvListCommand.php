<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class EnvListCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:list')
            ->setDescription("Lists available environments in platform")
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @see http://symfony.com/fr/doc/current/components/console/helpers/tablehelper.html
        $table = $this->getHelperSet()->get('table');
        $table->setHeaders(array('name', 'platform', 'boxes', 'status'));

        // Instanciate platform
        $platform = new Platform($input->getArgument('platform'), $input->getOption('working-directory'));

        // Find platforms directories
        foreach ($platform->getEnvironments() as $env) {
            $table->addRow(
                array(
                    $env->getName(),
                    $env->getPlatform()->getName(),
                    implode(',', array_keys($env->getPlatform()->getMachines())),
                    $env->getStatusText($env->getStatus())
                )
            );
        }

        // Render table
        $table->render($output);
    }
}
