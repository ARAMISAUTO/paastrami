<?php
namespace AramisAuto\Paastrami\Console;

use AramisAuto\Paastrami\Console\Command\EnvHaltCommand;
use AramisAuto\Paastrami\Console\Command\EnvInitCommand;
use AramisAuto\Paastrami\Console\Command\EnvUpCommand;
use AramisAuto\Paastrami\Console\Command\HostApacheCommand;
use AramisAuto\Paastrami\Console\Command\HostBindProxyCommand;
use AramisAuto\Paastrami\Console\Command\HostBootstrapCommand;
use AramisAuto\Paastrami\Console\Command\PlatformBuildCommand;
use AramisAuto\Paastrami\Console\Command\PlatformInitCommand;
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
        $commands[] = new PlatformPullCommand();

        // env:*
        $commands[] = new EnvInitCommand();
        $commands[] = new EnvUpCommand();
        $commands[] = new EnvHaltCommand();

        return $commands;
    }
}
