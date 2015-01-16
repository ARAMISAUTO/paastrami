<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvSshCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:ssh')
            ->setDescription("Connects to a machine via SSH")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Connect to the machine using this username', 'vagrant')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name')
            ->addArgument('machine', InputArgument::REQUIRED, 'Machine name')
            ->addArgument('cmd', InputArgument::OPTIONAL, 'Execute this command on machine instead of connecting to it');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate environment
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        // Build Vagrant command
        $user = $input->getOption('user');
        $machine = $input->getArgument('machine');
        if ($user == 'vagrant') {
            $command = sprintf('vagrant ssh %s', $machine);
            if ($input->getArgument('cmd')) {
                $command .= ' --command '.$input->getArgument('cmd');
            }
        } else {
            // Get machine spec
            $machineSpec = $environment->getMachine($machine);
            $ip = filter_var($machineSpec['ip'], FILTER_VALIDATE_IP);

            // Make sure machine has a valid IP
            if (false === $ip) {
                throw new \RuntimeException(
                    sprintf(
                        'Machine does not have a valid IP adress - machine=%s, ip=%s',
                        $machine,
                        $machineSpec['ip']
                    )
                );
            }

            // Build explicit SSH command
            $command = sprintf('ssh %s@%s', $user, $ip);
            if ($input->getArgument('cmd')) {
                $command .= ' '.$input->getArgument('cmd');
            }
        }

        // Execute command
        chdir($environment->getDirectory());
        passthru($command);
    }
}
