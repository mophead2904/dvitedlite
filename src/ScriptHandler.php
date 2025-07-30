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

class ScriptHandler
{
  public static function createRequiredFiles(Event $event)
  {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $event->getIO()->write("=== dvitedlite DEBUG START ===");
    $event->getIO()->write("Current working directory: " . getcwd());
    $event->getIO()->write("Drupal root found at: " . $drupalRoot);

    // Get the path to this package's files
    $vendorDir = $event->getComposer()->getConfig()->get("vendor-dir");
    $packageDir = $vendorDir . "/mophead2904/dvitedlite";

    $event->getIO()->write("Vendor directory: " . $vendorDir);
    $event->getIO()->write("Package directory: " . $packageDir);
    $event->getIO()->write("Package directory exists: " . ($fs->exists($packageDir) ? "YES" : "NO"));

    if ($fs->exists($packageDir)) {
      $event->getIO()->write("Contents of package directory:");
      $contents = scandir($packageDir);
      foreach ($contents as $item) {
        if ($item !== "." && $item !== "..") {
          $event->getIO()->write("  - " . $item);
        }
      }

      $customDir = $packageDir . "/src/custom";
      $event->getIO()->write("Custom directory: " . $customDir);
      $event->getIO()->write("Custom directory exists: " . ($fs->exists($customDir) ? "YES" : "NO"));

      if ($fs->exists($customDir)) {
        $event->getIO()->write("Contents of custom directory:");
        $customContents = scandir($customDir);
        foreach ($customContents as $item) {
          if ($item !== "." && $item !== "..") {
            $event->getIO()->write("  - " . $item);
          }
        }
      }
    }

    $dirs = ["modules", "profiles", "themes"];

    // Required for unit testing
    foreach ($dirs as $dir) {
      $dirPath = $drupalRoot . "/" . $dir;
      $event->getIO()->write("Checking directory: " . $dirPath);
      if (!$fs->exists($dirPath)) {
        $fs->mkdir($dirPath);
        $fs->touch($dirPath . "/.gitkeep");
        $event->getIO()->write("✓ Created directory: " . $dirPath);
      } else {
        $event->getIO()->write("✓ Directory already exists: " . $dirPath);
      }
    }

    // Copy settings.php from package (if it exists and destination doesn't)
    $sourceSettings = $packageDir . "/src/custom/settings.php";
    $destSettings = $drupalRoot . "/sites/default/settings.php";
    $event->getIO()->write("--- SETTINGS.PHP ---");
    $event->getIO()->write("Source: " . $sourceSettings);
    $event->getIO()->write("Destination: " . $destSettings);
    $event->getIO()->write("Source exists: " . ($fs->exists($sourceSettings) ? "YES" : "NO"));
    $event->getIO()->write("Destination exists: " . ($fs->exists($destSettings) ? "YES" : "NO"));

    if (!$fs->exists($destSettings) && $fs->exists($sourceSettings)) {
      $fs->copy($sourceSettings, $destSettings);
      $fs->chmod($destSettings, 0666);
      $event->getIO()->write("✓ Created a sites/default/settings.php file with chmod 0666");
    } else {
      $event->getIO()->write("⚠ Skipped settings.php (destination exists or source missing)");
    }

    // Copy settings.local.php from package
    $sourceSettingsLocal = $packageDir . "/src/custom/settings.local.php";
    $destSettingsLocal = $drupalRoot . "/sites/default/settings.local.php";
    $event->getIO()->write("--- SETTINGS.LOCAL.PHP ---");
    $event->getIO()->write("Source: " . $sourceSettingsLocal);
    $event->getIO()->write("Destination: " . $destSettingsLocal);
    $event->getIO()->write("Source exists: " . ($fs->exists($sourceSettingsLocal) ? "YES" : "NO"));
    $event->getIO()->write("Destination exists: " . ($fs->exists($destSettingsLocal) ? "YES" : "NO"));

    if (!$fs->exists($destSettingsLocal) && $fs->exists($sourceSettingsLocal)) {
      $fs->copy($sourceSettingsLocal, $destSettingsLocal);
      $fs->chmod($destSettingsLocal, 0666);
      $event->getIO()->write("✓ Created a sites/default/settings.local.php file with chmod 0666");
    } else {
      $event->getIO()->write("⚠ Skipped settings.local.php (destination exists or source missing)");
    }

    // Copy local.services.yml from package
    $sourceServices = $packageDir . "/src/custom/local.services.yml";
    $destServices = $drupalRoot . "/sites/local.services.yml";
    $event->getIO()->write("--- LOCAL.SERVICES.YML ---");
    $event->getIO()->write("Source: " . $sourceServices);
    $event->getIO()->write("Destination: " . $destServices);
    $event->getIO()->write("Source exists: " . ($fs->exists($sourceServices) ? "YES" : "NO"));
    $event->getIO()->write("Destination exists: " . ($fs->exists($destServices) ? "YES" : "NO"));

    if (!$fs->exists($destServices) && $fs->exists($sourceServices)) {
      $fs->copy($sourceServices, $destServices);
      $event->getIO()->write("✓ Created a sites/local.services.yml file");
    } else {
      $event->getIO()->write("⚠ Skipped local.services.yml (destination exists or source missing)");
    }

    // Create the files directory with chmod 0777
    $filesDir = $drupalRoot . "/sites/default/files";
    $event->getIO()->write("--- FILES DIRECTORY ---");
    $event->getIO()->write("Files directory: " . $filesDir);
    $event->getIO()->write("Files directory exists: " . ($fs->exists($filesDir) ? "YES" : "NO"));

    if (!$fs->exists($filesDir)) {
      $oldmask = umask(0);
      $fs->mkdir($filesDir, 0777);
      umask($oldmask);
      $event->getIO()->write("✓ Created a sites/default/files directory with chmod 0777");
    } else {
      $event->getIO()->write("✓ Files directory already exists");
    }

    $event->getIO()->write("=== dvitedlite DEBUG END ===");
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
