<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Tests;

use Filament\Facades\Filament;
use Liberu\Ecommerce\Shipping\Filament\Tests\Fixtures\TestPanelProvider;
use Liberu\Ecommerce\Shipping\ShippingServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\TestUser;
use Liberu\PackageTestbench\UsesTestUser;

/**
 * Filament is the one dependency `PackageTestCase`'s scoped discovery cannot
 * cover on its own: it registers `extra.laravel.providers` of this package's
 * *direct* dependencies, and a panel needs support, schemas, forms, tables,
 * actions, notifications, widgets, Livewire and the icon packages — all
 * transitive. So this case widens the walk to everything installed, which is
 * what Laravel's own discovery does in an application.
 *
 * The shipping domain provider is named explicitly for the opposite reason: a
 * Liberu module declares `extra.laravel.providers` empty on purpose, so
 * installing one never implies booting it. The tables have to exist here.
 */
abstract class TestCase extends PackageTestCase
{
    use UsesTestUser;

    protected function setUp(): void
    {
        parent::setUp();

        // No route is being visited, so nothing has resolved a panel from the
        // request; a resource page needs one to be current before it can mount.
        Filament::setCurrentPanel('admin');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', TestUser::class);

        // Livewire swallows a TypeError into a bare 419 unless debug is on, which
        // turns any signature mistake in a schema closure into "Page Expired".
        $app['config']->set('app.debug', true);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return array_values(array_unique([
            ...$this->discoveredProviders(),
            ShippingServiceProvider::class,
            ...parent::getPackageProviders($app),
            TestPanelProvider::class,
        ]));
    }

    /** @return array<int, class-string> */
    private function discoveredProviders(): array
    {
        $installed = json_decode(
            (string) file_get_contents($this->packageRoot().'/vendor/composer/installed.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $providers = [];

        foreach ($installed['packages'] ?? [] as $package) {
            foreach ((array) ($package['extra']['laravel']['providers'] ?? []) as $provider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
