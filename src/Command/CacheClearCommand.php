<?php

namespace MakinaCorpus\DrupalTooling\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheClearCommand extends AbstractCommand
{
    public function __construct($name = 'cache:clear')
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDefinition(new InputDefinition())
            ->setDescription("Clear selected Drupal caches")
            ->setHelp(<<<EOT
Clear selected Drupal caches, you may specific which cache to delete using the -b|--bin switch, you may use any of:
 - 'all': clear all Drupal caches
 - 'asset' or 'css-js': clear the frontend asset cache and regenerate the Drupal cache buster token
 - 'hook': clear bootstrap cache
 - 'menu': rebuild the Drupal menu
 - 'theme' or 'theme-registry': rebuild the Drupal theme registry
 - 'registry' : bootstrap Drupal in a lower level and rebuild the file registry
 - BIN : any other string will be considered as a Drupal cache bin, 'cache_' prefix can be ommited
EOT
            )
            ->addOption('bin', 'b', InputOption::VALUE_OPTIONAL, "Which cache bin to clear, see help for extended documentation", 'all')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io   = new SymfonyStyle($input, $output);
        $bin  = $input->getOption('bin');

        $this->drupalBootstrapDatabase();

        if ('all' === $bin) {

            $this->drupalBootstrapFull();
            drupal_flush_all_caches();

            $io->success("All Drupal caches have been dropped");

        } else if ('bin' === $bin) {

            $this->drupalBootstrapFull();

            $binList = ['cache', 'cache_path', 'cache_filter', 'cache_bootstrap', 'cache_page'];
            $binList = array_merge(module_invoke_all('flush_caches'), $binList);

            foreach ($binList as $bin) {
                cache_clear_all('*', $bin, true);
            }

            $io->success("All bins have been emptied");

        } else if ('asset' === $bin || 'css-js' === $bin) {

            $this->drupalBootstrapFull();

            _drupal_flush_css_js();
            drupal_clear_css_cache();
            drupal_clear_js_cache();

            $io->success("Frontend asset cache has been dropped and cache buster updated");

        } else if ('registry' === $bin) {

            $this->drupalBootstrapVariable();
            $this->getApplication()->getDrupalRoot() . '/includes/file.inc';
            registry_update();

            $io->success("Registry has been rebuilt");

        } else if ('hook' === $bin) {

            cache_clear_all('*', 'cache_bootstrap', true);

        } else if ('menu' === $bin) {

            $this->drupalBootstrapFull();
            menu_rebuild();

        } else if ('theme' === $bin || 'theme-registry' === $bin) {

            $this->drupalBootstrapFull();
            drupal_theme_rebuild();

            $io->success("Theme registry has been rebuilt");

        } else {

            if ('cache' !== $bin && 'cache_' !== substr($bin, 0, 6)) {
                $bin = 'cache_' . $bin;
            }

            if (!\Database::getConnection()->schema()->tableExists($bin)) {
                throw new \InvalidArgumentException(sprintf("%s: table does not exist", $bin));
            }

            cache_clear_all('*', $bin, true);

            $io->success(sprintf("%s: bin cleared", $bin));
        }

        return 0;
    }
}
