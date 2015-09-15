<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

// TODO : log
// TODO : MAL => couplage fort avec Salt (mais c'est déjà un début pour le provisioning multiplateforme du host)
class PlatformBootstrapHostCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('host:bootstrap')
            ->setDescription('Installs paastrami dependencies on host')
            ->addArgument('platform', InputArgument::REQUIRED, 'Platform name')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Instanciate platform
        $platform = new Platform($input->getArgument('platform'), $input->getOption('working-directory'));

        // Generate Salt minion configuration file
        $data = [
            'platform'          => $platform->getName(),
            'working-directory' => realpath($input->getOption('working-directory')),
        ];
        $pathSaltConfDir = sprintf('%s/provisioners/salt/etc', $platform->getRepository());
        $preprocessor = new Preprocessor($data, 'paastrami.');
        $preprocessor->preprocess($pathSaltConfDir);

        // Apply Salt highstate
        $command = sprintf('salt-call --config-dir=%s state.highstate', $pathSaltConfDir);
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }
}
