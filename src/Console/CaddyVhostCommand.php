<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Paths;

class CaddyVhostCommand extends Command
{
    /**
     * The placeholder in stubs/Caddyfile replaced with the site name
     */
    private const PLACEHOLDER = '__PROJECT__';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:caddy-vhost')
            ->setDescription("Create a site's Caddy config (Repos/<site>/_build/config/Caddyfile) from the sample")
            ->addArgument('site', InputArgument::REQUIRED, 'The site folder under Repos/, e.g. example')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite the site\'s existing Caddyfile with the sample')
        ;
    }

    /**
     * Run by ide:create-site and ide:repo-clone through the plugin's
     * site-created hook, or by hand for a site that predates the plugin.
     *
     * An existing Caddyfile is left alone - a cloned repository may bring
     * its own.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!is_dir(Paths::root() . "/Repos/{$site}")) {
            $io->error("Repos/{$site} does not exist - create or clone the site first.");
            return Command::FAILURE;
        }

        $config = Paths::siteConfig($site, Paths::SITE_FILE);

        if (file_exists($config) && !$input->getOption('force')) {
            $io->warning('Caddy config already present, leaving it alone (--force to overwrite it with the sample): ' . Paths::relative($config));
            return Command::SUCCESS;
        }

        if (!is_dir(Paths::siteConfig($site))) {
            mkdir(Paths::siteConfig($site), 0755, true);
        }

        $sample = (string) file_get_contents(Paths::package('stubs/Caddyfile'));

        file_put_contents($config, str_replace(self::PLACEHOLDER, $site, $sample));

        $io->writeln("<info>Created " . Paths::relative($config) . "</> - https://{$site}.localhost:" . (getenv('CADDY_PORT') ?: '9443') . " once caddy reloads (servers:caddy-reload).");

        return Command::SUCCESS;
    }
}
