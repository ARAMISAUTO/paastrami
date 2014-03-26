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

    public function init($ipRange, $sites)
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
    }

    private function preprocess(array $data)
    {
        $preprocessor = new Preprocessor($data, 'paastrami.');
        $preprocessor->preprocess($this->getDirectory());
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
        $data = $this->platform->getPreprocessingData($machine);
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
