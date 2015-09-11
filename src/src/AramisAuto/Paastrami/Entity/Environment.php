<?php

namespace AramisAuto\Paastrami\Entity;

use AramisAuto\Component\Preprocessor\Preprocessor;
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

        // Remove leftover Vagrantfile
        $fs->remove(sprintf('%s/builders/vagrant/Vagrantfile', $this->getPlatform()->getRepository()));

        // Mirror platform files in environment directory
        $fs->mirror(
            $this->getPlatform()->getRepository(),
            $this->getDirectory(),
            $finder->ignoreDotFiles(false)->ignoreVCS(false)->followLinks()->in($this->getPlatform()->getRepository()),
            array('delete' => false)
        );
    }

    public function build(array $sites = null, $dirSources = null)
    {
        // Utils
        $fs = new Filesystem();

        // Generate sites list
        if (!is_null($sites)) {
            $sites = $this->generateSitesList($sites);
        }

        // Preprocess environment
        $i = 0;
        foreach ($this->platform->getMachines() as $machine) {
            // Generate preprocessing data
            $data = $this->getPreprocessingData($machine, $sites);

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
        // Make sure environment exists
        $this->checkExistence();

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

    /**
     * Provisions each machine in environment
     *
     * @see https://docs.vagrantup.com/v2/cli/provision.html
     */
    public function provision()
    {
        // Make sure environment exists
        $this->checkExistence();

        // Generate Vagrant command
        $command = 'vagrant provision --parallel';

        // Execute command
        $process = new Process($command, $this->getDirectory());
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    public function halt($force = false)
    {
        // Make sure environment exists
        $this->checkExistence();

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
        // Make sure environment exists
        $this->checkExistence();

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

    public function getPreprocessingData(array $machine, array $sites = null)
    {
        // Get platform related data
        $data = $this->platform->getPreprocessingData($machine);

        // Add environment data
        $data['environment'] = $this->name;
        $data['platform'] = $this->platform->getName();
        $data['sites'] = '"'.implode('","', array_keys($sites)).'"';

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

    public function checkExistence()
    {
        if (!is_dir($this->getDirectory())) {
            throw new \RuntimeException(
                sprintf(
                    'Environment does not exist - platform="%s", environment="%s", directory="%s"',
                    $this->getPlatform()->getName(),
                    $this->getName(),
                    $this->getDirectory()
                )
            );
        }
    }

    /**
     * Returns environment's machines
     *
     * @return array
     */
    public function getMachines()
    {
        $machines = array();
        $pathEtc = sprintf('%s/etc/paastrami', $this->getDirectory());
        $specs = glob($pathEtc.'/*.json');
        foreach ($specs as $filepath) {
            $name = basename($filepath, '.json');
            $machines[] = $this->getMachine($name);
        }

        return $machines;
    }

    /**
     * Returns machine with corresponding name
     *
     * @return Machine
     */
    public function getMachine($name)
    {
        // Make sure spec file is readable
        $pathSpec = sprintf('%s/etc/paastrami/%s.json', $this->getDirectory(), $name);
        if (!is_readable($pathSpec)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Could not read machine specification file - machine=%s, file=%s',
                    $name,
                    $pathSpec
                )
            );
        }

        // Decode spec file
        $spec = json_decode(file_get_contents($pathSpec), true);
        if ($spec === false || $spec === null) {
            throw new \RuntimeException(
                sprintf(
                    'Machine specification file format is invalid - machine=%s, file=%s',
                    $name,
                    $pathSpec
                )
            );
        }

        return $spec;
    }

    /**
     * Returns environment's sites
     *
     * @return array
     */
    public function getSites()
    {
        // Directory holding list of sites (one file per site)
        $dirSites = sprintf('%s/etc/paastrami/sites', $this->getDirectory());

        // Extract sites names and branches
        $sites = array();
        $filesSites = glob($dirSites.'/*');
        foreach ($filesSites as $file) {
            $sites[basename($file)] = trim(file_get_contents($file));
        }

        return $sites;
    }

    /**
     * Deletes a site to environment
     *
     * @param string $name Site name
     *
     * @throws \InvalidArgumentException when site does not exist
     */
    public function removeSite($name)
    {
        // Check if site exists
        if (!$this->siteExists($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Site does not exist - site=%s, environment=%s, platform=%s',
                    $name,
                    $this->getName(),
                    $this->getPlatform()->getName()
                )
            );
        }

        // Delete site's file
        $pathSite = sprintf('%s/etc/paastrami/sites/%s', $this->getDirectory(), $name);
        $fs = new Filesystem();
        $fs->remove($pathSite);

        // Reprovision machines
        $this->provision();

        return true;
    }

    /**
     * Adds a site to environment
     *
     * @param string $name Site name
     *
     * @return array List of sites added (main site and dependencies)
     */
    public function addSite($site, $branch = 'master')
    {
        // Check if site exists
        if ($this->siteExists($site)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Site already exists - site=%s, environment=%s, platform=%s',
                    $site,
                    $this->getName(),
                    $this->getPlatform()->getName()
                )
            );
        }

        // Create dependencies
        $sitesAdded = array();
        $dependencies = $this->getPlatform()->getSiteDependencies($site);
        foreach ($dependencies as $dependency) {
            $pathDependency = sprintf('%s/etc/paastrami/sites/%s', $this->getDirectory(), $dependency);
            if (!file_exists($pathDependency)) {
                if (file_put_contents($pathDependency, 'master') === false) {
                    throw new \RuntimeException(
                        sprintf(
                            'Dependency could not be added - dependency=%s, branch=master, site=%s, environment=%s, platform=%s',
                            $dependency,
                            $site,
                            $this->getName(),
                            $this->getPlatform()->getName()
                        )
                    );
                } else {
                    $sitesAdded[] = $dependency;
                }
            }
        }

        // Create site file
        $pathSite = sprintf('%s/etc/paastrami/sites/%s', $this->getDirectory(), $site);
        if (file_put_contents($pathSite, $branch) === false) {
            throw new \RuntimeException(
                sprintf(
                    'Site could not be added - site=%s, branch=%s, environment=%s, platform=%s',
                    $site,
                    $branch,
                    $this->getName(),
                    $this->getPlatform()->getName()
                )
            );
        }

        // Reprovision machines
        $this->provision();

        return array_merge(array($site), $sitesAdded);
    }

    /**
     * Changes site's branch and reprovisions machines
     *
     * @param string $site   Site name
     * @param string $branch Branch name
     */
    public function changeSiteBranch($site, $branch)
    {
        // Check if site exists
        if (!$this->siteExists($site)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Site does not exist - site=%s, environment=%s, platform=%s',
                    $site,
                    $this->getName(),
                    $this->getPlatform()->getName()
                )
            );
        }

        // Create site file
        $pathSite = sprintf('%s/etc/paastrami/sites/%s', $this->getDirectory(), $site);
        if (file_put_contents($pathSite, $branch) === false) {
            throw new \RuntimeException(
                sprintf(
                    'Site branch could not be changed - site=%s, branch=%s, environment=%s, platform=%s',
                    $site,
                    $branch,
                    $this->getName(),
                    $this->getPlatform()->getName()
                )
            );
        }

        // Reprovision machines
        $this->provision();

        return true;
    }

    /**
     * Check if site exists in environment
     *
     * @param string $site Site
     *
     * @return bool True if site exists in environment
     */
    public function siteExists($site)
    {
        // Directory holding list of sites (one file per site)
        $pathSite = sprintf('%s/etc/paastrami/sites/%s', $this->getDirectory(), $site);

        return is_readable($pathSite);
    }
}
