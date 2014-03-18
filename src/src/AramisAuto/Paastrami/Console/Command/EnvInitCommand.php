<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class EnvInitCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('env:init')
            ->setDescription("Instanciation d'un environnement d'une plateforme : récupération des sources des applications et configuration, création et provisionning des machines virtuelles")
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
            ->addOption('ip-range', null, InputOption::VALUE_REQUIRED, "Plage d'IP", '192.168.0.2-254')
            ->addOption('sources', null, InputOption::VALUE_REQUIRED, "Chemin relatif vers le répertoire qui va héberger les sources des sites", 'var/www')
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addArgument('name', InputArgument::REQUIRED, "Nom de l'environnement")
            ->addArgument(
                'sites',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                "Liste des sites à configurer. Format: nom:branche"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Log
        $output->writeln(
            sprintf(
                '<info>Création d \'un environnement</info> - environnement="%s", platform="%s", ip-range="%s", working-directory="%s", sites="%s"',
                $input->getArgument('name'),
                $input->getArgument('platform'),
                $input->getOption('ip-range'),
                $input->getOption('working-directory'),
                implode(',', $input->getArgument('sites'))
            )
        );

        // Utils
        $fs = new Filesystem();
        $environment = $input->getArgument('name');
        $platform = $input->getArgument('platform');
        $dirPlatform = sprintf(
            '%s/platforms/%s',
            $input->getOption('working-directory'),
            $platform
        );
        $dirEnv = sprintf('%s/environments/%s', $dirPlatform, $environment);
        $confEnvironment = array('name' => $environment, 'platform' => $platform);
        $paastramiSpec = $dirEnv.'/etc/paastrami/paastrami.json';

        // On vérifie que l'environnement n'existe pas déjà
        if (is_readable($paastramiSpec)) {
            throw new \RuntimeException(
                sprintf(
                    'L\'environnement existe déjà - environnement="%s", platform="%s"',
                    $input->getArgument('name'),
                    $input->getArgument('platform')
                )
            );
        }

        // Create environment directory
        $output->writeln(sprintf('<info>Création de l\'arborescence de fichiers</info> - directory="%s"', $dirEnv));
        $fs->mkdir($dirEnv, 0755);

        // Read platform specification file
        $contentsVagrantfile = file_get_contents($dirPlatform.'/repository/Vagrantfile-dist');
        $matches = array();
        $found = preg_match_all('/config\.vm\.define "(\w+)" do \|\w+\|/', $contentsVagrantfile, $matches);
        if (!$found) {
            throw new \RuntimeException('Could not extract virtual machines names from Vagrantfile');
        }
        $vms = $matches[1];
        $output->writeln(
            sprintf(
                '<info>Extraction des machines virtuelles à partir du Vagrantfile de la plateforme</info> - vagrantfile="%s", vmcount="%d", vms="%s"',
                $dirPlatform.'/repository/Vagrantfile-dist',
                count($vms),
                implode(',', $vms)
            )
        );

        // Reserve IP adresses for virtual machines
        $output->writeln(
            sprintf(
                '<info>Réservation d\'une adresse IP pour chaque machine virtuelle</info> - iprange="%s", vmcount="%d"',
                $input->getOption('ip-range'),
                count($vms)
            )
        );
        $process = new Process(sprintf('nmap -v -sP %s', $input->getOption('ip-range')));
        $process->setTimeout(120); // ~time to scan 255 hosts on a standard network
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
        $matches = array();
        $found = preg_match_all(
            '/Nmap scan report for (\d+\.\d+\.\d+\.\d+) \[host down\]/',
            $process->getOutput(),
            $matches
        );
        if (!$found || count($matches[1]) < count($vms)) {
            throw new \RuntimeException(
                sprintf(
                    'No IP available for VMs %s',
                    json_encode(
                        array(
                            'range' => $input->getOption('ip-range'),
                            'platform' => $platform,
                            'environnement' => $environment,
                            'vms' => implode(',', $vms)
                        )
                    )
                )
            );
        }
        $mapIp = array();
        $ipEntrypoint = $matches[1][0];
        for ($i = 0; $i < count($vms); $i++) {
            $confEnvironment['vms'][] = array('name' => $vms[$i], 'ip' => $matches[1][$i]);
        }
        foreach ($confEnvironment['vms'] as $vm) {
            $output->writeln(
                sprintf(
                    '* <info>IP réservée pour la machine virtuelle</info> - vm="%s", ip="%s"',
                    $vm['name'],
                    $vm['ip']
                )
            );
        }

        // Generate replacement tokens map
        $mapTokens = array('@paastrami.env@' => $environment);
        foreach ($confEnvironment['vms'] as $vm) {
            $mapTokens[sprintf('@paastrami.vms.%s.ip@', $vm['name'])] = $vm['ip'];
        }

        // Generate environment's Vagrantfile
        $contentsVagrantfile = $this->replaceTokens($mapTokens, $contentsVagrantfile);

        // Write environment's Vagrantfile
        $output->writeln(
            sprintf(
                '<info>Génération du Vagrantfile de l\'environnement</info> - vagrantfile="%s", tokens=\'%s\'',
                $dirEnv.'/Vagrantfile',
                json_encode($mapTokens)
            )
        );
        file_put_contents($dirEnv.'/Vagrantfile', $contentsVagrantfile);

        // Generate sites list
        // Primary sites
        $output->writeln(
            sprintf(
                '<info>Génération de la liste des sites à installer</info> - sites="%s"',
                implode(',', $input->getArgument('sites'))
            )
        );
        $mapSites = array();
        foreach ($input->getArgument('sites') as $siteDef) {
            $siteParts = explode(':', $siteDef);
            $mapSites[$siteParts[0]] = 'master';
            if (isset($siteParts[1])) {
                $mapSites[$siteParts[0]] = $siteParts[1];
            }
        }

        // Dependencies
        foreach ($mapSites as $site => $branch) {
            if (file_exists($dirPlatform.'/repository/etc/paastrami/sites/'.$site)) {
                $dependencies = file($dirPlatform.'/repository/etc/paastrami/sites/'.$site);
                foreach ($dependencies as $dependency) {
                    $dependencyParts = explode(':', $dependency);
                    $dependencySite = trim($dependencyParts[0]);
                    if (!isset($mapSites[$dependencySite])) {
                        $mapSites[$dependencySite] = 'master';
                        if (isset($dependencyParts[1])) {
                            $mapSites[$dependencySite] = $dependencyParts[1];
                        }
                    }
                }
            }
        }

        // Write sites
        $fs->remove($dirEnv.'/etc/paastrami/sites');
        $fs->mkdir($dirEnv.'/etc/paastrami/sites', 0755);
        foreach ($mapSites as $site => $branch) {
            $output->writeln(sprintf('* <info>Site à installer</info> - site="%s", branch="%s"', $site, $branch));
            file_put_contents($dirEnv.'/etc/paastrami/sites/'.$site, trim($branch));
        }

        // Generate Salt configuration
        $this->doSalt($confEnvironment['vms'], $mapTokens, $dirPlatform, $dirEnv, $output);

        // Création du répertoire hébergeant les sources
        $fs->mkdir($dirEnv.'/'.$input->getOption('sources'));

        // Write environment configuration
        $output->writeln(
            sprintf(
                '<info>Génération du fichier de spécification de l\'environnement</info> - file="%s"',
                $paastramiSpec
            )
        );
        $confEnvironment['sites'] = $mapSites;
        file_put_contents($paastramiSpec, json_encode($confEnvironment, JSON_PRETTY_PRINT));

        // Démarrage des machines virtuelles
        foreach ($vms as $vm) {
            $output->writeln(
                sprintf(
                    '<info>Démarrage des machines virtuelles '.
                    '(cette opération peut prendre plusieurs minutes)</info> - vm="%s"',
                    $vm
                )
            );
            $command = 'vagrant up '.$vm;
            $process = new Process($command, $dirEnv);
            $process->setTimeout(0);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
            if (!$process->isSuccessful()) {
                throw new \RuntimeException(
                    sprintf(
                        'Le provisionning de la machine virtuelle a échoué : %s - vm="%s", command="%s"',
                        $process->getErrorOutput(),
                        $vm,
                        $command
                    )
                );
            }
        }
    }

    private function doSalt(array $vms, array $mapTokens, $dirPlatform, $dirEnv, OutputInterface $output)
    {
        // Génération de la configuration Salt
        $fs = new Filesystem();
        $fs->mkdir($dirEnv.'/etc/salt');
        $output->writeln(
            sprintf(
                '<info>Génération de la configuration Salt de l\'environnement</info> - dirSalt="%s"',
                $dirEnv.'/etc/salt'
            )
        );
        $tplConf = file_get_contents($dirPlatform.'/repository/etc/salt/minion.conf-dist');
        foreach ($vms as $vm) {
            $output->writeln(
                sprintf(
                    '* <info>Génération du fichier de configuration Salt de la machine virtuelle</info> - vm="%s", file="%s"',
                    $vm['name'],
                    sprintf('%s/etc/salt/%s.conf', $dirEnv, $vm['name'])
                )
            );
            $conf = str_replace('@paastrami.vm@', $vm['name'], $tplConf);
            file_put_contents(sprintf('%s/etc/salt/%s.conf', $dirEnv, $vm['name']), $conf);
        }

        // Génération des locaux à partir des fichiers -dist
        $finder = new Finder();
        $finder->files()->name('*-dist');
        foreach (array('salt', 'pillar') as $type) {
            $output->writeln(
                sprintf(
                    '* <info>Génération des fichiers Salt locaux</info> - type="%s", source="%s", dest="%s"',
                    $type,
                    $dirPlatform.'/repository/srv/pillar',
                    $dirEnv.'/srv/paastrami/pillar'
                )
            );
            $fs->mkdir($dirEnv.'/srv/'.$type);
            foreach ($finder->in($dirPlatform.'/repository/srv/'.$type) as $file) {
                // Remplacement des tokens
                $contents = $this->replaceTokens($mapTokens, file_get_contents($file->getRealPath()));
                $path = explode($dirPlatform.'/repository/srv/'.$type, $file->getPath());
                $pathBase = basename($path[1]);
                $fs->mkdir($dirEnv.'/srv/'.$type.'/'.$pathBase);
                $file = $dirEnv.'/srv/'.$type.'/'.$pathBase.'/'.basename($file->getFilename(), '-dist');
                file_put_contents($file, $contents);
                $output->writeln(sprintf('** <info>Génération du fichier</info> - file="%s"', $file));
            }
        }
    }

    private function replaceTokens(array $mapTokens, $contents)
    {
        foreach ($mapTokens as $token => $replacement) {
            $contents = str_replace(array_keys($mapTokens), array_values($mapTokens), $contents);
        }

        return $contents;
    }
}
