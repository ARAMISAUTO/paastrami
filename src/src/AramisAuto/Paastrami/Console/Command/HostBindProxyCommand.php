<?php

namespace AramisAuto\Paastrami\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class HostBindProxyCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('host:bind-proxy')
            ->setDescription(
                'Génération de la configuration Bind '.
                'et Apache pour environnements des plateformes existantes'
            )
            ->addArgument('domain', null, InputArgument::REQUIRED, 'Domaine')
            ->addOption('working-directory', null, InputOption::VALUE_REQUIRED, 'Répertoire de travail', '.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Récupération de tous les fichiers paastramis.json
        $finder = new Finder();
        $filesPaastrami = $finder
            ->files()
            ->name('paastrami.json')
            ->in($input->getOption('working-directory').'/platforms/*/environments/*/etc');
        if (!$filesPaastrami->count()) {
            throw new \RuntimeException(
                sprintf(
                    'Aucun fichier paastrami.json n\'a été trouvé - working-directory="%s"',
                    $input->getOption('working-directory')
                )
            );
        }

        // IP du serveur hôte
        $ipHost = $this->getHostIp();

        // Log
        $output->writeln(
            sprintf(
                '<info>Génération de la configuration Bind et Apache des environnements existants</info>'.
                ' - working-directory="%s", domain="%s", envCount="%d"',
                $input->getOption('working-directory'),
                $input->getArgument('domain'),
                $filesPaastrami->count()
            )
        );

        // Suppression de la configuration existante des plateformes
        $platforms = array();
        foreach ($filesPaastrami as $file) {
            $platforms[] = basename(dirname(dirname(dirname(dirname(dirname($file->getPathname()))))));
        }
        $platforms = array_unique($platforms);
        foreach ($platforms as $platform) {
            $this->doBindCleanup($input->getArgument('domain'), $platform, $output);
            $this->doApacheCleanup($input->getArgument('domain'), $platform, $output);
        }

        // Génération des configurations pour chaque environnement
        foreach ($filesPaastrami as $file) {
            // Lecture de la spécification de l'environnement
            $specEnvironment = json_decode(file_get_contents($file->getPathname()), true);
            if (false == $specEnvironment) {
                $output->writeln(
                    sprintf(
                        '<error>Le fichier paastrami.json ne peut pas être lu et est ignoré</error> - file="%s"',
                        $file->getPathname()
                    )
                );
                continue;
            }

            // Récupération du serial DNS avant suppression du fichier db
            $serial = $this->getDnsDbSerial(
                sprintf(
                    '/etc/bind/db.%s.%s.%s',
                    $input->getArgument('domain'),
                    $specEnvironment['platform'],
                    $specEnvironment['name']
                )
            );

            // Génération de la configuration Bind de l'environnement
            $this->doBindGenerate(
                $specEnvironment['name'],
                $specEnvironment['platform'],
                $input->getArgument('domain'),
                array_keys($specEnvironment['sites']),
                $ipHost,
                $serial,
                $output
            );

            // Génération de la configuration Apache
            $this->doApacheGenerate(
                $specEnvironment['name'],
                $specEnvironment['platform'],
                $input->getArgument('domain'),
                $specEnvironment['vms'][0]['ip'],
                $output
            );
        }

        // Génération de la configuration resolv.conf
        $this->doResolvconf($ipHost, $output);

        // Redémarrage de Bind
        $output->writeln('<info>Redémarrage de Bind</info>');
        $process = new Process('service bind9 restart');
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Le redémarrage de Bind a échoué : ' . $process->getErrorOutput());
        } else {
            $output->writeln($process->getOutput());
        }

        // Redémarrage de Apache
        $output->writeln('<info>Redémarrage de Apache</info>');
        $process = new Process('service apache2 restart');
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        } else {
            $output->writeln($process->getOutput());
        }
    }

    private function doBindGenerate($environment, $platform, $domain, array $sites, $ipHost, $serial, OutputInterface $output)
    {
        // DNS Zone
        $dnsZonePath = sprintf('/etc/bind/zones.%s.%s.%s', $domain, $platform, $environment);
        $dnsZone = <<<EOT
zone "${environment}.${platform}.${domain}" in {
    type master;
    file "/etc/bind/db.${domain}.${platform}.${environment}";
};
EOT;

        // DNS DB
        $dnsDbPath = sprintf('/etc/bind/db.%s.%s.%s', $domain, $platform, $environment);
        $dnsDb = <<<EOT
\$TTL 5m
\$ORIGIN ${environment}.${platform}.${domain}.
@     IN    SOA    ns1.${environment}.${platform}.${domain}. hostmaster.${environment}.${platform}.${domain} ( ${serial} 600 600 600 600 )
      IN    NS     ns1.${environment}.${platform}.${domain}.
ns1   IN    A      ${ipHost}
EOT;
        foreach ($sites as $site) {
            $dnsDb .= sprintf("\n%s    IN    A    %s", $site, $ipHost);
        }

        // Log
        $output->writeln(
            sprintf(
                '<info>Génération de la configuration Bind</info>'.
                ' - domain="%s", platform="%s", environment="%s", sites="%s", hostIp="%s", dnsSerial="%s"',
                $domain,
                $platform,
                $environment,
                implode(',', $sites),
                $ipHost,
                $serial
            )
        );

        // DNS local
        $dnsLocal = file('/etc/bind/named.conf.local');
        $dnsLocal[] = sprintf('include "/etc/bind/zones.%s.%s.%s";', $domain, $platform, $environment);
        $dnsLocal = array_unique($dnsLocal);

        // Write Bind configuration
        file_put_contents($dnsZonePath, $dnsZone);
        file_put_contents($dnsDbPath, $dnsDb);
        file_put_contents('/etc/bind/named.conf.local', implode("\n", $dnsLocal));
    }

    private function doBindCleanup($domain, $platform, OutputInterface $output)
    {
        // Log
        $output->writeln(
            sprintf(
                '<info>Nettoyage de la configuration Bind de la plateforme</info> - domain="%s", platform="%s"',
                $domain,
                $platform
            )
        );

        // Utils
        $finder = new Finder();
        $fs = new Filesystem();

        // Suppression des fichiers de configuration de la plateforme
        $filesDns = $finder
            ->files()
            ->name(sprintf('*.%s.%s.*', $domain, $platform))
            ->in('/etc/bind');
        $fs->remove($filesDns);

        // Purge des inclusions dans named.conf.local
        $local = file('/etc/bind/named.conf.local');
        $localPurged = array();
        foreach ($local as $line) {
            $matches = array();
            if (!preg_match(sprintf('/%s\.%s/', preg_quote($domain), preg_quote($platform)), $line)) {
                $localPurged[] = $line;
            }
        }
        file_put_contents('/etc/bind/named.conf.local', implode("\n", $localPurged));
    }

    private function doApacheGenerate($environment, $platform, $domain, $ipEntrypoint, OutputInterface $output)
    {
        // Log
        $output->writeln(
            sprintf(
                '<info>Génération de la configuration Apache</info>'.
                '- domain="%s", plaform="%s", environment="%s", ipBackend="%s"',
                $domain,
                $platform,
                $environment,
                $ipEntrypoint
            )
        );

        // Génération du vhost
        $conf = <<<EOT
<VirtualHost *:80>
  ServerName ${environment}.${platform}.${domain}
  ServerAlias *.${environment}.${platform}.${domain}

  ProxyPass / http://${ipEntrypoint}/
  ProxyPassReverse / http://${ipEntrypoint}/
  ProxyPreserveHost On
</VirtualHost>
EOT;

        file_put_contents(
            sprintf('/etc/apache2/sites-available/%s.%s.%s.conf', $environment, $platform, $domain),
            $conf
        );

        $commands = array('a2ensite '.sprintf('%s.%s.%s', $environment, $platform, $domain), 'service apache2 restart');
        foreach ($commands as $command) {
            $process = new Process($command);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException(
                    sprintf('Une commande a échoué : %s - command="%s"', $process->getErrorOutput(), $command)
                );
            }
        }
    }

    public function doApacheCleanup($domain, $platform, OutputInterface $output)
    {
        // Log
        $output->writeln(
            sprintf(
                '<info>Nettoyage de la configuration Apache de la plateforme</info> - domain="%s", platform="%s"',
                $domain,
                $platform
            )
        );

        // Utils
        $finder = new Finder();
        $fs = new Filesystem();

        // Suppression des fichiers de configuration
        $files = $finder
            ->files()
            ->name(sprintf('*.%s.%s.conf', $platform, $domain))
            ->in('/etc/apache2/sites-enabled/')
            ->in('/etc/apache2/sites-available/');
        $fs->remove($files);
    }

    private function doResolvconf($ipHost, OutputInterface $output)
    {
        $resolvconf = file_get_contents('/etc/resolvconf/resolv.conf.d/head');
        if (!preg_match(sprintf('/nameserver %s/', preg_quote($ipHost)), $resolvconf)) {
            $output->writeln(
                sprintf('<info>Génération de la configuration resolvconf</info> - ipHost="%s"', $ipHost)
            );
            $resolvconf .= "\nnameserver ${ip}\n";
            file_put_contents('/etc/resolvconf/resolv.conf.d/head', $resolvconf);
            $process = new Process('service resolvconf restart');
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            } else {
                $output->writeln($process->getOutput());
            }
        }
    }

    private function getHostIp()
    {
        $ipHost = trim(shell_exec("ip route get 8.8.8.8 | awk '{ print \$NF; exit }'"));
        if (!filter_var($ipHost, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException(
                sprintf('Impossible de récupérer l\'IP publique serveur hôte - ipFound="%s"', $ipHost)
            );
        }

        return $ipHost;
    }

    private function getDnsDbSerial($dnsDbPath)
    {
        if (!is_readable($dnsDbPath)) {
            return date('YmdH');
        }

        $dnsDb = file_get_contents($dnsDbPath);
        $matches = array();
        $found = preg_match('/(\d{10})/', $dnsDb, $matches);
        if (!$found) {
            return date('YmdH');
        } else {
            $serial = $matches[1];

            // Partie du serial "date" (Ymd)
            $partDate = substr($serial, 0, strlen($serial) - 2);

            // Partie du serial "incrémentale"
            $partInc = (int)substr($serial, -2);

            // Génération du nouveau serial
            if (date('Ymd') != $partDate) {
                $partDate = date('Ymd');
                $partInc = 0;
            } else {
                $partInc += 1;
            }

            // Préfixage d'un zéro si nécessaire
            if ($partInc < 10) {
                $partInc = '0'.$partInc;
            }

            return $partDate.$partInc;
        }

        return date('YmdH');
    }
}
