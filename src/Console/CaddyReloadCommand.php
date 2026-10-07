<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Traits\InteractsWithCaddy;

class CaddyReloadCommand extends Command
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
            ->setName('servers:caddy-reload')
            ->setDescription('Reload Caddy to pick up config and site changes, without dropping connections')
        ;
    }

    /**
     * `caddy reload` hands the new config to the running server's admin API,
     * which only swaps it in if it loads - a broken config is rejected and
     * the old one keeps serving, so no separate test is needed first. Only
     * caddy is touched, unlike ide:restart.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->caddyRunning()) {
            $io->error('Caddy is not running - start it with servers:caddy-start.');
            return Command::FAILURE;
        }

        if ($this->compose($output, "exec -T caddy caddy reload {$this->caddyConfig}") !== 0) {
            $io->error('Caddy did not reload - the config above has errors, and the running server is untouched.');
            return Command::FAILURE;
        }

        $io->success('Caddy reloaded.');

        return Command::SUCCESS;
    }
}
