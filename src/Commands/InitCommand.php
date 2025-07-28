<?php

namespace DrupalProject\composer\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use DrupalFinder\DrupalFinder;

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
    $this->createThemeFiles($fs, $themeDir, $themeName, $themeMachineName, $output);

    // 6. Enable settings.local.php in settings.php
    $this->enableSettingsLocal($fs, $drupalRoot, $output);

    // 7. Update DDEV config
    $this->updateDdevConfig($fs, $drupalRoot, $output);

    // 8. Restart DDEV if available
    $this->restartDdev($output);

    $output->writeln("<success>✅ Vite theme '{$themeName}' initialized successfully!</success>");
    $output->writeln("<comment>Next steps:</comment>");
    $output->writeln("1. cd {$themeDir}");
    $output->writeln("2. pnpm install");
    $output->writeln("3. pnpm build to make initial manifest file");
    $output->writeln("4. pnpm dev to start development server");

    return 0;
  }

  private function copyViteConfig($fs, $packageDir, $themeDir, $themeMachineName, $output)
  {
    $sourceFile = $packageDir . "/src/custom/vite.config.js";
    $destFile = $themeDir . "/vite.config.js";

    if ($fs->exists($sourceFile)) {
      // Read the source file and replace the theme name placeholder
      $content = file_get_contents($sourceFile);
      $content = str_replace("cga", $themeMachineName, $content);
      $content = str_replace("/themes/custom/cga/", "/themes/custom/{$themeMachineName}/", $content);

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
      $content = str_replace("cga", $themeMachineName, $content);

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

    // Add/update scripts
    $packageJson["scripts"] = array_merge($packageJson["scripts"] ?? [], [
      "dev" => "vite --host 0.0.0.0 --port 12321",
      "dev:fast" => "vite --host 0.0.0.0 --port 12321 --force",
      "dev:debug" => "vite --host 0.0.0.0 --port 12321 --debug --force",
      "build" => "vite build --mode production",
      "preview" => "vite preview",
    ]);

    // Add devDependencies
    $packageJson["devDependencies"] = array_merge($packageJson["devDependencies"] ?? [], [
      "@csstools/postcss-global-data" => "^3.0.0",
      "autoprefixer" => "^10.4.21",
      "glob" => "^11.0.3",
      "postcss" => "^8.5.3",
      "postcss-custom-media" => "^11.0.5",
      "postcss-import" => "^16.1.0",
      "postcss-nesting" => "^13.0.1",
      "postcss-preset-env" => "^10.1.6",
      "util" => "^0.12.5",
      "vite" => "^6.2.6",
      "vite-plugin-compression" => "^0.5.1",
    ]);

    // Add dependencies
    $packageJson["dependencies"] = array_merge($packageJson["dependencies"] ?? [], [
      "cssnano" => "^7.1.0",
      "tinyglobby" => "^0.2.14",
      "vite-plugin-live-reload" => "^3.0.4",
    ]);

    $fs->dumpFile($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $output->writeln("✓ Updated package.json");
  }

  private function createSrcStructure($fs, $themeDir, $output)
  {
    // Create src directories
    $srcDir = $themeDir . "/src";
    $cssDir = $srcDir . "/css";
    $jsDir = $srcDir . "/js";

    $fs->mkdir([$cssDir, $jsDir]);
    $output->writeln("✓ Created src/css/ directory");
    $output->writeln("✓ Created src/js/ directory");
  }

  private function createThemeFiles($fs, $themeDir, $themeName, $themeMachineName, $output)
  {
    // Create .theme file with Vite integration
    $themeFile = <<<PHP
    <?php
    require_once __DIR__ . '/includes/vite.php';
    PHP;

    $fs->dumpFile($themeDir . "/{$themeMachineName}.theme", $themeFile);
    $output->writeln("✓ Created {$themeMachineName}.theme");
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

    // Check if disable_settings_management is already set to true
    if (preg_match('/^disable_settings_management:\s*true\s*$/m', $content)) {
      $output->writeln("✓ disable_settings_management already set to true in .ddev/config.yaml");
      return;
    }

    // Check if disable_settings_management exists but is set to false
    if (preg_match('/^disable_settings_management:\s*false\s*$/m', $content)) {
      $content = preg_replace('/^disable_settings_management:\s*false\s*$/m', "disable_settings_management: true", $content);
      $fs->dumpFile($ddevConfigPath, $content);
      $output->writeln("✓ Updated disable_settings_management to true in .ddev/config.yaml");
      return;
    }

    // If disable_settings_management doesn't exist, add it
    // Try to add it after the name field for better organization
    if (preg_match('/^name:\s*.*$/m', $content)) {
      $content = preg_replace('/^(name:\s*.*\n)/m', '$1disable_settings_management: true' . "\n", $content);
    } else {
      // If no name field found, add at the beginning
      $content = "disable_settings_management: true\n" . $content;
    }

    $fs->dumpFile($ddevConfigPath, $content);
    $output->writeln("✓ Added disable_settings_management: true to .ddev/config.yaml");
  }

  private function restartDdev($output)
  {
    // Check if ddev command is available
    $ddevAvailable = shell_exec("which ddev 2>/dev/null");

    if (empty(trim($ddevAvailable))) {
      $output->writeln("<comment>⚠ DDEV command not found, skipping restart</comment>");
      return;
    }

    $output->writeln("<info>🔄 Restarting DDEV to apply config changes...</info>");

    // Execute ddev restart
    $result = shell_exec("ddev restart 2>&1");
    $exitCode = shell_exec('echo $?');

    if (trim($exitCode) === "0") {
      $output->writeln("✅ DDEV restarted successfully");
    } else {
      $output->writeln("<error>❌ DDEV restart failed:</error>");
      $output->writeln($result);
      $output->writeln("<comment>You may need to run 'ddev restart' manually</comment>");
    }
  }
}
