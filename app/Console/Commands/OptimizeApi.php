<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class OptimizeApi extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api:optimize {--clear : Clear all caches before optimizing}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize API performance by caching routes, config, and clearing old caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting API Optimization...');
        $this->newLine();

        if ($this->option('clear')) {
            $this->warn('Clearing all caches first...');
            $this->clearCaches();
            $this->newLine();
        }

        // 1. Cache config
        $this->info('📦 Caching configuration...');
        Artisan::call('config:cache');
        $this->line('   ✅ Config cached');

        // 2. Cache routes
        $this->info('🛤️  Caching routes...');
        Artisan::call('route:cache');
        $this->line('   ✅ Routes cached');

        // 3. Cache views
        $this->info('👁️  Caching views...');
        Artisan::call('view:cache');
        $this->line('   ✅ Views cached');

        // 4. Cache events
        $this->info('📡 Caching events...');
        Artisan::call('event:cache');
        $this->line('   ✅ Events cached');

        // 5. Optimize autoloader
        $this->info('⚡ Optimizing autoloader...');
        exec('composer dump-autoload --optimize --no-dev 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            $this->line('   ✅ Autoloader optimized');
        } else {
            $this->line('   ⚠️  Autoloader optimization skipped (run manually)');
        }

        $this->newLine();
        $this->info('✨ API Optimization Complete!');
        $this->newLine();

        // Show cache driver info
        $cacheDriver = config('cache.default');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Driver', $cacheDriver],
                ['Session Driver', config('session.driver')],
                ['Queue Driver', config('queue.default')],
            ]
        );

        $this->newLine();
        $this->warn('💡 Tips for better performance:');
        $this->line('   • Use Redis for caching: CACHE_DRIVER=redis');
        $this->line('   • Use Redis for sessions: SESSION_DRIVER=redis');
        $this->line('   • Run "php artisan migrate" to add database indexes');
        $this->line('   • Enable OPcache in PHP for faster code execution');

        return Command::SUCCESS;
    }

    /**
     * Clear all caches
     */
    private function clearCaches(): void
    {
        Artisan::call('cache:clear');
        $this->line('   ✅ Application cache cleared');

        Artisan::call('config:clear');
        $this->line('   ✅ Config cache cleared');

        Artisan::call('route:clear');
        $this->line('   ✅ Route cache cleared');

        Artisan::call('view:clear');
        $this->line('   ✅ View cache cleared');

        Artisan::call('event:clear');
        $this->line('   ✅ Event cache cleared');
    }
}

