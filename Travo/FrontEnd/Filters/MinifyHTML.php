<?php 

namespace Travo\FrontEnd\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class MinifyHTML implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // No action needed before request
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Run optimization ONLY on exactly '/webstories' or '/webstories/' (landing page)
        $uri = $request->getUri()->getPath();

        // Normalize URI to ensure no trailing slash for consistent checking
        $uriNormalized = rtrim($uri, '/');

        // If URI starts with "/webstories/" and is NOT exactly "/webstories", skip optimization
        if (strpos($uriNormalized, '/webstories/') === 0 && $uriNormalized !== '/webstories') {
            return $response; 
        }

        if (stripos($response->getHeaderLine('Content-Type'), 'text/html') === false) {
            return $response;
        }

        $html = $response->getBody();
        $minifiedHtml = $this->minifyHtml($html);

        $response->setBody($minifiedHtml);
        return $response;
    }


private function minifyHtml(string $buffer): string
{
    preg_match_all('#<script\b[^>]*>.*?</script>#is', $buffer, $scriptBlocks);
    $htmlWithoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '%%SCRIPT%%', $buffer);

    $search = [
        '/\>[^\S ]+/s',
        '/[^\S ]+\</s',
        '/(\s)+/s',
        '/<!--(?!\[if).*?-->/'
    ];
    $replace = [
        '>',
        '<',
        '\\1',
        ''
    ];

    $minified = preg_replace($search, $replace, $htmlWithoutScripts);

    foreach ($scriptBlocks[0] as $script) {
        $minified = preg_replace('/%%SCRIPT%%/', $script, $minified, 1);
    }

    return $minified;
}

}