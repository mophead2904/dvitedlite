<?php

use Drupal\Core\Site\Settings;

/**
 * Implements hook_library_info_alter().
 */
function cga_library_info_alter(&$libraries, $extension)
{
  if ($extension !== "cga") {
    return;
  }

  $local = _vite_is_local();

  // Replace library paths so they are ready for either Dev or Prd.
  foreach ($libraries as $library => $settings) {
    if (!_shouldLibraryBeManagedByVite($settings)) {
      continue;
    }
    if (!empty($settings["css"])) {
      foreach ($settings["css"] as $type => $paths) {
        foreach ($paths as $path => $options) {
          if (_shouldAssetBeManagedByVite($path, $options)) {
            _vite_replace_library($libraries[$library]["css"][$type], $path, $options);
          }
        }
      }
    }
    if (!empty($settings["js"])) {
      foreach ($settings["js"] as $path => $options) {
        if (_shouldAssetBeManagedByVite($path, $options)) {
          _vite_replace_library($libraries[$library]["js"], $path, $options);
        }
      }
    }
  }

  // Exit if local development. Everything after will be for production.
  if ($local) {
    return;
  }

  // Remove the HMR client library.
  unset($libraries["hot-module-replacement"]);

  $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
  $manifest_path = $theme_path . "/dist/.vite/manifest.json";

  if (file_exists($manifest_path)) {
    $manifest_data = file_get_contents($manifest_path);
    $manifest = json_decode($manifest_data);

    \Drupal::logger("vite_manifest")->info("Vite manifest found at " . $manifest_path);
    if (json_last_error() !== JSON_ERROR_NONE) {
      \Drupal::logger("vite")->error("Invalid Vite manifest file");
      return;
    }
  } else {
    \Drupal::logger("vite_manifest")->warning("Vite manifest not found at " . $manifest_path);
    return;
  }

  foreach ($manifest as $key => $data) {
    // Ignore files that will be imported via js.
    if (preg_match("/^_/", $key)) {
      continue;
    }

    if (!empty($data->css)) {
      foreach ($data->css as $css_file) {
        // Search through each library.
        foreach ($libraries as $library => $settings) {
          foreach ($settings["js"] as $path => $options) {
            if (str_ends_with($path, $data->file)) {
              $libraries[$library]["css"]["component"]["dist/" . $css_file] = [];
            }
          }
        }
      }
    }
  }
}

function _vite_is_local(): bool
{
  // Check if HMR is enabled and dev server is running
  $hmr_enabled = Settings::get("hot_module_replacement", false);
  $dev_server_running = _is_vite_dev_server_running();

  \Drupal::logger("vite hmr")->info("HMR enabled: " . ($hmr_enabled ? "true" : "false"));
  \Drupal::logger("vite dev server")->info("Dev server running: " . ($dev_server_running ? "true" : "false"));

  return $hmr_enabled && $dev_server_running;
}

/**
 * Replace an asset path with one that works with Vite.
 *
 * @param  array  $library
 *                          The library to be altered.
 * @param  string  $path
 *                        The file path and name.
 * @param  array  $options
 *                          Any settings that were part of the original file's settings.
 */
function _vite_replace_library(array &$library, string $path, array $options): void
{
  $local = _vite_is_local();
  $dir = "dist";

  // Remove the old library info.
  unset($library[$path]);

  if ($local) {
    $dir = "http://localhost:12321";
    $options["type"] = "external";
    if (preg_match('/.m?js$/', $path)) {
      $options["crossorigin"] = true;
    }

    // Handle main.css in local development - convert to individual files
    if (preg_match('/\.css$/', $path) && str_contains($path, "main.css")) {
      // Load main-base.css instead of main.css
      $base_path = str_replace("main.css", "main-base.css", $path);
      $base_url = $dir . "/" . $base_path;
      $library[$base_url] = $options;

      // Add all individual CSS files that Vite is serving
      $individual_css_files = _get_individual_css_files();
      foreach ($individual_css_files as $css_file) {
        $css_url = $dir . "/" . $css_file;
        $library[$css_url] = $options;
      }

      return;
    }
  } else {
    // Convert .scss files to .css
    $path = preg_replace('/.s[ac]ss$/', ".css", $path);
    // Strip off all but the filename.
    $path = preg_replace("#^src/#", "", $path);
    $path = preg_replace('#\.s[ac]ss$#', ".css", $path);
  }

  // Prepend the directory.
  $path = $dir . "/" . $path;
  // Add in the new altered library.
  $library[$path] = $options;
}

/**
 * Get list of individual CSS files that Vite serves in development.
 * This should match the files from getIndividualCSSEntries() in vite.config.js
 */
function _get_individual_css_files(): array
{
  $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
  $css_dir = $theme_path . "/src/css";

  // Files to exclude (already in main-base.css or not individual components)
  $exclude_files = ["main.css", "main-base.css", "custom-media.css", "variables.css", "base.css", "typography.css", "admin.css"];

  $css_files = [];

  if (is_dir($css_dir)) {
    $files = scandir($css_dir);
    foreach ($files as $file) {
      if (pathinfo($file, PATHINFO_EXTENSION) === "css" && !in_array($file, $exclude_files)) {
        $css_files[] = "src/css/" . $file;
      }
    }
  }

  return $css_files;
}

/**
 * Implements hook_preprocess_html().
 */
function cga_preprocess_html(&$variables)
{
  $variables["#attached"]["drupalSettings"]["path"]["themeUrl"] = \Drupal::theme()->getActiveTheme()->getPath();
}

/**
 * Tries to determine if asset should be managed by vite.
 */
function _shouldAssetBeManagedByVite(string $path, array $options): bool
{
  return $path[0] !== DIRECTORY_SEPARATOR && strpos($path, "http") !== 0 && (!isset($options["type"]) || $options["type"] !== "external") && (!isset($options["vite"]) || $options["vite"] !== false) && (!isset($options["vite"]["enabled"]) || $options["vite"]["enabled"] !== false);
}

function _shouldLibraryBeManagedByVite(array $settings): bool
{
  return (!empty($settings["js"]) || !empty($settings["css"])) && isset($settings["vite"]) && $settings["vite"] === true;
}

/**
 * Checks if the Vite development server is running.
 * Optimized for faster response times.
 */
function _is_vite_dev_server_running(): bool
{
  static $cache = [];
  $cache_key = "vite_dev_server_running";

  // Cache the result for 5 seconds to avoid repeated checks
  if (isset($cache[$cache_key]) && $cache[$cache_key]["time"] > time() - 5) {
    return $cache[$cache_key]["result"];
  }

  $vite_host = "host.docker.internal";
  $vite_port = 12321;
  $dev_server_url = "http://" . $vite_host . ":" . $vite_port;

  // Faster connection check
  $connection = @fsockopen($vite_host, $vite_port, $errno, $errstr, 0.5);

  if ($connection) {
    fclose($connection);
    $result = true;
  } else {
    $result = false;
  }

  $cache[$cache_key] = [
    "result" => $result,
    "time" => time(),
  ];

  return $result;
}
