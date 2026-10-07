# Caddy server

[Caddy](https://caddyserver.com/) 2 for [my-sites-ide](https://github.com/yiendos/my-sites-ide),
serving every site in `Repos/` over HTTPS on port 9443 and handing PHP to the IDE's `fpm`
container. Caddy issues each site's certificate from its own local certificate authority, so
once you've trusted that CA, `https://<site>.localhost:9443` opens without a warning. It runs
alongside the [nginx plugin](https://github.com/yiendos/my-sites-ide-servers-nginx) (443) and the apache plugin (8443), or instead of them.

Written for: developers running sites in my-sites-ide who want them served by Caddy, whether or
not they've used Caddy before.

## Contents

- [Installation](#installation)
- [Architecture](#architecture)
- [Site config](#site-config)
- [Trusting Caddy's certificates](#trusting-caddys-certificates)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. It needs a my-sites-ide with
`"storage": true` support (my-sites-ide #69). Add it to the `require` section of the IDE's
`composer.local.json`:

```json
"yiendos/my-sites-ide-servers-caddy": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide servers:caddy-start     # or ide:spark, which starts it alongside APP
php my-sites-ide servers:caddy-trust     # once: trust Caddy's certificates in your browser
```

Composer's `post-autoload-dump` hook registers the `servers:caddy-*` commands, the `caddy`
compose service, and the plugin's `site-created` hook. It also creates
`storage/plugins/caddy/`, which the IDE mounts into the container at `/storage`. The service has
`autostart: true`, so `ide:spark` starts it - you don't need to list `caddy` in `APP`.

Then visit https://default.localhost:9443/.

## Architecture

```
host (my-sites-ide CLI)
  |- ide:create-site / ide:repo-clone --> site-created hook --> servers:caddy-vhost <site>
  |                                                             writes Repos/<site>/_build/config/Caddyfile
  |- servers:caddy-test               --> caddy validate (in the running container, or a throwaway one)
  |- servers:caddy-reload             --> caddy reload, through Caddy's admin API in the container
  |- servers:caddy-start / -stop      --> docker compose up -d / stop caddy
  |- servers:caddy-trust              --> points at the local CA's root certificate, trusts it on macOS

browser --https:9443--> caddy container (official caddy:2.x-alpine image, pinned in docker-compose.yml)
                          |- /etc/caddy/Caddyfile (conf/Caddyfile): the default site, then
                          |    import /opt/repos/*/_build/config/Caddyfile
                          |- /storage (storage/plugins/caddy/): the local CA and issued certificates
                          |- static files served straight from /opt/repos/<site>/Sites/public
                          |- *.php ---- php_fastcgi fpm:9000 ----> IDE fpm container
```

## Site config

`conf/Caddyfile` serves the IDE's default site, then imports every site's own config:

```
import /opt/repos/*/_build/config/Caddyfile
```

So a site is served once `Repos/<site>/_build/config/Caddyfile` exists and Caddy has reloaded
(`php my-sites-ide servers:caddy-reload`).

This is **one file per site, always named `Caddyfile`** - unlike nginx and apache, which load any
number of `*-nginx.conf` / `*-apache.conf` files. Caddy's `import` allows a single `*` in a
pattern, and the folder already uses it. A site that needs more can `import` further files from
its own `Caddyfile`.

`ide:create-site` and `ide:repo-clone` create that file for you through this plugin's
`site-created` hook. It copies `stubs/Caddyfile`, replacing `__PROJECT__` with the site name:

```
https://<site>.localhost {
	tls internal
	root * /opt/repos/<site>/Sites/public
	php_fastcgi {$FPM_HOST}
	file_server
	encode gzip
}
```

A site that already has a `Caddyfile` (a cloned repository may bring its own) is left alone;
`--force` overwrites it with the sample. For sites that existed before the plugin was installed,
run the command yourself:

```
php my-sites-ide servers:caddy-vhost <site>
php my-sites-ide servers:caddy-reload
```

## Trusting Caddy's certificates

`tls internal` has Caddy sign each site's certificate with its own local CA. Caddy creates the CA
on first start, in `storage/plugins/caddy/data/caddy/pki/authorities/local/`, and keeps it across
container rebuilds and `composer update`. Browsers warn about every site until you trust its root
certificate - once, on your machine:

```
php my-sites-ide servers:caddy-trust             # where the certificate is, whether it's trusted, the command to trust it
php my-sites-ide servers:caddy-trust --install   # macOS: add it to the System keychain (asks for your password)
```

Restart your browser afterwards. Firefox keeps its own certificate list - import the root
certificate there too if you use it. Caddy can't do this itself from inside a container, which
is why `conf/Caddyfile` sets `skip_install_trust`.

Deleting `storage/plugins/caddy/` creates a new CA on the next start, which you'd need to trust
again.

## Command reference

| Command | What it does |
|---|---|
| `servers:caddy-vhost <site>` | Create `Repos/<site>/_build/config/Caddyfile` from the sample, unless the site already has one |
| `servers:caddy-vhost <site> --force` | Same, overwriting the site's existing `Caddyfile` |
| `servers:caddy-test` | Validate the config, every site's `Caddyfile` included (`caddy validate`). Uses the running container, or a throwaway one when caddy is stopped |
| `servers:caddy-reload` | Reload the config without dropping connections (`caddy reload`). A config with errors is rejected, and the running server carries on with the old one |
| `servers:caddy-start` | Validate in a throwaway container, then `docker compose up -d caddy`. Also recreates a running container whose compose config has changed |
| `servers:caddy-stop` | `docker compose stop caddy`, leaving the rest of the IDE running. The next `ide:spark` starts it again |
| `servers:caddy-trust [--install]` | Show Caddy's local CA root certificate and whether your Mac trusts it; `--install` trusts it (macOS) |

After editing a site's `Caddyfile`, `servers:caddy-reload` is quicker than `ide:restart`, which
restarts every container.

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `CADDY_PORT` | `9443` | The host port Caddy serves HTTPS on. nginx has 443 and the apache plugin 8443 |

The default lives in this plugin's `.env`. To override it, set it in the IDE's root `.env` -
`php my-sites-ide ide:plugin-env yiendos/my-sites-ide-servers-caddy` copies it there, commented
out - then run `servers:caddy-start` to recreate the container.

To change how Caddy behaves:

- **One site**: edit its `Repos/<site>/_build/config/Caddyfile` and run `servers:caddy-reload`.
  The place for redirects, headers, extra hostnames, or a certificate of your own.
- **All sites**: edit `conf/Caddyfile` in this package (global options, the default site) and run
  `servers:caddy-reload`. With a `Packages/` clone, that's your working copy; otherwise copy the
  change upstream, as `composer update` replaces `vendor/`.
- **The Caddy version**: change the pinned `image:` tag in `docker-compose.yml`, then
  `servers:caddy-start`.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `Repos/` | mounted at `/opt/repos` - the sites and their `Caddyfile`s |
| `storage/plugins/caddy/` (`"storage": true`) | mounted at `/storage` - Caddy's local CA, certificates and autosaved config |
| the `fpm` service (`fpm:9000`) | PHP, through `php_fastcgi {$FPM_HOST}` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | reaching `Repos/` from `vendor/` |
| the `my-sites-ide` network | reaching `fpm` |

## Troubleshooting

**The browser warns the certificate isn't trusted.** Caddy's local CA isn't trusted yet - run
`php my-sites-ide servers:caddy-trust`, then restart the browser. If it says the CA is already
trusted, `storage/plugins/caddy/` may have been deleted since, giving Caddy a new CA - trust the
new one.

**https://<site>.localhost:9443 shows the default site, or nothing.** The site has no
`Caddyfile`, or Caddy hasn't reloaded since it was added. Check
`ls Repos/<site>/_build/config/Caddyfile`, then `php my-sites-ide servers:caddy-reload`.

**`servers:caddy-reload`, `-test` or `-start` reports an error.** The message names the file and
line, usually a site's `Caddyfile`. The running server keeps its old config until it's fixed.
`ambiguous site definition` means two `Caddyfile`s claim the same hostname.

**Static files load but PHP pages return 502.** Caddy can't reach `fpm`. Make sure the `fpm`
service is running (`docker compose ps fpm`), as it's an IDE service, not part of this plugin.

**"No files matching import glob pattern" in the logs.** Harmless: no site has a `Caddyfile` yet.

**Port 9443 is already allocated.** Another container or host process has it. Set `CADDY_PORT` in
the root `.env` and run `servers:caddy-start`.

## Known gaps

- One config file per site, always `Caddyfile` - see [Site config](#site-config).
- Sites that predate the plugin need `servers:caddy-vhost <site>` run by hand; `site-created`
  only fires for new sites.
- The container runs as root (the official image's default). On a Linux host, the files Caddy
  writes in `storage/plugins/caddy/` are owned by root.
- Only HTTPS over TCP is published - no plain HTTP and no HTTP/3 (UDP 443).
- Certificates from the certbot-cloudflare plugin (`storage/certificates/`) aren't mounted; sites
  use Caddy's local CA.
- Unlike nginx, caddy has no `IDE_SITE_ALIAS` network alias, so other containers (e.g. the zaproxy
  plugin) reach sites through nginx, not caddy.
