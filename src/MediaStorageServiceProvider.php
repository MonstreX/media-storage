<?php

namespace MonstreX\MediaStorage;

use Illuminate\Support\ServiceProvider;

class MediaStorageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('media', function () {
            return new MediaStorage;
        });

        $this->publishes([dirname(__DIR__).'/publishable/config/media-storage.php' => config_path('media-storage.php')],'config');
    }


    public function boot()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/media-storage.php', 'media-storage'
        );

        $this->loadMigrationsFrom(realpath(__DIR__.'/../migrations'));
    }

}
