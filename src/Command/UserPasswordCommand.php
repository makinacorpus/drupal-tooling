<?php

namespace MakinaCorpus\DrupalTooling\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserPasswordCommand extends AbstractCommand
{
    public function __construct($name = 'user:password')
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDefinition(new InputDefinition())
            ->setDescription("Change user password")
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, "User to change the password, may be numeric, name or email")
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, "New password, leave null to generate a new one", null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io   = new SymfonyStyle($input, $output);

        $this->drupalBootstrapDatabase();

        $user = $input->getOption('user');
        $pass = $input->getOption('password');

        $map = db_query("
            SELECT uid, name FROM {users}
            WHERE
                uid = ?
                OR name = ?
                OR mail = ?
        ", [$user, $user, $user])->fetchAllKeyed();

        if (empty($map)) {
            $io->error("No matching users");

            return 1;
        }

        $headers  = ['Id', 'Name'];
        $rows     = [];

        foreach ($map as $uid => $name) {
            $rows[] = [$uid, check_plain($name)];
        }

        reset($map);
        $default = key($map);
        $value = null;

        while (!$value) {

            $io->table($headers, $rows);
            $value = $io->ask("Please select a user identifier", $default);

            if (!$value) {
                $value = $default;
            }
            if (!isset($map[$value])) {
                $value = null;
            }
        }

        require_once DRUPAL_ROOT . '/includes/password.inc';
        $hash = _password_crypt('sha512', $pass, _password_generate_salt(DRUPAL_HASH_COUNT));

        db_query("UPDATE {users} SET pass = ? WHERE uid = ?", [$hash, $value]);

        return 0;
    }
}
