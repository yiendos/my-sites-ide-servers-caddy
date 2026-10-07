<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Traits\InteractsWithCaddy;

class CaddyStartCommand extends Command
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
            ->setName('servers:caddy-start')
            ->setDescription('Start the Caddy container - or recreate it if its compose config changed - testing the config first')
        ;
    }

    /**
     * Tests the config before starting - a broken one would otherwise leave
     * the container exiting straight after `up` with nothing on screen.
     *
     * Always runs `up -d`, even when caddy is already running: it's a no-op
     * for an up-to-date container, and recreates one whose compose config
     * has changed. The test runs in a throwaway container for the same
     * reason, as the running one may be the stale one.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->caddyConfigTest($output, fresh: true) !== 0) {
            $io->error('Caddy configuration has errors - not starting.');
            return Command::FAILURE;
        }

        if ($this->compose($output, 'up -d caddy') !== 0) {
            $io->error('Caddy did not start - see above.');
            return Command::FAILURE;
        }

        $io->success('Caddy started - https://default.localhost:' . (getenv('CADDY_PORT') ?: '9443'));

        return Command::SUCCESS;
    }
}
