<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

// TODO : log
class HostBootstrapCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('host:bootstrap')
            ->setDescription('Installation des dépendances sur la machine hôte')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addOption('vagrant-url', null, InputOption::VALUE_OPTIONAL, 'Url vers le paquet Vagrant')
            ->addOption(
                'package-manager',
                null,
                InputOption::VALUE_REQUIRED,
                'Gestionnaire de paquets à utiliser',
                $this->guessPackageManager()
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Sélection de la méthode d'installation (eg /usr/bin/apt-get => AptGet)
        $method = 'do'.str_replace(
            ' ',
            '',
            ucwords(str_replace('-', ' ', basename($input->getOption('package-manager'))))
        );
        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Méthode d'installation inconnue %s",
                    json_encode(
                        array('package-manager' => $input->getOption('package-manager'), 'method' => $method),
                        JSON_UNESCAPED_SLASHES
                    )
                )
            );
        }

        // Récupération de la liste de commandes à exécuter pour le gestionnaire de package sélectionné
        $commandsPackages = call_user_func(
            array($this, $method),
            $input->getOption('package-manager'),
            $input->getOption('working-directory'),
            $input->getOption('vagrant-url')
        );

        // Commandes pour l'installation des plugins Vagrant
        $vagrantPlugins = array('vagrant-vbguest', 'vagrant-cachier', 'vagrant-salt');
        $commandsVagrant = array();
        foreach ($vagrantPlugins as $vagrantPlugin) {
            $commandsVagrant[] = sprintf('vagrant plugin uninstall %s', $vagrantPlugin);
            $commandsVagrant[] = sprintf('vagrant plugin install %s', $vagrantPlugin);
        }

        // Exécution de la liste des commandes
        $commands = array_merge($commandsPackages, $commandsVagrant);
        foreach ($commands as $command) {
            $process = new Process($command);
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }
        }

        // Création de l'arborescence
        $fs = new Filesystem();
        $fs->mkdir(sprintf('%s/platforms', $input->getOption('working-directory')));
    }

    // TODO : ne pas retélécharger Vagrant si le paquet est déjà présent
    // TODO : ne pas réinstaller Vagrant s'il est déjà dans la bonne version
    private function doAptGet($packageManager, $workingDirectory, $urlVagrant = null)
    {
        if (!$urlVagrant) {
            $urlVagrant = sprintf(
                'https://dl.bintray.com/mitchellh/vagrant/vagrant_1.5.0_%s.deb',
                posix_uname()['machine']
            );
        }
        $commands = array(
            sprintf('%s update', $packageManager),
            sprintf('%s -y --force-yes install apache2 nfs-kernel-server curl rpl nmap bind9 git virtualbox', $packageManager),
            sprintf('wget %s -O %s/vagrant.deb', $urlVagrant, $workingDirectory),
            sprintf('dpkg -i %s/vagrant.deb', $workingDirectory),
            sprintf('rm %s/vagrant.deb', $workingDirectory),
            'a2enmod proxy_http',
            'service apache2 reload'
        );

        return $commands;
    }

    private function guessPackageManager()
    {
        $manager = '/usr/bin/apt-get';
        $managers = array('apt-get', 'yum');
        foreach ($managers as $command) {
            // Sélection de la méthode d'installation
            $process = new Process('which '.$command);
            $process->run();
            if ($process->isSuccessful()) {
                $manager = trim($process->getOutput());
                break;
            }
        }

        return $manager;
    }
}
