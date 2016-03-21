<?php

namespace MakinaCorpus\DrupalTooling\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SiteStatusCommand extends AbstractCommand
{
    public function __construct($name = 'site:status')
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDefinition(new InputDefinition())
            ->setDescription('Displays site information')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->drupalBootstrapDatabase();
            $bootstrapStatus = 'success';
        } catch (\Exception $e) {
            $bootstrapStatus = $e->getMessage();
        }

        try {
            \Database::getConnection()->query("SELECT 1");
            $databaseStatus = 'connected';
        } catch (\Exception $e) {
            $databaseStatus = $e->getMessage();
        }

        // @todo Translation
        $headers  = ['Info', 'Value'];
        $rows     = [];

        $rows[] = ['core version', VERSION];
        $rows[] = ['status', variable_get('maintainance_mode') ? "offline" : "online"];
        $rows[] = ['database class', get_class(\Database::getConnection())];
        $rows[] = ['database host', \Database::getConnectionInfo('default')['default']['host']];
        $rows[] = ['database status', $databaseStatus];
        $rows[] = ['bootstrap status', $bootstrapStatus];

        $io->table($headers, $rows);

        return 0;
    }
}
