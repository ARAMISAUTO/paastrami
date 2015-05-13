<?php
namespace AramisAuto\Paastrami\Console;

use AramisAuto\Paastrami\Console\Command\EnvDestroyCommand;
use AramisAuto\Paastrami\Console\Command\EnvHaltCommand;
use AramisAuto\Paastrami\Console\Command\EnvInitCommand;
use AramisAuto\Paastrami\Console\Command\EnvListCommand;
use AramisAuto\Paastrami\Console\Command\EnvSshCommand;
use AramisAuto\Paastrami\Console\Command\EnvUpCommand;
use AramisAuto\Paastrami\Console\Command\HostBootstrapCommand;
use AramisAuto\Paastrami\Console\Command\PlatformBuildCommand;
use AramisAuto\Paastrami\Console\Command\PlatformInitCommand;
use AramisAuto\Paastrami\Console\Command\PlatformListCommand;
use AramisAuto\Paastrami\Console\Command\PlatformPullCommand;
use AramisAuto\Paastrami\Console\Command\SiteAddCommand;
use AramisAuto\Paastrami\Console\Command\SiteChangeBranchCommand;
use AramisAuto\Paastrami\Console\Command\SiteListCommand;
use AramisAuto\Paastrami\Console\Command\SiteRemoveCommand;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('paastrami', '0.1.0');
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

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
        $commands[] = new EnvSshCommand();

        // site:*
        $commands[] = new SiteAddCommand();
        $commands[] = new SiteChangeBranchCommand();
        $commands[] = new SiteListCommand();
        $commands[] = new SiteRemoveCommand();

        return $commands;
    }
}
