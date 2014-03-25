<?php

namespace AramisAuto\Paastrami\Entity;

class Vagrantfile
{
    private $data;

    public function __construct($string)
    {
        // Valeurs par défaut
        $this->data = array('machines' => null);

        // Extraction des données
        $this->data = array_merge($this->data, $this->analyze($string));
    }

    public static function fromFile($pathFile)
    {
        // Sanity checks
        if (!is_readable($pathFile)) {
            throw new \InvalidArgumentException(sprintf('Le fichier ne peut être lu - file="%s"', $pathFile));
        }

        return new self(file_get_contents($pathFile));
    }

    public static function fromString($string)
    {
        return new self($string);
    }

    public function getData()
    {
        return $this->data;
    }

    protected function analyze($string)
    {
        // Liste des machines
        $matches = array();
        $pattern = '/config\.vm\.define "(\w+)" do \|\w+\|/';
        $found = preg_match_all($pattern, $string, $matches);
        if (!$found) {
            throw new \LogicException(
                sprintf('Aucune déclaration de machine n\' a pu être extraite - pattern="%s"', $pattern)
            );
        }
        $machines = array();
        foreach ($matches[1] as $vmName) {
            $machines[] = array('name' => $vmName);
        }

        // Récupération de la box associée à chaque machine
        $patternBox = '/(%s).vm.box ?= ?"(.+)"/';

        // Box par défaut
        $matches = array();
        $boxDefault = false;
        if (preg_match_all(sprintf($patternBox, 'config'), $string, $matches)) {
            $boxDefault = $matches[2][0];
        }

        // On tente d'abord d'obtenir le nom de la boxe explicitement lié à la machine
        // puis on se rabat sur la box par défaut
        for ($i = 0; $i < count($machines); $i++) {
            $matches = array();
            if (preg_match_all(sprintf($patternBox, $machines[$i]['name']), $string, $matches)) {
                $machines[$i]['box'] = $matches[2][0];
            } else {
                if (false !== $boxDefault) {
                    $machines[$i]['box'] = $boxDefault;
                } else {
                    throw new \LogicException(
                        sprintf(
                            'Impossible d\'associer une box à la machine et pas de box par défaut - machine="%s", pattern="%s"',
                            $machines[$i]['name'],
                            sprintf($patternBox, $machines[$i]['name'])
                        )
                    );
                }
            }
        }

        return array('machines' => $machines);
    }
}
