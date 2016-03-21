<?php

namespace MakinaCorpus\DrupalTooling;

use MakinaCorpus\DrupalTooling\Console\Application;

use Symfony\Component\Console\Output\OutputInterface;

class SiteInstaller
{
    /**
     * @var Application
     */
    private $application;

    /**
     * Default constructor
     *
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Enable and install modules more quickly than core alternative.
     */
    public function enableModule($module)
    {
        $db = $this->application->getDatabaseConnection();

        // Only process modules that are not already enabled.
        $existing = $db->query("SELECT status, info, schema_version FROM {system} WHERE name = :name", [':name' => $module])->fetch();

        if (!$existing) {
            throw new \Exception(sprintf("%s: system table is not complete during install", $module));
        }

        $enabled = (bool)$existing->status;
        $info = (bool)unserialize($existing->info);
        $schema_version = $existing->schema_version;

        if ($enabled) {
            return;
        }

        // Load module code.
        drupal_load('module', $module);
        module_load_install($module);
        $db->query("UPDATE {system} SET status = 1 WHERE name = :name", [':name' => $module]);

        _system_update_bootstrap_status();
        drupal_static_reset();
        module_list(true);

        // Update the registry only if the module defines files.
        if (!empty($info['files'])) {
            registry_update();
        }

        // Refresh the schema to include it, we cannot really make it conditional
        // because of hook_schema_alter() that could alter either this module schema
        // or be implemented by this module too.
        drupal_get_schema(null, true);

        // Now install the module if necessary.
        if (SCHEMA_UNINSTALLED == $schema_version) {
            drupal_install_schema($module);

            // Set the schema version to the number of the last update provided
            // by the module.
            $versions = drupal_get_schema_versions($module);
            $version = $versions ? max($versions) : SCHEMA_INSTALLED;

            // If the module has no current updates, but has some that were
            // previously removed, set the version to the value of
            // hook_update_last_removed().
            if ($last_removed = module_invoke($module, 'update_last_removed')) {
                $version = max($version, $last_removed);
            }
            drupal_set_installed_schema_version($module, $version);
            // Allow the module to perform install tasks.
            module_invoke($module, 'install');
        }

        module_invoke($module, 'enable');
    }

    /**
     * Prepare environment.
     */
    private function prepareEnvironment()
    {
        $this->application->setDrupalEnvOverrides();
        $this->application->loadDrupalIncludes();

        define('MAINTENANCE_MODE', 'install');

        $this->application->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_SETTINGS);

        $root = $this->application->getDrupalRoot();

        // We do will need all of this
        require_once $root . '/includes/cache.inc';
        require_once $root . '/includes/database/database.inc';
        require_once $root . '/includes/common.inc';
        require_once $root . '/includes/entity.inc';
        require_once $root . '/includes/file.inc';
        require_once $root . '/includes/install.inc';
        require_once $root . '/includes/language.inc';
        require_once $root . '/includes/module.inc';
        require_once $root . '/includes/theme.inc';
        require_once $root . '/includes/unicode.inc';
        require_once $root . '/modules/system/system.install';
        require_once $root . '/modules/system/system.module';
        require_once $root . '/modules/user/user.install';
        require_once $root . '/modules/user/user.module';
    }

    public function install($profile, OutputInterface $output)
    {
        $this->prepareEnvironment();

        $root = $this->application->getDrupalRoot();
        $db   = $this->application->getDatabaseConnection();

        if ($db->schema()->tableExists('system')) {
            throw new \LogicException("Drupal already installed");
        }

        $moduleList = [];
        $moduleList['system']['filename'] = 'modules/system/system.module';
        $moduleList['user']['filename'] = 'modules/user/user.module';
        module_list(true, false, false, $moduleList);

        // We will need this to do a few overrides
        $includes = [
            'path_inc' => __DIR__ . '/includes/null-path.inc',
            'lock_inc' => __DIR__ . '/includes/null-lock.inc',
        ];
        foreach ($includes as $variable => $file) {
            $pathPrefix = array_fill(0, substr_count($root, '/'), '..');
            $GLOBALS['conf'][$variable] = implode('/', $pathPrefix) . $file;
        }

        foreach (array_keys($GLOBALS['conf']) as $variable) {
            if ('cache' === substr($variable, 0, 5)) {
                unset($GLOBALS['conf'][$variable]);
            }
        }
        $GLOBALS['conf']['cache_default_class'] = '\MakinaCorpus\DrupalTooling\Cache\NullCacheBackend';

        $profile_file = $root . "/profiles/$profile/$profile.profile";
        if (!isset($profile) || !file_exists($profile_file)) {
            throw new \InvalidArgumentException(sprintf("%s: profile not found", $profile));
        }
        $info_file = $root . "/profiles/$profile/$profile.info";
        if (!file_exists($info_file)) {
            throw new \InvalidArgumentException(sprintf("%s: profile not found", $profile));
        }
        $info = drupal_parse_info_file($info_file);

        $output->writeln(sprintf("%s: found profile", $profile));

        // We cant do better than this function, since that system module
        // bootstraps itself without any core subsystem ready yet, but then
        // rebuilds the module data, which we do need too, before enabling the
        // user and other modules
        drupal_install_system();
        $this->enableModule('user');
        $output->writeln("-- base system installed --");

        // This must happend after syste module has been installed to ensure
        // that the variable table exists
        variable_set('install_profile', $profile);

        // We also need to rebuild all Drupal caches and let the
        // system_rebuild_module_data() function happen to ensure that the
        // profile will be in the system list
        drupal_static_reset();
        module_list(true);

        // Rebuild the initial registry to ensures that classes in Drupal .inc files
        // are correctly loaded once installed.
        registry_update();

        $moduleList = $info['dependencies'];
        // Node is a required module, most modules don't set the explicit dependency
        // upon it, which makes profiles that don't set node in their module list to
        // fail at install.
        if (!in_array('node', $moduleList)) {
            array_unshift($moduleList, 'node');
        }
        // Same goes for the filter module.
        if (!in_array('filter', $moduleList)) {
            array_unshift($moduleList, 'filter');
        }

        $this->application->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_FULL);
        $output->writeln("-- bootstrap OK --");

        // Rebuild the modules dependency tree.
        $module_data = system_rebuild_module_data();
        $moduleList = array_flip(array_values($moduleList));
        while (list($module) = each($moduleList)) {
            if (!isset($module_data[$module])) {
                throw new \InvalidArgumentException(sprintf("%s: module is missing from filesystem", $module));
            }
            $moduleList[$module] = $module_data[$module]->sort;
            foreach (array_keys($module_data[$module]->requires) as $dependency) {
                if (!isset($moduleList[$dependency])) {
                    $moduleList[$dependency] = 0;
                }
            }
        }
        arsort($moduleList);
        $moduleList = array_keys($moduleList);

        // Install modules, really.
        foreach ($moduleList as $module) {
            $this->enableModule($module);
            $output->writeln(sprintf("%s: installed", $module));
        }

        // Enable the profile
        $this->enableModule($profile);
        $output->writeln("-- profile fully installed --");

        // Delay all hook_module_installed().
        module_invoke_all('modules_installed', $moduleList);
        module_invoke_all('modules_enabled', $moduleList);

        // Rebuild the theme registry, since we removed it from module_enable().
        drupal_theme_rebuild();
    }
}
