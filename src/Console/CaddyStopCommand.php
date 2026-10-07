<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Traits\InteractsWithCaddy;

class CaddyStopCommand extends Command
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
            ->setName('servers:caddy-stop')
            ->setDescription('Stop the Caddy container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so servers:caddy-start brings back the
     * same container. The next ide:spark starts it again (autostart).
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->caddyRunning()) {
            $io->writeln('Caddy is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop caddy') !== 0) {
            $io->error('Caddy did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Caddy stopped.');

        return Command::SUCCESS;
    }
}
