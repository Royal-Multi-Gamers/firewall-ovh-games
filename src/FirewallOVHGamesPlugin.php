<?php

namespace RoyalMultiGamers\FirewallOVHGames;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FirewallOVHGamesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'firewall-ovh-games';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        // Discover and register Filament resources
        $panel->discoverResources(
            plugin_path($this->getId(), "src/Filament/$id/Resources"),
            "RoyalMultiGamers\\FirewallOVHGames\\Filament\\$id\\Resources"
        );

        // Discover and register Filament pages
        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "RoyalMultiGamers\\FirewallOVHGames\\Filament\\$id\\Pages"
        );

        // Discover and register Filament widgets
        $panel->discoverWidgets(
            plugin_path($this->getId(), "src/Filament/$id/Widgets"),
            "RoyalMultiGamers\\FirewallOVHGames\\Filament\\$id\\Widgets"
        );
    }

    public function boot(Panel $panel): void
    {
        // Is run only when the panel that the plugin is being registered to is actually in-use.
    }
}
