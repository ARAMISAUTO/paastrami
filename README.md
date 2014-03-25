# Glossary
- Domain
    - Platform
        - Environment
            - Site

# DNS
<site>.<environment>.<platform>.<domain>
<v5>.<1>.<paas>.<aramisauto.com>

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
utiliser https://github.com/willdurand/nmap
réorganiser les file_roots
plugin redmine pour lancer la création d'environnement
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
Permettre de rendre les box générées accessibles en HTTP pour la récupération en Vagrantfile
env:init : meilleure usage du preprocesseur

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

# Entities

Entity\Vagrantfile
    ::fromFile($path)
    ::__construct($contents)
    ->getMachines() : []

Repository
    ::fromFilesystem($path)
    - Path
    - Platforms
        - Platform
        - Environments
            - Environment
                - Name
                - Path
        - Machines
            - Machine
                - Box
                - Builder
                - IP
                - Name
                - Provisioner

Builder
    ->build()
