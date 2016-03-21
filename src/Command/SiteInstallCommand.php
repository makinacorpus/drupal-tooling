<?php

namespace MakinaCorpus\DrupalTooling\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use MakinaCorpus\DrupalTooling\SiteInstaller;

class SiteInstallCommand extends AbstractCommand
{
    public function __construct($name = 'site:install')
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDefinition(new InputDefinition())
            ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, "Profile to use for install")
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, "Administrator user name", 'admin')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, "Administrator password", 'generated')
            ->setDescription('Installs site')
            ->setHelp(<<<EOT
Installs the site. If site is already installed, this command will fail.

IMPORTANT NOTICE: this install procedure needs the settings.php file to be
already generated with the database configuration inside. All cache backends,
path.inc and lock.inc replacement files will be ignored and replaced by null
implementations in order to speed-up the process.

This install procedure will use a steroid-based install.php version, it is
very efficient but may consume lots of RAM, be aware.

It only installs the selected profile and module dependencies, but per default
won't prepare the site, nor set variables, it is your responsability to do so
in your profile install file.

EOT
            )
        ;
    }

    private function generatePassword($length = 13)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@!#{}[]_-="~&*';
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $profile = $input->getOption('profile');
        if (!$profile) {
            throw new \InvalidArgumentException(sprintf("--profile=PROFILE is required"));
        }

        $username = $input->getOption('username');
        $password = $input->getOption('password');

        if ('generated' === $password) {
            $password = $this->generatePassword();
        }

        $installer = new SiteInstaller($this->getApplication());
        $installer->install($profile, $output);

        $io->writeln(sprintf("Password for user '%s' is: %s", $username, $password));

        return 0;
    }
}
