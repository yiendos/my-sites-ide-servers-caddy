<?php

namespace Yiendos\MySitesIde\Servers\Caddy\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the caddy compose service from the host. Every call goes through
 * `docker compose` from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithCaddy
{
    /**
     * The caddy CLI's arguments for the config the container runs
     */
    private string $caddyConfig = '--config /etc/caddy/Caddyfile --adapter caddyfile';

    /**
     * Whether the caddy container is up
     *
     * @return bool
     */
    protected function caddyRunning(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running caddy 2>/dev/null')) !== '';
    }

    /**
     * Checks the config, every site's Caddyfile included, with `caddy
     * validate` - in the running container when there is one, otherwise
     * (or when $fresh) a throwaway one built from the current compose file
     *
     * @param OutputInterface $output
     * @param bool $fresh test in a throwaway container even if caddy is running
     * @return int the caddy exit code, 0 when the config is good
     */
    protected function caddyConfigTest(OutputInterface $output, bool $fresh = false): int
    {
        $command = !$fresh && $this->caddyRunning()
            ? "exec -T caddy caddy validate {$this->caddyConfig}"
            : "run --rm --no-deps -T caddy caddy validate {$this->caddyConfig}";

        return $this->compose($output, $command);
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
