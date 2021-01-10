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
    }


    public function boot()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/media-storage.php', 'media-storage'
        );
    }

}
