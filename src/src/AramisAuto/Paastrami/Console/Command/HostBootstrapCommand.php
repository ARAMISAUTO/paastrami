<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Component\Preprocessor\Preprocessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

// TODO : log
// TODO : MAL => couplage fort avec Salt (mais c'est déjà un début pour le provisioning multiplateforme du host)
class HostBootstrapCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('host:bootstrap')
            ->setDescription('Installs paastrami dependencies on host')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Working directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Preprocess Salt configuration
        $dirRoot = realpath(__DIR__.'/../../../../../..');
        $dirSalt = $dirRoot.'/src/salt';
        $preprocessor = new Preprocessor(['rootdir' => $dirRoot], 'paastrami.');
        $preprocessor->preprocess($dirSalt);

        // Log
        $output->writeln(sprintf('<info>Provisioning host</info> - salt-config="%s"', $dirSalt));

        // Apply Salt highstate
        $command = sprintf('salt-call --retcode-passthrough --config-dir=%s state.highstate', $dirSalt);
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run();

        // Display Salt ouput if provisionning was not successful
        if (!$process->isSuccessful()) {
            $output->write($process->getOutput());
            $output->write($process->getErrorOutput());
            throw new \RuntimeException('Host provisioning did not succeed - message="%s"');
        }

        // Log
        $output->writeln('<info>Host was successfuly provisioned</info>');
    }
}
