<?php

namespace MakinaCorpus\DrupalTooling\Command;

use MakinaCorpus\DrupalTooling\Console\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractCommand extends Command
{
    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_LANGUAGE);

        return \Drupal::getContainer();
    }

    /**
     * @return HttpKernelInterface
     */
    public function getKernel()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_LANGUAGE);

        return \Drupal::_getKernel();
    }

    public function drupalBootstrapFull()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_FULL);
    }

    public function drupalBootstrapVariable()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_VARIABLE);
    }

    public function drupalBootstrapDatabase()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_DATABASE);
    }

    public function drupalBootstrapConfiguration()
    {
        $this->getApplication()->bootstrapDrupal(Application::DRUPAL_BOOTSTRAP_SETTINGS);
    }
}
