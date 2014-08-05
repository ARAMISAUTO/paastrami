<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteListCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('site:list')
            ->setDescription("Lists sites in environment")
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

        // Make sure environment exists
        $environment->checkExistence();

        // @see http://symfony.com/fr/doc/current/components/console/helpers/tablehelper.html
        $table = $this->getHelperSet()->get('table');
        $table->setHeaders(array('site', 'branch'));

        // Get environment sites
        $sites = $environment->getSites();

        // Display number of sites
        $output->writeln(
            sprintf(
                "\nEnvironment <info>%s</info> in platform <info>%s</info> hosts <info>%d</info> sites\n",
                $environment->getName(),
                $environment->getPlatform()->getName(),
                count($sites)
            )
        );

        foreach ($sites as $name => $branch) {
            $table->addRow(array($name, $branch));
        }

        // Render table
        $table->render($output);
    }
}
