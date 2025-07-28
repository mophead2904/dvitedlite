<?php

namespace DrupalProject\composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use DrupalProject\composer\Commands\InitCommand;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [
            new InitCommand(),
        ];
    }
}
