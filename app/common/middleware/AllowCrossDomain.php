<?php

namespace app\common\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AllowCrossDomain implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $headers = [
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, Ba-Token, Batoken, lang, server, xtoken, x-user-token',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Expose-Headers'    => 'xtoken',
        ];
        $origin = (string) $request->header('origin', '*');
        $allowDomains = array_filter(array_map('trim', explode(',', (string) config('xframe.cors_request_domain', ''))));
        $headers['Access-Control-Allow-Origin'] = (!$allowDomains || in_array(parse_url($origin, PHP_URL_HOST) ?: $origin, $allowDomains, true)) ? $origin : '*';

        if ($request->method() === 'OPTIONS') {
            return response('', 204, $headers);
        }

        return $handler($request)->withHeaders($headers);
    }
}
