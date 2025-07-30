<?php

use Drupal\Core\Site\Settings;

/**
 * Implements hook_library_info_alter().
 */
function speedster_library_info_alter(&$libraries, $extension)
{
  if ($extension !== "speedster") {
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
    $dir = \Drupal::service("settings")->get("vite")["devServerUrl"];
    $options["type"] = "external";
    if (preg_match('/.m?js$/', $path)) {
      $options["crossorigin"] = true;
    }
  } else {
    // Convert .scss files to .css
    $path = preg_replace('/.s[ac]ss$/', ".css", $path);
    // Strip off all but the filename.
    if (!$local) {
      $path = preg_replace("#^src/#", "", $path);
      $path = preg_replace('#\.s[ac]ss$#', ".css", $path);
    }
  }

  // Prepend the local development url.
  $path = $dir . "/" . $path;
  // Add in the new altered library.
  $library[$path] = $options;
}

/**
 * Implements hook_preprocess_html().
 */
function speedster_preprocess_html(&$variables)
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
  $cache_key = "vite_dev_server_running_http";

  // Cache the result for 5 seconds
  if (isset($cache[$cache_key]) && $cache[$cache_key]["time"] > time() - 5) {
    return $cache[$cache_key]["result"];
  }

  $vite_settings = \Drupal::service("settings")->get("vite");
  if (!$vite_settings || !isset($vite_settings["devServerUrl"])) {
    return false;
  }

  $dev_server_url = $vite_settings["devServerUrl"];

  // Try to make a quick HTTP request to the Vite client endpoint
  $context = stream_context_create([
    "http" => [
      "timeout" => 2,
      "method" => "GET",
    ],
  ]);

  $vite_client_url = $dev_server_url . "/@vite/client";
  $result = @file_get_contents($vite_client_url, false, $context);

  $is_running = $result !== false;

  if ($is_running) {
    \Drupal::logger("vite")->info("Vite dev server confirmed running at {$dev_server_url}");
  } else {
    \Drupal::logger("vite")->warning("Vite dev server not responding at {$dev_server_url}");
  }

  $cache[$cache_key] = [
    "result" => $is_running,
    "time" => time(),
  ];

  return $is_running;
}
