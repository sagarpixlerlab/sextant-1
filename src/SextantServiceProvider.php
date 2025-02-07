<?php

namespace Amondar\Sextant;

use Illuminate\Support\ServiceProvider;

/**
 * Class SextantServiceProvider
 *
 * @version 1.0.0
 * @date    2019-02-28
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class SextantServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Parser configs.
        $configPath = __DIR__ . '/config/sextant.php';

        // Load default configuration if it is not cached.
        if ( ! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom($configPath, 'sextant');
        }

        $this->publish($configPath, config_path('sextant.php'), 'config');

        $this->app->singleton('sextant', function () {
            return new SextantWrapper();
        });
    }

    /**
     * Publish the config file
     *
     * @param  string $path
     * @param         $pathTo
     */
    protected function publish($path, $pathTo, $tag)
    {
        $this->publishes([ $path => $pathTo ], $tag);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        app('db')->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [ 'sextant' ];
    }
}