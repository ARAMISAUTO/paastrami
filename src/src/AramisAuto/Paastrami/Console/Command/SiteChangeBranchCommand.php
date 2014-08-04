<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteChangeBranchCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('site:change-branch')
            ->setDescription("Changes site branch")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name')
            ->addArgument('site', InputArgument::REQUIRED, 'Site name')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch name');
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
        $branch = $input->getArgument('branch');
        $environment->changeSiteBranch($site, $branch);

        // Log
        $output->writeln(
            sprintf(
                '<info>Site branch has been changed and machines reprovisioned</info> - site=%s, branch=%s, environment=%s, platform=%s',
                $site,
                $branch,
                $environment->getName(),
                $environment->getPlatform()->getName()
            )
        );
    }
}
