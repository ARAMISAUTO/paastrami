platforms
    <platform>
        environments
            <environment>
                Vagrantfile
                sites

# Name ?
shoes
chaussure
bess (cf. breaking the waves)

# New features
- Multi project

# Command set
- chaussure self-bootstrap                           - Installation des dépendances sur la machine hôte
- chaussure bootstrap $platform $platformGitUrl      - Création de l'arborescence de base pour la plateforme et définition du dépôt Git (dans $platform/chaussure.json)
- chaussure build $platform $env $sites              - Instanciation d'un environnement d'une plateforme : provisionning des machines virtuelles, génération des configuration Bind et Apache
- chaussure start $platform [$envs]                  - Démarrage des machines virtuelles d'une plateforme ou d'une série d'environnements
- chaussure stop $platform [$envs]                   - Arrêt des machines virtuelles d'une plateforme ou d'une série d'environnement
- chaussure destroy $platform [$envs]                - Suppression d'une plateforme ou d'une série d'environnements
- chaussure status $platform                         - Affiche l'état des environnements d'une plateforme

# Glossary
- Domain
    - Platform
        - Environment
            - Site

# File tree
## legacy
- /
    - platforms
        - $platform
            - $environment
                - etc
                - srv
                - var
## new
- /
    - platforms
        - $platform
            - repository
                - etc
                    - paastrami
                        - sites (?)
                - provisionners
                     - salt
                        - srv
                            - salt
                            - pillar
                        - etc
                            - roots.conf
                            - minion.conf-dist
            - $environment
                - var

# Parameters
- Home directory
- Environment name (eg. test1)
- Platform name (eg. platform.aramisauto.com)
- Workspace ($Home/$Environment)
- projectUrl (salt sources + Vagrantfile)
- ipRange (for VMs)

# Steps
- install host dependencies
- create workspace
- clone projectUrl in workspace
- activate sites and dependencies in build-enabled
- create etc/chaussure directory with all variables
- recursively apply variables do -dist files in env
- generate Vagrantfile
- vagrant up each vm
- generate bind configuration
- generate apache configuration

# DNS
<site>.<environment>.<platform>.<domain>
<v5>.<1>.<paas>.<aramisauto.com>

# Commands
host:boostrap
host:bind-proxy <platform> <environment>
host:unbind-proxy <platform> <environment>

platform:init

env:init

# Workflows

## Installation

host:bootstrap - Installation des dépendances sur l'hôte
platform:init  - Création d'une plateforme
platform:build - Création des images

## Création d'un environnement

Un environnement va avoir pour nom l'identifiant du ticket sur lequel on veut travailler. eg. 1161 pour http://redmine.it.aramisauto.com/issues/1161

env:init - Création de l'environnement, récupération des sources des applications, configuration
host:bind-proxy - Génération de la configuration Apache / Bind sur l'hôte

-- On peut accéder à la homepage de l'environnement

# Cas d'utilisation

* Regénération de la configuration de l'hôte - host:bind-proxy

* création d'une plateforme      - platform:init
* démarrage d'une plateforme     - platform:up
* arrêt d'une plateforme         - platform:halt
* suppression d'une plateforme   - platform:destroy
* vérification de l'état         - platform:check
* génération des images          - platform:build

* création d'un environnement    - env:init
* démarrage d'un environnement   - env:up
* suppression d'un environnement - env:destroy
* arrêt d'un environnement       - env:halt

# TODO
réorganiser les file_roots
plugin redmine pour lancer la création d'environnement
mirror github (avec hook pour récupérer les modifs en live - cf. intégration redmine)
faire un schéma d'architecture
apt proxy sur le serveur de dev (via boostrap ?)
bootstrap du host avec Salt
homepage data :
 * phpmyadmin
 * rabbitmq management [x]
 * es HQ [x]
unbind-proxy
<site>.<env>.<platform>.platforms.aramisauto.com
    v5.env1.main.platforms.aramisauto.com
Génération des tâches rundeck à partir des tâches symfony
api qui liste les plateformes et les environnements
suppression des environnements
phar
documentation pépite

homepage plateforme
homepage environnement

platform:build -  TODO : on devrait générer tous les -dist ça permettrait d'être agnostique au provisionner
platform:build - Specified SLS 'data.varnish' in environment 'com.aramisauto.local' is not available on the salt master
    => parce que c'est un dist !

intégrer packer à paastrami : https://github.com/flomotlik/packer-example
    host:bootstrap installe packer
    platform:init
    sélection du builder à partir du nom de la box :
        packer_aramis       => doPacker('aramis')       => repository/builders/packer/aramis.json
        packer_aramis_front => doPacker('aramis_front') => repository/builders/packer/aramis_front.json

fix BUILD FAILED
/var/www/v5/build.xml:4: Cannot find /var/www/v5/vendor/constructions-incongrues/ananas-build-toolkit/modules/toolkit/module.xml imported from /var/www/v5/build.xml
because :

                    [exec]   [Symfony\Component\Process\Exception\ProcessTimedOutException]
                       [exec]   The process "git clone 'git@github.com:ARAMISAUTO/v5MediaAssets.git' '/var/www/v5/vendor/aramisauto/v5MediaAssets' && cd '/var/www/v5/vendor/aramisauto/v5MediaAssets' && git remote add composer 'git@github.com:ARAMISAUTO/v5MediaAssets.git' && git fetch composer" exceeded the timeout of 300 seconds.
                       [exec]


start    : 14h34
end      : 14h55
duration : 21m

# Amélioration des performances
Mirroirs des dépôts Github
Mirroir APT
VM préprovisionnées avec Packer

Entity\Vagrantfile
    ::fromFile($path)
    ::__construct($contents)
    ->getMachines() : []
