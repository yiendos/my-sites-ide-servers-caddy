<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Caddy\Paths;

class CaddyTrustCommand extends Command
{
    /**
     * Caddy's local CA root, under XDG_DATA_HOME (/storage/data) in the container
     */
    private const ROOT_CERTIFICATE = 'data/caddy/pki/authorities/local/root.crt';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:caddy-trust')
            ->setDescription("Show how to trust Caddy's local certificate authority, so browsers accept its https://<site>.localhost certificates")
            ->addOption('install', null, InputOption::VALUE_NONE, 'macOS only: add it to the System keychain now (asks for your password, via sudo)')
        ;
    }

    /**
     * Caddy signs every site's certificate with its own local CA. Caddy can't
     * trust that CA for you from inside a container (skip_install_trust in
     * conf/Caddyfile), so this points at the root certificate in
     * storage/plugins/caddy/ and the host command that trusts it. It's
     * created once, on caddy's first start, and kept across rebuilds - so
     * trusting it is a one-off, unless storage/plugins/caddy/ is deleted.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $certificate = Paths::storage(self::ROOT_CERTIFICATE);

        if (!file_exists($certificate)) {
            $io->error('No local CA yet - Caddy creates it on first start. Run servers:caddy-start, then try again.');
            return Command::FAILURE;
        }

        $io->writeln('Caddy\'s local CA root certificate: <info>' . Paths::relative($certificate) . '</>');

        if (PHP_OS_FAMILY !== 'Darwin') {
            $io->writeln([
                '',
                'Add it to your system\'s trusted certificates, e.g. on Debian/Ubuntu:',
                '',
                '  sudo cp ' . escapeshellarg($certificate) . ' /usr/local/share/ca-certificates/my-sites-ide-caddy.crt',
                '  sudo update-ca-certificates',
                '',
                'Firefox keeps its own list: Settings > Privacy & Security > Certificates > View Certificates > Import.',
            ]);
            return Command::SUCCESS;
        }

        exec('security verify-cert -c ' . escapeshellarg($certificate) . ' 2>/dev/null', $ignored, $code);

        if ($code === 0) {
            $io->success('Your Mac already trusts it - https://<site>.localhost:' . (getenv('CADDY_PORT') ?: '9443') . ' has no certificate warnings.');
            return Command::SUCCESS;
        }

        $trust = 'sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ' . escapeshellarg($certificate);

        if (!$input->getOption('install')) {
            $io->writeln(['', 'Your Mac doesn\'t trust it yet. Run:', '', "  {$trust}", '', 'or this command again with --install. Restart your browser afterwards.']);
            return Command::SUCCESS;
        }

        $output->writeLn($trust);
        passthru($trust, $code);

        if ($code !== 0) {
            $io->error('The certificate was not added - see above.');
            return Command::FAILURE;
        }

        $io->success('Trusted. Restart your browser to pick it up.');

        return Command::SUCCESS;
    }
}
