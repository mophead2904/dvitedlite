<?php

namespace DrupalProject\composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
  public function activate(Composer $composer, IOInterface $io) {}
  public function deactivate(Composer $composer, IOInterface $io) {}
  public function uninstall(Composer $composer, IOInterface $io) {}

  public static function getSubscribedEvents()
  {
    return [
      ScriptEvents::PRE_INSTALL_CMD => "onPreCmd",
      ScriptEvents::PRE_UPDATE_CMD => "onPreCmd",
      ScriptEvents::POST_INSTALL_CMD => "onPostCmd",
      ScriptEvents::POST_UPDATE_CMD => "onPostCmd",
    ];
  }

  public function onPreCmd($event)
  {
    \Composer\Config::disableProcessTimeout();
    ScriptHandler::checkComposerVersion($event);
  }

  public function onPostCmd($event)
  {
    ScriptHandler::createRequiredFiles($event);
  }
}
