<?php

namespace AramisAuto\Paastrami\Console\Command;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Vagrantfile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class PlatformBuildCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('platform:build')
            ->setDescription("Génération des images de la plateforme")
            ->addArgument('platform', InputArgument::REQUIRED, 'Nom de la plateforme')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Log
        $output->writeln(
            sprintf('<info>Génération des images de la plateforme</info> - platform="%s"', $input->getArgument('platform'))
        );

        // Chemins utiles
        $paths = array();
        $paths['platform'] = sprintf(
            '%s/platforms/%s',
            $input->getOption('working-directory'),
            $input->getArgument('platform')
        );
        $paths['repository'] = sprintf('%s/repository', $paths['platform']);

        // Analyse du Vagrantfile
        $pathVagrantfile = sprintf('%s/builders/vagrant/Vagrantfile-dist', $paths['repository']);
        $output->writeln(sprintf('<info>Analyse du Vagrantfile</info> - vagrantfile="%s"', $pathVagrantfile));
        $vagrantfile = Vagrantfile::fromFile($pathVagrantfile);

        // Génération de paastrami.json
        $pathPaastramiJson = sprintf('%s/builders/paastrami/paastrami.json', $paths['repository']);
        $output->writeln(
            sprintf('<info>Génération du fichier paastrami.json</info> - file="%s"', $pathPaastramiJson)
        );

        // Génération des images de machines
        $output->writeln(
            sprintf('<info>Génération des images des machines</info> - vagrantfile="%s"', $pathVagrantfile)
        );
        foreach ($vagrantfile->getData()['machines'] as $machine) {
            try {
                // Génération des tokens
                $tokens = $this->getTokensMap($machine, $input);

                // Écriture du fichier de configuration Paastrami pour la machine
                file_put_contents(
                    sprintf('%s/etc/paastrami/%s.json', $paths['repository'], $tokens['machine']),
                    json_encode($tokens, JSON_PRETTY_PRINT)
                );

                // Génération des dists
                $this->generateDistFiles($paths['repository'], $tokens);

                // Génération des images
                $this->buildImage($tokens, $input, $output);
            } catch (\InvalidArgumentException $e) {
                $output->writeln(sprintf('<error>Image non générée : %s</error>', $e->getMessage()));
            }
        }
    }

    private function getTokensMap(array $machine, InputInterface $input)
    {
        $map = array(
            'box'               =>  $machine['box'],
            'builder'           =>  explode('_', $machine['box'])[0],
            'context'           =>  'preseed',
            'environment'       =>  null,
            'ip'                =>  null,
            'machine'           =>  $machine['name'],
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

    // TODO : log !
    private function generateDistFiles($directory, array $tokens)
    {
        $preprocessor = new Preprocessor($tokens, '@', 'paastrami.', '-dist');
        $preprocessor->preprocess($directory);
    }

    private function buildImage(array $tokens, InputInterface $input, OutputInterface $output)
    {
        $methodBuild = 'build'.ucfirst($tokens['builder']);
        if (!is_callable(array($this, $methodBuild))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'générateur inconnu - machine="%s", box="%s", builder="%s"',
                    $tokens['machine'],
                    $tokens['box'],
                    $tokens['builder']
                )
            );
        }

        // Appel du générateur
        $pathVagrantBox = call_user_func(array($this, $methodBuild), $tokens, $input, $output);

        // Ajout de la box Vagrant
        $this->addVagrantBox($pathVagrantBox, $tokens, $input, $output);
    }

    private function buildPacker($tokens, InputInterface $input, OutputInterface $output)
    {
        // Chemin vers le template Packer
        $partsBox = explode('_', $tokens['box']);
        array_shift($partsBox);
        $box = implode('_', $partsBox);
        $pathTemplate = sprintf('%s/builders/packer/%s.json', $tokens['repository'], $box);

        // Génération de la commande Packer
        $command = sprintf(
            "packer build".
            " -var 'paastrami_box=%s'".
            " -var 'paastrami_builder=%s'".
            " -var 'paastrami_environment=%s'".
            " -var 'paastrami_machine=%s'".
            " -var 'paastrami_platform=%s'".
            " -var 'paastrami_repository=%s'".
            " -var 'paastrami_working_directory=%s'".
            " %s",
            $box,
            $tokens['builder'],
            $tokens['environment'],
            $tokens['machine'],
            $tokens['platform'],
            $tokens['repository'],
            realpath($input->getOption('working-directory')),
            $pathTemplate
        );

        // Exécution de la commande
        $output->writeln(
            sprintf(
                '<info>Génération d\'une image</info> - machine="%s", box="%s", builder="packer"',
                $tokens['machine'],
                $box
            )
        );
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf(
                    'La génération de l\'image a échoué : %s - machine="%s", command="%s"',
                    $process->getErrorOutput(),
                    $tokens['machine'],
                    $command
                )
            );
        }

        // Récupération du chemin vers la box Vagrant générée
        $matches = array();
        $found = preg_match(
            "/==> Builds finished\. The artifacts of successful builds are:\n-->.+: (.+)/",
            $process->getOutput(),
            $matches
        );
        // TODO : exception si not found

        return $matches[1];
    }

    private function addVagrantBox($pathVagrantBox, array $tokens, InputInterface $input, OutputInterface $output)
    {
        // On vérifie que le fichier est bien lisible
        if (!is_readable($pathVagrantBox)) {
            throw new \InvalidArgumentException(
                sprintf('<info>Impossible de lire le fichier de box Vagrant</info> - file="%s"', $pathVagrantBox)
            );
        }

        $output->writeln(sprintf('<info>Ajout de la box à Vagrant</info> - file="%s"', $pathVagrantBox));
        $command = sprintf('vagrant box add -f --name=%s %s', $tokens['box'], $pathVagrantBox);
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf(
                    'L\'ajout de la box à Vagrant a échoué : %s - machine="%s", command="%s"',
                    $process->getErrorOutput(),
                    $tokens['machine'],
                    $command
                )
            );
        }
    }
}
