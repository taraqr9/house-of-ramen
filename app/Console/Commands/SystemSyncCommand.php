<?php

namespace App\Console\Commands;

use Database\Seeders\AdminSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Console\Command;

class SystemSyncCommand extends Command
{
    protected $signature = 'system:sync';

    protected $description = 'Sync system permissions and menus safely';

    public function handle(): int
    {
        $this->info('System sync started...');

        $this->info('Syncing permissions and Super Admin role...');

        $this->call('db:seed', [
            '--class' => AdminSeeder::class,
            '--force' => true,
        ]);

        $this->info('Permissions and Super Admin role synced successfully.');

        $this->info('Syncing menus...');

        $this->call('db:seed', [
            '--class' => MenuSeeder::class,
            '--force' => true,
        ]);

        $this->info('Menus synced successfully.');

        $this->info('Clearing application cache...');

        $this->call('optimize:clear');

        $this->info('Application cache cleared successfully.');

        $this->info('System sync completed successfully.');

        return self::SUCCESS;
    }
}
