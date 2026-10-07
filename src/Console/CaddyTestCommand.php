<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Traits\InteractsWithCaddy;

class CaddyTestCommand extends Command
{
    use InteractsWithCaddy;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:caddy-test')
            ->setDescription("Test Caddy's configuration, including every site's Caddyfile")
        ;
    }

    /**
     * Works whether or not caddy is running, so a site's config can be
     * checked before the server ever loads it
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->caddyConfigTest($output) !== 0) {
            $io->error('Caddy configuration has errors - see above.');
            return Command::FAILURE;
        }

        $io->success('Caddy configuration is valid.');

        return Command::SUCCESS;
    }
}
