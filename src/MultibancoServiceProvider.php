<?php

namespace tricciardi\LaravelMultibanco;

use Illuminate\Support\ServiceProvider;

class MultibancoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
      $this->loadRoutesFrom(__DIR__.'/routes.php');
      $this->publishes([
                          __DIR__.'/config/multibanco.php' => config_path('multibanco.php'),
                          __DIR__.'/views' => resource_path('views/vendor/multibanco'),
                        ]);
      $this->loadMigrationsFrom(__DIR__.'/migrations');
      $this->loadViewsFrom(__DIR__.'/views', 'multibanco');

      //set commands
      if ($this->app->runningInConsole()) {
          $this->commands([
              Commands\GetDailyPayments::class,
              Commands\GetPayments::class,
          ]);
      }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
      $this->mergeConfigFrom( __DIR__.'/config/multibanco.php', 'multibanco' );
    }
}
