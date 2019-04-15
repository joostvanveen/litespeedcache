<?php
namespace Joostvanveen\Litespeedcache\Facades;

use Illuminate\Support\Facades\Facade;

class LitespeedCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'litespeedcache';
    }
}
