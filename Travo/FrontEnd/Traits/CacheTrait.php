<?php

namespace Travo\FrontEnd\Traits;

use Travo\FrontEnd\LIbraries\StaticPageCache;

trait CacheTrait
{
    protected function serveStaticPage(): void
    {
        $cache = new StaticPageCache();
        $cache->serve();
    }

    protected function storeStaticPage(string $content): void
    {
        $cache = new StaticPageCache();
        $cache->store($content);
    }
}