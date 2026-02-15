# Firewall OVH Games Plugin for Pelican Panel

Automatically synchronize OVH Game firewall rules from Pelican allocations.

## Features

- Automatic node IP discovery
- Multi-IP OVH mapping
- Scheduled synchronization (panel scheduler)
- Event-driven synchronization (create/update/delete allocation)
- Queue-first execution for production
- Filament admin pages (settings, IP configs, sync logs)
- EN/FR translations

## Requirements

- Pelican Panel `v1.0.0-beta31+`
- PHP `8.2+`
- Composer
- OVH API credentials with firewall permissions

## Installation

1) Install plugin

```bash
cd /var/www/pelican
php artisan p:plugin:install
```

2) Ensure OVH SDK is installed (auto via `plugin.json` in most setups, manual fallback below)

```bash
composer require ovh/ovh:^3.0
```

3) Run migrations and clear caches

```bash
php artisan migrate
php artisan optimize:clear
```

4) Restart workers after deploy/update

```bash
php artisan queue:restart
```

## OVH API Permissions

Create credentials on OVH API and grant at least:

- `GET /ip/*/game/*/rule`
- `GET /ip/*/game/*/rule/*`
- `POST /ip/*/game/*/rule`
- `DELETE /ip/*/game/*/rule/*`

## Configuration

1) Open **Admin → Firewall OVH → OVH Firewall Settings**

- Application Key
- Application Secret
- Endpoint (typically `ovh-eu`)
- Consumer Key

2) Configure sync

- Sync enabled
- Sync interval (minutes)
- Sync on allocation events
- Default protocol (`other` by default)

3) Configure IP mapping in **Firewall OVH → IP Configurations**

- `panel_ip`: node IP seen by Pelican
- `ovh_ip`: OVH IP (can include CIDR, e.g. `x.x.x.x/30`)
- `ovh_ip_on_game`: OVH game IP identifier
- `enabled`: true

## Queue (Recommended)

In `.env`:

```env
QUEUE_CONNECTION=redis
OVH_FIREWALL_USE_QUEUE=true
OVH_FIREWALL_QUEUE_NAME=default
OVH_FIREWALL_LOGGING_ENABLED=false
```

Worker example:

```bash
php artisan queue:work --queue=default --sleep=1 --tries=3 --timeout=120
```

### systemd Example

```ini
[Unit]
Description=Pelican Queue Worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/pelican
ExecStart=/usr/bin/php /var/www/pelican/artisan queue:work --queue=default --sleep=1 --tries=3 --timeout=120
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now pelican-queue
sudo systemctl status pelican-queue
```

## Behavior Notes

- Full sync only processes IPs with an enabled OVH configuration.
- IPs without configuration are skipped (no noisy warning spam).
- Allocation creation can add the OVH rule even when `server_id = null`.
- Allocation deletion removes the OVH rule.
- Port change uses OVH-safe flow: **delete old rule → wait until removed → add new rule**.

## Commands

```bash
php artisan firewall:test-connection
php artisan firewall:sync
php artisan firewall:sync --force
php artisan firewall:sync-node-ips
```

## Troubleshooting

### `Class "Ovh\Api" not found`

Run in panel root:

```bash
composer require ovh/ovh:^3.0
composer dump-autoload -o
php artisan optimize:clear
php artisan queue:restart
sudo systemctl restart pelican-queue
```

### `Class "...OvhFirewallIpConfig" not found`

Usually stale autoload/deploy state:

```bash
composer dump-autoload -o
php artisan optimize:clear
php artisan queue:restart
sudo systemctl restart pelican-queue
```

### Sync does not apply expected port changes

- Check IP mapping (`panel_ip`, `ovh_ip`, `ovh_ip_on_game`, `enabled`)
- Run `php artisan firewall:sync --force`
- Inspect **Firewall OVH → Sync Logs**

## Development Notes

- Package dependency is declared in `plugin.json` via `composer_packages`.
- Main plugin namespace: `RoyalMultiGamers\FirewallOVHGames\`.

## License

MIT

## Credits

Royal Multi Gamers
