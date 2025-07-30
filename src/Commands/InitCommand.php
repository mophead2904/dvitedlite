<?php

namespace DrupalProject\composer\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Yaml\Yaml;

class InitCommand extends BaseCommand
{
  protected function configure()
  {
    $this->setName("speedster:init")->setDescription("Initialize theme with Vite development setup")->addArgument("theme_name", InputArgument::REQUIRED, "The name of the theme to initialize")->setHelp("This command sets up a Drupal theme with Vite for modern frontend development");
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $themeName = $input->getArgument("theme_name");
    $themeMachineName = strtolower(str_replace([" ", "-"], "_", $themeName));
    $fs = new Filesystem();

    // Find Drupal root
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    // Get package directory
    $composer = $this->getComposer();
    $vendorDir = $composer->getConfig()->get("vendor-dir");
    $packageDir = $vendorDir . "/mophead2904/speedster";

    $output->writeln("<info>🚀 Initializing Vite theme: {$themeName}</info>");
    $output->writeln("Drupal root: {$drupalRoot}");
    $output->writeln("Package dir: {$packageDir}");

    // Create theme directory
    $themeDir = $drupalRoot . "/themes/custom/{$themeMachineName}";

    if (!$fs->exists($themeDir)) {
      $fs->mkdir($themeDir);
      $output->writeln("✓ Created theme directory: {$themeDir}");
    } else {
      $output->writeln("⚠ Theme directory already exists: {$themeDir}");
    }

    // 1. Copy vite.config.js from package
    $this->copyViteConfig($fs, $packageDir, $themeDir, $themeMachineName, $output);

    // 2. Copy vite.php to /includes/
    $this->copyVitePhp($fs, $packageDir, $themeDir, $themeMachineName, $output);

    // 3. Update package.json
    $this->updatePackageJson($fs, $themeDir, $output);

    // 4. Create src structure with basic files
    $this->createSrcStructure($fs, $themeDir, $output);

    // 5. Create basic theme files
    $this->createThemeFiles($fs, $themeDir, $themeName, $themeMachineName, $drupalRoot, $output);

    // 6. Enable settings.local.php in settings.php
    $this->enableSettingsLocal($fs, $drupalRoot, $output);

    // 7. Update DDEV config
    $this->updateDdevConfig($fs, $drupalRoot, $output);

    $output->writeln("<success>✅ Vite theme '{$themeName}' initialized successfully!</success>");
    $output->writeln("<comment>Next steps:</comment>");
    $output->writeln("1. ddev restart");
    $output->writeln("2. cd {$themeDir}");
    $output->writeln("3. pnpm install");
    $output->writeln("4. pnpm build to make initial manifest file");
    $output->writeln("5. pnpm dev to start development server");

    return 0;
  }

  private function copyViteConfig($fs, $packageDir, $themeDir, $themeMachineName, $output)
  {
    $sourceFile = $packageDir . "/src/custom/vite.config.js";
    $destFile = $themeDir . "/vite.config.js";

    if ($fs->exists($sourceFile)) {
      // Read the source file and replace the theme name placeholder
      $content = file_get_contents($sourceFile);
      $content = str_replace("THEME_NAME", $themeMachineName, $content);
      $content = str_replace("/themes/custom/THEME_NAME/", "/themes/custom/{$themeMachineName}/", $content);

      $fs->dumpFile($destFile, $content);
      $output->writeln("✓ Created vite.config.js");
    } else {
      $output->writeln("<error>Source vite.config.js not found: {$sourceFile}</error>");
    }
  }

  private function copyVitePhp($fs, $packageDir, $themeDir, $themeMachineName, $output)
  {
    $includesDir = $themeDir . "/includes";
    if (!$fs->exists($includesDir)) {
      $fs->mkdir($includesDir);
    }

    $sourceFile = $packageDir . "/src/custom/vite.php";
    $destFile = $includesDir . "/vite.php";

    if ($fs->exists($sourceFile)) {
      // Read the source file and replace the theme name placeholder
      $content = file_get_contents($sourceFile);
      $content = str_replace("THEME_NAME", $themeMachineName, $content);

      $fs->dumpFile($destFile, $content);
      $output->writeln("✓ Created includes/vite.php");
    } else {
      $output->writeln("<error>Source vite.php not found: {$sourceFile}</error>");
    }
  }

  private function updatePackageJson($fs, $themeDir, $output)
  {
    $packageJsonPath = $themeDir . "/package.json";
    $packageJson = [];

    // Load existing package.json if it exists
    if ($fs->exists($packageJsonPath)) {
      $packageJson = json_decode(file_get_contents($packageJsonPath), true) ?: [];
    }

    // Make sure type is set to module
    $packageJson["type"] = array_merge($packageJson["type"] ?? [], ["module"]);

    // Add/update scripts
    $packageJson["scripts"] = array_merge($packageJson["scripts"] ?? [], [
      "dev" => "vite",
      "build" => "vite build --mode production",
    ]);

    // Add devDependencies
    $packageJson["devDependencies"] = array_merge($packageJson["devDependencies"] ?? [], [
      "postcss" => "^8.5.3",
      "postcss-preset-env" => "^10.1.6",
      "vite" => "^6.2.6",
      "tinyglobby" => "^0.2.14",
    ]);

    $fs->dumpFile($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $output->writeln("✓ Updated package.json");
  }

  private function createSrcStructure($fs, $themeDir, $output)
  {
    // Create src directories
    $srcDir = $themeDir . "/src";
    $cssMain = $srcDir . "/css/";
    $jsDir = $srcDir . "/js/";

    $fs->mkdir([$cssMain, $jsDir]);

    // add a main.css and main.js
    $fs->dumpFile($cssMain . "main.css", "");
    $fs->dumpFile($jsDir . "main.js", "");

    $fs->dumpFile($jsDir . "main.js", "import '../css/main.css';");

    $output->writeln("✓ Created src/css/main.css");
    $output->writeln("✓ Created src/js/main.js");
  }

  private function createThemeFiles($fs, $themeDir, $themeName, $themeMachineName, $drupalRoot, $output)
  {
    // Create .theme file with Vite integration
    $themeFile = <<<PHP
    <?php
    require_once __DIR__ . '/includes/vite.php';
    PHP;

    $fs->dumpFile($themeDir . "/{$themeMachineName}.theme", $themeFile);
    $output->writeln("✓ Created {$themeMachineName}.theme");

    // Create or update theme.libraries.yml file
    $this->createOrUpdateLibrariesYml($fs, $themeDir, $themeMachineName, $drupalRoot, $output);

    // Create or update theme.info.yml file
    $this->createOrUpdateThemeInfo($fs, $themeDir, $themeName, $themeMachineName, $output);
  }

  private function createOrUpdateLibrariesYml($fs, $themeDir, $themeMachineName, $drupalRoot, $output)
  {
    $librariesYmlPath = $themeDir . "/{$themeMachineName}.libraries.yml";
    $libraries = [];
    $projectName = $this->getDdevProjectName($drupalRoot);

    // Load existing libraries.yml if it exists
    if ($fs->exists($librariesYmlPath)) {
      $content = file_get_contents($librariesYmlPath);
      $libraries = Yaml::parse($content) ?: [];
      $output->writeln("✓ Loading existing {$themeMachineName}.libraries.yml");
    }

    // Add/update global library (only if it doesn't exist or needs updating)
    $libraries["global"] = [
      "vite" => true,
      "css" => [
        "theme" => [
          "src/css/main.css" => [],
        ],
      ],
      "js" => [
        "src/js/main.js" => ["attributes" => ["type" => "module"]],
      ],
      "dependencies" => ["core/drupal", "core/once", "core/jquery", "core/drupalSettings"],
    ];

    // Add/update hot-module-replacement library with dynamic project name
    $libraries["hot-module-replacement"] = [
      "js" => [
        "https://{$projectName}.ddev.site:5173/@vite/client" => [
          "type" => "external",
          "attributes" => ["type" => "module"],
        ],
      ],
      "dependencies" => ["core/drupal"],
    ];

    // Convert to YAML and write
    $yamlContent = $this->arrayToYaml($libraries);
    $fs->dumpFile($librariesYmlPath, $yamlContent);
    $output->writeln("✓ Created/updated {$themeMachineName}.libraries.yml");
  }

  private function createOrUpdateThemeInfo($fs, $themeDir, $themeName, $themeMachineName, $output)
  {
    $infoYmlPath = $themeDir . "/{$themeMachineName}.info.yml";
    $infoYml = [];

    // Load existing info.yml if it exists
    if ($fs->exists($infoYmlPath)) {
      $content = file_get_contents($infoYmlPath);
      $infoYml = Yaml::parse($content) ?: [];
      $output->writeln("✓ Loading existing {$themeMachineName}.info.yml");
    } else {
      // Create basic theme info structure
      $infoYml = [
        "name" => $themeName,
        "type" => "theme",
        "description" => "A Vite-powered Drupal theme",
        "core_version_requirement" => "^9 || ^10 || ^11",
        "base theme" => false,
      ];
    }

    // Add or update libraries section
    $libraries = ["{$themeMachineName}/global", "{$themeMachineName}/hot-module-replacement"];

    $infoYml["libraries"] = array_unique(array_merge($infoYml["libraries"] ?? [], $libraries));

    // Convert array back to YAML and write to file
    $yamlContent = $this->arrayToYaml($infoYml);
    $fs->dumpFile($infoYmlPath, $yamlContent);
    $output->writeln("✓ Created/updated {$themeMachineName}.info.yml");
  }

  private function enableSettingsLocal($fs, $drupalRoot, $output)
  {
    $settingsPath = $drupalRoot . "/sites/default/settings.php";

    if (!$fs->exists($settingsPath)) {
      $output->writeln("<comment>⚠ settings.php not found, skipping settings.local.php enablement</comment>");
      return;
    }

    $content = file_get_contents($settingsPath);

    // Check if settings.local.php include is already uncommented
    if (preg_match('/^\s*if\s*\(\s*file_exists\s*\(\s*\$app_root\s*\.\s*\'\/\'\s*\.\s*\$site_path\s*\.\s*\'\/settings\.local\.php\'\s*\)\s*\)\s*\{/m', $content)) {
      $output->writeln("✓ settings.local.php include already enabled in settings.php");
      return;
    }

    // Look for the commented version and uncomment it
    $pattern = '/^#\s*(if\s*\(\s*file_exists\s*\(\s*\$app_root\s*\.\s*\'\/\'\s*\.\s*\$site_path\s*\.\s*\'\/settings\.local\.php\'\s*\)\s*\)\s*\{)\s*\n#\s*(include\s+\$app_root\s*\.\s*\'\/\'\s*\.\s*\$site_path\s*\.\s*\'\/settings\.local\.php\';)\s*\n#\s*(\})/m';

    if (preg_match($pattern, $content)) {
      $replacement = '$1' . "\n" . '  $2' . "\n" . '$3';
      $content = preg_replace($pattern, $replacement, $content);

      $fs->dumpFile($settingsPath, $content);
      $output->writeln("✓ Uncommented settings.local.php include in settings.php");
    } else {
      // If the commented version isn't found, add it at the end
      $settingsLocalCode = "\n" . "// Include local settings if available." . "\n" . 'if (file_exists($app_root . \'/\' . $site_path . \'/settings.local.php\')) {' . "\n" . '  include $app_root . \'/\' . $site_path . \'/settings.local.php\';' . "\n" . "}" . "\n";

      $content .= $settingsLocalCode;
      $fs->dumpFile($settingsPath, $content);
      $output->writeln("✓ Added settings.local.php include to settings.php");
    }
  }

  private function updateDdevConfig($fs, $drupalRoot, $output)
  {
    // Go up one level from Drupal root to find .ddev directory
    $projectRoot = dirname($drupalRoot);
    $ddevConfigPath = $projectRoot . "/.ddev/config.yaml";

    if (!$fs->exists($ddevConfigPath)) {
      $output->writeln("<comment>⚠ .ddev/config.yaml not found, skipping DDEV config update</comment>");
      return;
    }

    $content = file_get_contents($ddevConfigPath);
    $modified = false;

    // Handle disable_settings_management
    if (preg_match('/^disable_settings_management:\s*true\s*$/m', $content)) {
      $output->writeln("✓ disable_settings_management already set to true in .ddev/config.yaml");
    } elseif (preg_match('/^disable_settings_management:\s*false\s*$/m', $content)) {
      $content = preg_replace('/^disable_settings_management:\s*false\s*$/m', "disable_settings_management: true", $content);
      $output->writeln("✓ Updated disable_settings_management to true in .ddev/config.yaml");
      $modified = true;
    } else {
      // If disable_settings_management doesn't exist, add it
      if (preg_match('/^name:\s*.*$/m', $content)) {
        $content = preg_replace('/^(name:\s*.*\n)/m', '$1disable_settings_management: true' . "\n", $content);
      } else {
        $content = "disable_settings_management: true\n" . $content;
      }
      $output->writeln("✓ Added disable_settings_management: true to .ddev/config.yaml");
      $modified = true;
    }

    // Handle web_extra_exposed_ports for Vite
    $vitePortConfig = 'web_extra_exposed_ports:
    - name: vite
      container_port: 5173
      http_port: 5172
      https_port: 5173';

    if (preg_match('/^web_extra_exposed_ports:\s*$/m', $content)) {
      // web_extra_exposed_ports section exists, check if vite is already configured
      if (preg_match('/^\s*-\s*name:\s*vite\s*$/m', $content)) {
        $output->writeln("✓ Vite port configuration already exists in .ddev/config.yaml");
      } else {
        // Add vite configuration to existing web_extra_exposed_ports
        $viteEntry = '  - name: vite
      container_port: 5173
      http_port: 5172
      https_port: 5173';

        $content = preg_replace('/^(web_extra_exposed_ports:\s*\n)/m', '$1' . $viteEntry . "\n", $content);
        $output->writeln("✓ Added Vite port configuration to existing web_extra_exposed_ports in .ddev/config.yaml");
        $modified = true;
      }
    } else {
      // web_extra_exposed_ports doesn't exist, add the entire section
      $content .= "\n" . $vitePortConfig . "\n";
      $output->writeln("✓ Added web_extra_exposed_ports with Vite configuration to .ddev/config.yaml");
      $modified = true;
    }

    // Write the file only if it was modified
    if ($modified) {
      $fs->dumpFile($ddevConfigPath, $content);
    }
  }

  private function getDdevProjectName($drupalRoot)
  {
    $projectRoot = dirname($drupalRoot);
    $ddevConfigPath = $projectRoot . "/.ddev/config.yaml";

    if (!file_exists($ddevConfigPath)) {
      // Fallback to directory name if no DDEV config found
      return basename($projectRoot);
    }

    $content = file_get_contents($ddevConfigPath);

    // Extract project name from DDEV config
    if (preg_match('/^name:\s*([^\s]+)\s*$/m', $content, $matches)) {
      return trim($matches[1]);
    }

    // Fallback to directory name if name not found in config
    return basename($projectRoot);
  }
}
