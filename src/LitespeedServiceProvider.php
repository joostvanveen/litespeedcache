<?php

namespace Joostvanveen\Litespeedcache;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LitespeedServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->app->bind('litespeedcache', function () {
            return new Cache();
        });
    }
}
