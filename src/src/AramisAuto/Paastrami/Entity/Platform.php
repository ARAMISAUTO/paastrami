<?php

namespace AramisAuto\Paastrami\Entity;

use AramisAuto\Component\Preprocessor\Preprocessor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Platform
{
    private $directory;
    private $firstMachine;
    private $name;
    private $machines = [];

    public function __construct($name, $directory)
    {
        $this->name = $name;
        $this->directory = realpath(sprintf('%s/platforms/%s', $directory, $this->name));
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
        $matches = [];
        $found = preg_match(
            "/==> Builds finished\. The artifacts of successful builds are:\n-->.+: (.+)/",
            $process->getOutput(),
            $matches
        );

        return $matches[1];
    }

    public function getPreprocessingData(array $machine)
    {
        $data = [
            'box'         => $machine['box'],
            'environment' => null,
            'machine'     => $machine['name'],
            'platform'    => $this->name,
            'repository'  => realpath($this->getRepository()),
            'sites'       => '',
        ];
        foreach ($this->getMachines() as $name => $spec) {
            $data[sprintf('machines.%s.box', $name)] = $spec['box'];
        }

        return $data;
    }

    public function getEnvironments()
    {
        // Find environments directories
        $finder = new Finder();
        $environments = [];
        $dirs = $finder
            ->directories()
            ->depth('< 1')
            ->sortByName()
            ->in($this->getDirectory().'/environments');

        // Instanciate environments
        foreach ($dirs as $dir) {
            $environments[] = new Environment($dir->getFilename(), $this);
        }

        return $environments;
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

    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns list of site's dependencies.
     *
     * @param string $site Site name
     *
     * @return array List of sites names
     */
    public function getSiteDependencies($site)
    {
        // Check if site exists
        if (!$this->siteExists($site)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Site does not exist in platform - site=%s, platform=%s',
                    $site,
                    $this->getName()
                )
            );
        }

        // Read dependencies from site's file
        $sites = explode(
            "\n",
            trim(
                file_get_contents(
                    sprintf(
                        '%s/etc/paastrami/sites/%s',
                        $this->getRepository(),
                        $site
                    )
                )
            )
        );

        return $sites;
    }

    /**
     * Check if site exists in environment.
     *
     * @param string $site Site
     *
     * @return bool True if site exists in environment
     */
    public function siteExists($site)
    {
        // Directory holding list of sites (one file per site)
        $pathSite = sprintf('%s/etc/paastrami/sites/%s', $this->getRepository(), $site);

        return is_readable($pathSite);
    }
}
