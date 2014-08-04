<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteRemoveCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('site:remove')
            ->setDescription("Removes site from environment")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name')
            ->addArgument('site', InputArgument::REQUIRED, 'Site name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Remove site
        $site = $input->getArgument('site');
        $environment->removeSite($site);

        // Log
        $output->writeln(
            sprintf(
                '<info>Site has been removed and machines reprovisioned</info> - site=%s, environment=%s, platform=%s',
                $site,
                $environment->getName(),
                $environment->getPlatform()->getName()
            )
        );
    }
}
