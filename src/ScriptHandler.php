<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ScriptHandler
{
  public static function createRequiredFiles(Event $event)
  {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    // Get the path to this package's files
    $vendorDir = $event->getComposer()->getConfig()->get("vendor-dir");
    $packageDir = $vendorDir . "/mophead2904/speedster";

    $dirs = ["modules", "profiles", "themes"];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($drupalRoot . "/" . $dir)) {
        $fs->mkdir($drupalRoot . "/" . $dir);
        $fs->touch($drupalRoot . "/" . $dir . "/.gitkeep");
      }
    }

    // Copy settings.php from package (if it exists and destination doesn't)
    $sourceSettings = $packageDir . "/src/custom/settings.php";
    $destSettings = $drupalRoot . "/sites/default/settings.php";
    if (!$fs->exists($destSettings) && $fs->exists($sourceSettings)) {
      $fs->copy($sourceSettings, $destSettings);
      $fs->chmod($destSettings, 0666);
      $event->getIO()->write("Created a sites/default/settings.php file with chmod 0666");
    }

    // Copy settings.local.php from package
    $sourceSettingsLocal = $packageDir . "/src/custom/settings.local.php";
    $destSettingsLocal = $drupalRoot . "/sites/default/settings.local.php";
    if (!$fs->exists($destSettingsLocal) && $fs->exists($sourceSettingsLocal)) {
      $fs->copy($sourceSettingsLocal, $destSettingsLocal);
      $fs->chmod($destSettingsLocal, 0666);
      $event->getIO()->write("Created a sites/default/settings.local.php file with chmod 0666");
    }

    // Copy local.services.yml from package
    $sourceServices = $packageDir . "/src/custom/local.services.yml";
    $destServices = $drupalRoot . "/sites/local.services.yml";
    if (!$fs->exists($destServices) && $fs->exists($sourceServices)) {
      $fs->copy($sourceServices, $destServices);
      $event->getIO()->write("Created a sites/local.services.yml file");
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($drupalRoot . "/sites/default/files")) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . "/sites/default/files", 0777);
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/files directory with chmod 0777");
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   */
  public static function checkComposerVersion(Event $event)
  {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === "@package_version@" || $version === "@package_branch_alias_version@") {
      $io->writeError("<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>");
    } elseif (Comparator::lessThan($version, "1.0.0")) {
      $io->writeError("<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.");
      exit(1);
    }
  }
}
