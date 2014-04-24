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

Un environnement va avoir pour nom l'identifiant du ticket sur lequel on veut travailler. eg. 1161 pour http://redmine.xxx/issues/1161

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
intégration Yammer
apt proxy sur le serveur de dev (via boostrap ?)
bootstrap du host avec Salt
homepage data :
 * phpmyadmin
 * rabbitmq management [x]
 * es HQ [x]
Génération des tâches rundeck à partir des tâches symfony
api qui liste les plateformes et les environnements (avec API blueprint !)
phar
documentation pépite
homepage plateforme
homepage environnement
Permettre de rendre les box générées accessibles en HTTP pour la récupération en Vagrantfile
avoir un sls de build dédié à la plateforme, include par le sls de build d'environnement (éviter opérations ES par exemple)
voir ce qu'on peut faire avec Vagrant Cloud
trop SIGINT pour éteindre correctement les VM
passer ABT en install --prefer-dist
utiliser Twig pour le rendering
installer Mailcatcher ?
.deb qui package le tout !
Permettre l'accès au log et en SSH aux environnements
Passer tout en anglais
env:list
    - liste des sites
    - date de création (ou dernière mise à jour ?)
env:reload
env:urls ?
logs avec splunk
déplacer /root/Virtualbox VMS sur une partition suffisamment grande
Les VM ne sont pas à l'heure
Arrêter l'exécution de platform:build et env:up en cas de soucis avec Salt
paastrami self-update
utiliser http://gnugat.github.io/2014/04/09/sf2-console-component-by-example.html#consolelogger pour le logging dans les classes
permettre le passage de métadonnées à env:create
Apr 17 09:42:59 srvdev01 named[6422]: /etc/bind/db.platforms.aramisauto.com.staging.1414:9: file does not end with newline
Intégration Yammer


# Amélioration des performances

Mirroirs des dépôts Github
Mirroir APT
VM préprovisionnées avec Packer

# Dimensionnement

front : 10,3Go DD
data : 3Go DD

env moyen : ~15Go

RAM ?

# Services
Service :
 * type (mysql)
 * name (weekly)
 * host (ip / hostname)
 * port (3306)

 service:add
 service:remove
 service:list
