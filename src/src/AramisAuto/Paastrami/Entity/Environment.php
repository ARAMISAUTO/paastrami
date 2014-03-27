<?php

namespace AramisAuto\Paastrami\Entity;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Environment
{
    private $name;
    private $platform;

    public function __construct($name, Platform $platform)
    {
        $this->name = $name;
        $this->platform = $platform;
        $this->directory = sprintf('%s/environments/%s', $this->platform->getDirectory(), $this->name);
    }

    public function init()
    {
        // Utils
        $fs = new Filesystem();
        $finder = new Finder();

        // Create environment directory
        $fs->mkdir($this->directory);

        // Mirror platform files in environment directory
        $fs->mirror(
            $this->platform->getRepository(),
            $this->getDirectory(),
            $finder->notName('.git')->followLinks()->in($this->platform->getRepository()),
            array('delete' => false)
        );
    }

    public function build($ipRange, array $sites = null, $dirSources = null)
    {
        // Utils
        $fs = new Filesystem();

        // Find available IPs for machines
        $ips = $this->findAvailableIps(count($this->platform->getMachines()), $ipRange);

        // Preprocess environment
        $i = 0;
        foreach ($this->platform->getMachines() as $machine) {
            // Generate preprocessing data
            $data = $this->getPreprocessingData($machine, $ips[$i], $ips);

            // Preprocess environment files
            $this->preprocess($data);

            $i++;
        }

        // Generate sites list
        if (!is_null($sites)) {
            $this->generateSitesList($sites);
        }

        // Create sources directory
        if (!is_null($dirSources)) {
            $fs->mkdir($this->getDirectory().'/'.$dirSources);
        }
    }

    public function up($provision = false)
    {
        // Generate Vagrant command
        $command = 'vagrant up --parallel';
        if (true === $provision) {
            $command .= ' --provision';
        }

        // Execute command
        $process = new Process($command, $this->getDirectory());
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    public function halt($force = false)
    {
        // Generate Vagrant command
        $command = 'vagrant halt';
        if (true === $force) {
            $command .= ' --force';
        }

        // Execute command
        $process = new Process($command, $this->getDirectory());
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    public function destroy()
    {
        // Utils
        $fs = new Filesystem();

        // Generate Vagrant command
        $command = 'vagrant destroy --force';

        // Execute command
        $process = new Process($command, $this->getDirectory());
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Delete directory
        $fs->remove($this->getDirectory());
    }

    private function preprocess(array $data)
    {
        $preprocessor = new Preprocessor($data, 'paastrami.');
        $preprocessor->preprocess($this->getDirectory());
    }

    private function generateSitesList(array $sites)
    {
        // Utils
        $fs = new Filesystem();

        $mapSites = array();
        foreach ($sites as $siteDef) {
            $siteParts = explode(':', $siteDef);
            $mapSites[$siteParts[0]] = 'master';
            if (isset($siteParts[1])) {
                $mapSites[$siteParts[0]] = $siteParts[1];
            }
        }

        // Dependencies
        foreach ($mapSites as $site => $branch) {
            if (file_exists($this->platform->getRepository().'/etc/paastrami/sites/'.$site)) {
                $dependencies = file($this->platform->getRepository().'/etc/paastrami/sites/'.$site);
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
        $fs->remove($this->getDirectory().'/etc/paastrami/sites');
        $fs->mkdir($this->getDirectory().'/etc/paastrami/sites', 0755);
        foreach ($mapSites as $site => $branch) {
            file_put_contents($this->getDirectory().'/etc/paastrami/sites/'.$site, trim($branch));
        }

        return $mapSites;
    }

    private function findAvailableIps($count, $ipRange)
    {
        $process = new Process(sprintf('nmap -v -sP %s', $ipRange));
        $process->setTimeout(120); // ~time to scan 255 hosts on a standard network
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
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
            throw new \RuntimeException(
                sprintf('Could not find enough available IPs - count="%d" range="%s"', $count, $ipRange)
            );
        }

        return array_slice($matches[1], 0, $count);
    }

    public function getPreprocessingData(array $machine, $ip, array $ips)
    {
        // Get platform related data
        $data = $this->platform->getPreprocessingData($machine);

        // Add environment data
        $data['environment'] = $this->name;
        $data['ip'] = $ip;

        $i = 0;
        foreach ($this->platform->getMachines() as $machine) {
            $data[sprintf('machines.%s.ip', $machine['name'])] = $ips[$i];
            $i++;
        }

        return $data;
    }

    public function getDirectory()
    {
        return $this->directory;
    }
}
