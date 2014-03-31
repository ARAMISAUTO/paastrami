<?php
namespace AramisAuto\Paastrami\Console;

use AramisAuto\Paastrami\Console\Command\EnvDestroyCommand;
use AramisAuto\Paastrami\Console\Command\EnvHaltCommand;
use AramisAuto\Paastrami\Console\Command\EnvInitCommand;
use AramisAuto\Paastrami\Console\Command\EnvListCommand;
use AramisAuto\Paastrami\Console\Command\EnvUpCommand;
use AramisAuto\Paastrami\Console\Command\HostApacheCommand;
use AramisAuto\Paastrami\Console\Command\HostBindProxyCommand;
use AramisAuto\Paastrami\Console\Command\HostBootstrapCommand;
use AramisAuto\Paastrami\Console\Command\PlatformBuildCommand;
use AramisAuto\Paastrami\Console\Command\PlatformInitCommand;
use AramisAuto\Paastrami\Console\Command\PlatformListCommand;
use AramisAuto\Paastrami\Console\Command\PlatformPullCommand;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('paastrami', '0.1.0');
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        // host:*
        $commands[] = new HostBootstrapCommand();
        $commands[] = new HostBindProxyCommand();

        // platform:*
        $commands[] = new PlatformBuildCommand();
        $commands[] = new PlatformInitCommand();
        $commands[] = new PlatformListCommand();
        $commands[] = new PlatformPullCommand();

        // env:*
        $commands[] = new EnvDestroyCommand();
        $commands[] = new EnvHaltCommand();
        $commands[] = new EnvInitCommand();
        $commands[] = new EnvListCommand();
        $commands[] = new EnvUpCommand();

        return $commands;
    }
}
