<?php

namespace MakinaCorpus\DrupalTooling\Console;

use MakinaCorpus\DrupalTooling\Command\CacheClearCommand;
use MakinaCorpus\DrupalTooling\Command\SiteInstallCommand;
use MakinaCorpus\DrupalTooling\Command\SiteStatusCommand;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Application extends BaseApplication
{
    const DRUPAL_BOOTSTRAP_SETTINGS = 1;
    const DRUPAL_BOOTSTRAP_DATABASE = 2;
    const DRUPAL_BOOTSTRAP_VARIABLE = 3;
    const DRUPAL_BOOTSTRAP_LANGUAGE = 5;
    const DRUPAL_BOOTSTRAP_FULL = 6;

    private static $isDrupalLoaded = false;

    private $path;
    private $drupalRoot;
    private $commandsRegistered = false;

    /**
     * Constructor
     *
     * @param string $path Drupal path
     */
    public function __construct($path)
    {
        $this->path = $path;

        parent::__construct('Drupal Tooling', 'master');

        $this->getDefinition()->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'));
    }

    /**
     * Fill some gaps in the environmnent to avoid Drupal PHP warnings
     */
    public function setDrupalEnvOverrides()
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = '127.0.0.1';
        }
        if (!isset($_SERVER['HTTP_REFERER'])) {
            $_SERVER['HTTP_REFERER'] = '';
        }
        if (!isset($_SERVER['SERVER_PROTOCOL']) || ($_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.0' && $_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.1')) {
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
        }
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
    }

    /**
     * Load bootstrap necessary includes
     */
    public function loadDrupalIncludes()
    {
        if (self::$isDrupalLoaded) {
            return;
        }

        $directory = $this->path;

        if (!is_dir($directory)) {
            throw new \RuntimeException(sprintf("%s: directory does not exists", $directory));
        }
        if (!file_exists($directory . '/index.php')) {
            throw new \RuntimeException(sprintf("%s: directory is not a PHP application directory", $directory));
        }

        $bootstrapInc = $directory . '/includes/bootstrap.inc';
        if (!is_file($bootstrapInc)) {
            throw new \RuntimeException(sprintf("%s: is a not a Drupal installation or version mismatch", $directory));
        }

        if (!$handle = fopen($bootstrapInc, 'r')) {
            throw new \RuntimeException(sprintf("%s: cannot open for reading", $bootstrapInc));
        }

        $buffer = fread($handle, 512);
        fclose($handle);

        $matches = [];
        if (preg_match("/^\s*define\('VERSION', '([^']+)'/ims", $buffer, $matches)) {
            list($parsedMajor) = explode('.', $matches[1]);
        }
        if (!isset($parsedMajor) || empty($parsedMajor)) {
            throw new \RuntimeException(sprintf("%s: could not parse core version", $bootstrapInc));
        }

        // realpath() is necessary in order to avoid symlinks messing up with
        // Drupal path when testing in a console which hadn't hardened the env
        // using a chroot() unlink PHP-FPM
        if (defined('DRUPAL_ROOT')) {
            if (DRUPAL_ROOT !== realpath($directory)) {
                throw new \LogicException(sprintf("'DRUPAL_ROOT' is already defined and does not point toward the same root"));
            }
        } else {
            define('DRUPAL_ROOT', realpath($directory));
        }
        $this->drupalRoot = DRUPAL_ROOT;

        // This is necessary, we need to change the working directory
        chdir(DRUPAL_ROOT);

        require_once $bootstrapInc;

        self::$isDrupalLoaded = true;
    }

    /**
     * Bootstrap Drupal
     */
    public function bootstrapDrupal($level = self::DRUPAL_BOOTSTRAP_FULL)
    {
        // First we need to find Drupal
        $this->setDrupalEnvOverrides();
        $this->loadDrupalIncludes();

        switch ($level) {

            case self::DRUPAL_BOOTSTRAP_SETTINGS:
                drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);
                break;

            case self::DRUPAL_BOOTSTRAP_DATABASE:
                drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
                break;

            case self::DRUPAL_BOOTSTRAP_VARIABLE:
                drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES);
                break;

            case self::DRUPAL_BOOTSTRAP_LANGUAGE:
                drupal_bootstrap(DRUPAL_BOOTSTRAP_LANGUAGE);
                break;

            case self::DRUPAL_BOOTSTRAP_FULL:
                drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
                break;

            default:
                throw new \InvalidArgumentException("Invalid bootstrap level given");
        }
    }

    /**
     * @return string
     */
    public function getDrupalRoot()
    {
        if (!$this->drupalRoot) {
            throw new \LogicException("You must bootstrap Drupal first");
        }

        return $this->drupalRoot;
    }

    /**
     * Get databse connection
     *
     * @param string $target
     *
     * @return \DatabaseConnection
     */
    public function getDatabaseConnection($target = 'default')
    {
        $this->bootstrapDrupal(self::DRUPAL_BOOTSTRAP_DATABASE);

        return \Database::getConnection($target);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $this->registerCommands();

        return parent::get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function all($namespace = null)
    {
        $this->registerCommands();

        return parent::all($namespace);
    }

    private function registerCommands()
    {
        if ($this->commandsRegistered) {
            return;
        }

        $this->commandsRegistered = true;

        $this->add(new CacheClearCommand());
        $this->add(new SiteInstallCommand());
        $this->add(new SiteStatusCommand());

        /*
        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        foreach ($this->kernel->getBundles() as $bundle) {
            if ($bundle instanceof Bundle) {
                $bundle->registerCommands($this);
            }
        }

        if ($container->hasParameter('console.command.ids')) {
            foreach ($container->getParameter('console.command.ids') as $id) {
                $this->add($container->get($id));
            }
        }
         */
    }
}
