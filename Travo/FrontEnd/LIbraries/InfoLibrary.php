<?php
namespace Travo\FrontEnd\Controllers\LIbraries;

class InfoLibrary
{
    public static function info(): array
    {
        $request = service('request');
        $ip = $request->getIPAddress();
        $maxmind = null;
        $readerClass = 'MaxMind\\Db\\Reader';
        if (class_exists($readerClass) && is_file('maxmind/GeoLite2-City.mmdb')) {
            try {
                $reader = new $readerClass('maxmind/GeoLite2-City.mmdb');
                $maxmind = $reader->get($ip);
            } catch (\Throwable) {
                $maxmind = null;
            }
        }

        $info['continent_code'] = $maxmind['continent']['code'] ?? null;
        $info['country_name'] = $maxmind['country']['iso_code'] ?? null;
        $info['city_name'] = $maxmind['city']['names']['en'] ?? null;
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $whichBrowserClass = 'WhichBrowser\\Parser';
        $whichbrowser = class_exists($whichBrowserClass) ? new $whichBrowserClass($userAgent) : null;
        $info['browser_name'] = $whichbrowser->browser->name ?? null;
        $info['os_name'] = $whichbrowser->os->name ?? null;
        $info['browser_language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
        $info['device_type'] = \function_exists('get_device_type') ? get_device_type($userAgent) : null;

        return $info;
    }
}