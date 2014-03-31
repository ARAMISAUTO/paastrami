<?php

namespace AramisAuto\Paastrami\Entity;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Platform;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Environment
{
    const STATUS_HALTED = 1;
    const STATUS_MIXED = 2;
    const STATUS_NEW = 3;
    const STATUS_UNKNOWN = 4;
    const STATUS_UP = 5;

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

        // Generate sites list
        if (!is_null($sites)) {
            $sites = $this->generateSitesList($sites);
        }

        // Preprocess environment
        $i = 0;
        foreach ($this->platform->getMachines() as $machine) {
            // Generate preprocessing data
            $data = $this->getPreprocessingData($machine, $ips[$i], $ips, $sites);

            // Preprocess environment files
            $this->preprocess($data);

            $i++;
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

    public function getPreprocessingData(array $machine, $ip, array $ips, array $sites = null)
    {
        // Get platform related data
        $data = $this->platform->getPreprocessingData($machine);

        // Add environment data
        $data['environment'] = $this->name;
        $data['ip'] = $ip;
        $data['sites'] = '"'.implode('","', array_keys($sites)).'"';

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

    public function getName()
    {
        return $this->name;
    }

    public function getPlatform()
    {
        return $this->platform;
    }

    public function getStatus()
    {
        $statuses = array();
        foreach ($this->getPlatform()->getMachines() as $machine) {
            // Execute vagrant command
            $process = new Process('vagrant status '.$machine['name'], $this->getDirectory());
            $process->run();

            // Parse output
            $matches = array();
            $found = preg_match(sprintf('/%s +(\w+)/', $machine['name']), $process->getOutput(), $matches);
            if (!$found) {
                $statuses[] = self::STATUS_UNKNOWN;
            } elseif ($matches[1] == 'poweroff') {
                $statuses[] = self::STATUS_HALTED;
            } elseif ($matches[1] == 'running') {
                $statuses[] = self::STATUS_UP;
            }
        }

        $status = array_unique($statuses);
        if (count($status) === 1) {
            $status = $status[0];
        } else {
            $status = self::STATUS_MIXED;
        }

        return $status;
    }

    public function getStatusText($status)
    {
        $texts = array(1 => 'halted', 2 => 'mixed', 3 => 'new', 4 => 'unknown', 5 => 'up');
        if (!isset($texts[$status])) {
            $status = 3;
        }

        return $texts[$status];
    }
}
