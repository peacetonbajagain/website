<?php

namespace Travo\FrontEnd\Traits;

use Travo\FrontEnd\Controllers\LIbraries\StaticPageCache;

trait CacheTrait
{
    protected function serveStaticPage(): void
    {
        (new StaticPageCache())->serve();
    }

    protected function storeStaticPage(string $content): void
    {
        (new StaticPageCache())->store($content);
    }
}