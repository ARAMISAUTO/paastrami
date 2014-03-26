<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Environment;
use AramisAuto\Paastrami\Entity\Platform;
use AramisAuto\Paastrami\Entity\Vagrantfile;
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
            ->addArgument('environment', InputArgument::REQUIRED, "Nom de l'environnement")
            ->addArgument(
                'sites',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                "Liste des sites à configurer. Format: nom:branche"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = new Environment(
            $input->getArgument('environment'),
            new Platform($input->getArgument('platform'), $input->getOption('working-directory'))
        );

        $environment->init($input->getOption('ip-range'), $input->getArgument('sites'));

        exit;

        // Log
        $output->writeln(
            sprintf(
                '<info>Création d \'un environnement</info> - environnement="%s", platform="%s", ip-range="%s", working-directory="%s", sites="%s"',
                $input->getArgument('environment'),
                $input->getArgument('platform'),
                $input->getOption('ip-range'),
                $input->getOption('working-directory'),
                implode(',', $input->getArgument('sites'))
            )
        );

        // Utils
        $fs = new Filesystem();
        $environment = $input->getArgument('environment');
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
                    $input->getArgument('environment'),
                    $input->getArgument('platform')
                )
            );
        }

        // Create environment directory
        $output->writeln(sprintf('<info>Création de l\'arborescence de fichiers</info> - directory="%s"', $dirEnv));
        $fs->mkdir($dirEnv, 0755);

        // Copie de la plateforme vers le nouvel environnement
        // TODO : ignore .git, etc => en passant un iterator de Finder à fs->mirror()
        $fs->mirror($dirPlatform.'/repository', $dirEnv);

        // Copie du Vagrantfile de la platforme
        $pathVagrantfile = $dirEnv.'/builders/vagrant/Vagrantfile-dist';

        // Analyse de la Vagrantfile
        $vagrantfile = Vagrantfile::fromFile($pathVagrantfile);
        $machines = $vagrantfile->getData()['machines'];
        $output->writeln(
            sprintf(
                '<info>Extraction des machines virtuelles à partir du Vagrantfile de la plateforme</info> - vagrantfile="%s", vmcount="%d"',
                $pathVagrantfile,
                count($vagrantfile->getData()['machines'])
            )
        );

        // Génération des tokens
        $tokens = $this->getTokensMap($input);

        // Recherche d'adresses IP disponibles
        $ips = $this->getAvailableIps(count($machines), $input, $output);

        // Complétion des tokens avec les données de machines
        for ($i = 0; $i < count($machines); $i++) {
            $tokens[sprintf('machines.%s.ip', $machines[$i]['name'])] = $ips[$i];
        }

        // Preprocessing des fichiers de l'environnement
        $this->generateDistFiles($dirEnv, $tokens);

        // Placement du Vagrantfile à la racine
        $fs->copy($dirEnv.'/builders/vagrant/Vagrantfile', $dirEnv.'/Vagrantfile');

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
        $output->writeln(
            sprintf(
                '<info>Démarrage des machines virtuelles '.
                '(cette opération peut prendre plusieurs minutes)</info> - count="%s"',
                count($machines)
            )
        );
        $command = 'vagrant up';
        $process = new Process($command, $dirEnv);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf(
                    'Le provisionning de la machine virtuelle a échoué : %s - command="%s"',
                    $process->getErrorOutput(),
                    $command
                )
            );
        }
    }

    private function getTokensMap(InputInterface $input)
    {
        $map = array(
            'context'           =>  null,
            'environment'       =>  $input->getArgument('environment'),
            'platform'          =>  $input->getArgument('platform'),
            'provisioner'       =>  'vagrant',
            'repository'        =>  null,
            'working_directory' =>  realpath($input->getOption('working-directory'))
        );
        $map['repository'] = sprintf(
            '%s/platforms/%s/repository',
            $map['working_directory'],
            $input->getArgument('platform')
        );

        return $map;
    }

    private function getAvailableIps($count, InputInterface $input, OutputInterface $output)
    {
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
        if (!$found || count($matches[1]) < $count) {
            throw new \RuntimeException(sprintf('Aucune IP disponible - iprange="%s"', $input->getOption('ip-range')));
        }

        return array_slice($matches[1], 0, $count);
    }

    // TODO : log !
    private function generateDistFiles($directory, array $tokens)
    {
        $preprocessor = new Preprocessor($tokens, '@', 'paastrami.', '-dist');
        $preprocessor->preprocess($directory);
    }

    private function replaceTokens(array $mapTokens, $contents)
    {
        foreach ($mapTokens as $token => $replacement) {
            $contents = str_replace(array_keys($mapTokens), array_values($mapTokens), $contents);
        }

        return $contents;
    }
}
