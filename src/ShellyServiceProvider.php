<?php

namespace DaedalosLabs\FilamentShelly;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShellyServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-shelly';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasTranslations()
            ->hasMigration('create_filament_shelly_tables')
            ->hasInstallCommand(fn (InstallCommand $command) => $command
                ->publishMigrations()
                ->askToRunMigrations()
                ->askToStarRepoOnGitHub('daedaloslabs/filament-shelly'));
    }
}
