<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteAddCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('site:add')
            ->setDescription("Adds site to environment")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Site branch', 'master')
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

        // Add site
        $branch = $input->getOption('branch');
        $site = $input->getArgument('site');
        $environment->addSite($site, $branch);

        // Log
        $output->writeln(
            sprintf(
                '<info>Site has been added and machines reprovisioned</info> - site=%s, branch=%s, environment=%s, platform=%s',
                $site,
                $branch,
                $environment->getName(),
                $environment->getPlatform()->getName()
            )
        );
    }
}
