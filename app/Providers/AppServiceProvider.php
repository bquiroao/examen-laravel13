<?php

namespace App\Providers;

   use App\Services\Access\PermissionResolver;
   use App\Services\Hospital\HospitalDatabase;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
           $this->app->scoped(PermissionResolver::class);
   $this->app->singleton(HospitalDatabase::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
