<?php

namespace AramisAuto\Paastrami\Entity;

use AramisAuto\Component\Preprocessor\Preprocessor;
use AramisAuto\Paastrami\Entity\Vagrantfile;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Platform
{
    private $directory;
    private $firstMachine;
    private $name;
    private $machines = array();

    public function __construct($name, $directory)
    {
        $this->name = $name;
        $this->directory = sprintf('%s/platforms/%s', $directory, $this->name);
        $this->initialize();
    }

    protected function initialize()
    {
        // Search for initial Vagrantfile to get list of machines
        $vagrantfile = Vagrantfile::fromFile(
            sprintf('%s/builders/vagrant/Vagrantfile-dist', $this->getRepository())
        );
        $machines = $vagrantfile->getData()['machines'];
        $this->firstMachine = $machines[0]['name'];
        foreach ($machines as $machine) {
            $this->setMachine($machine['name'], $machine);
        }
    }

    private function preprocess(array $data)
    {
        $preprocessor = new Preprocessor($data, 'paastrami.');
        $preprocessor->preprocess($this->getRepository());
    }

    public function build()
    {
        foreach ($this->getMachines() as $machine) {
            // Generate data used for preprocessing
            $data = $this->getPreprocessingData($machine);

            // Preprocess platform's repository files
            $this->preprocess($data);

            // Build box for machine
            $pathBox = $this->buildBox($data);

            // Add box to Vagrant
            $this->addBox($data['box'], $pathBox);
        }
    }

    private function addBox($name, $path)
    {
        // Generate command
        $command = sprintf('vagrant box add -f --name=%s %s', $name, $path);

        // Execute command
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    private function buildBox(array $data)
    {
        // Generate Packer command
        $command = sprintf('packer build %s/builders/packer/%s.json', $this->getRepository(), $data['box']);

        // Execute command
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Get file to generated Vagrant box
        $matches = array();
        $found = preg_match(
            "/==> Builds finished\. The artifacts of successful builds are:\n-->.+: (.+)/",
            $process->getOutput(),
            $matches
        );

        return $matches[1];
    }

    public function getPreprocessingData(array $machine)
    {
        $data = array(
            'box'        => $machine['box'],
            'machine'    => $machine['name'],
            'platform'   => $this->name,
            'repository' => realpath($this->getRepository()),
        );
        foreach ($this->getMachines() as $name => $spec) {
            $data[sprintf('machines.%s.box', $name)] = $spec['box'];
        }

        return $data;
    }

    public function setMachine($name, array $data)
    {
        $this->machines[$name] = $data;
    }

    public function getMachines()
    {
        return $this->machines;
    }

    public function getDirectory()
    {
        return $this->directory;
    }

    public function getRepository()
    {
        return $this->getDirectory().'/repository';
    }
}
