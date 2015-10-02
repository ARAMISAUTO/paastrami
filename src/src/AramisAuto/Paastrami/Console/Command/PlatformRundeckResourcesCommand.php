<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Platform;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class PlatformRundeckResourcesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:rundeck-resources')
            ->setDescription("Generate a Rundeck compatible list of machines")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addOption('host-suffix', null, InputOption::VALUE_OPTIONAL, 'Host to be appended to nodes hostname')
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get platform
        $platform = new Platform($input->getArgument('platform'), $input->getOption('working-directory'));

        // Get machines
        $machines = $platform->getMachines();

        // Get environments
        $environments = $platform->getEnvironments();

        // Get sites
        $sites = $platform->getSites();

        // Build nodes list
        $nodes = [];
        foreach ($environments as $environment) {
            foreach ($machines as $machine) {
                foreach (array_keys($sites) as $site) {
                    $node = array(
                        'nodename' => sprintf('%s_%s_%s', $machine['name'], $environment->getName(), $site),
                        'hostname' => sprintf(
                            '%s.%s.%s.%s',
                            $machine['name'],
                            $environment->getName(),
                            $platform->getName(),
                            $input->getOption('host-suffix')
                        ),
                        'username' => $site,
                        'tags' => [$platform->getName(), $environment->getName(), $site]
                    );
                }
                $nodes[$node['nodename']] = $node;
            }
        }

        // Serialize
        $serializer = SerializerBuilder::create()->build();
        $yaml = $serializer->serialize($nodes, 'yml');
        echo $yaml;
    }
}