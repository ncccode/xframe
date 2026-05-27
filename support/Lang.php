<?php

declare(strict_types=1);

namespace support;

use Throwable;
use Webman\Http\Request;

class Lang
{
    protected static array $packages = [];

    public static function get(string $name, array $vars = [], string $lang = ''): string
    {
        if ($name === '') {
            return $name;
        }

        $config = self::config();
        $locale = self::resolveLocale($lang, $config);
        $locales = self::lookupLocales($locale, $config);
        [$app, $controllerPath] = self::requestContext();

        foreach ($locales as $lookupLocale) {
            foreach (self::packageFiles($app, $controllerPath, $lookupLocale, $config) as $file) {
                $messages = self::loadPackage($file);
                if (array_key_exists($name, $messages)) {
                    return self::format((string) $messages[$name], $vars);
                }
            }
        }

        return self::format($name, $vars);
    }

    protected static function config(): array
    {
        $config = config('translation', []);

        return is_array($config) ? $config : [];
    }

    protected static function resolveLocale(string $lang, array $config): string
    {
        $supported = self::supportedLocales($config);

        if ($lang !== '') {
            return self::normalizeLocale($lang, $supported);
        }

        $request = self::request();
        if ($request) {
            $headerName = (string) ($config['header'] ?? 'lang');
            $headerLocale = (string) $request->header($headerName, '');
            if ($headerLocale !== '') {
                $matched = self::matchSupportedLocale($headerLocale, $supported);
                if ($matched !== null) {
                    return $matched;
                }
            }

            $acceptLocale = self::preferredAcceptLanguage((string) $request->header('accept-language', ''), $supported);
            if ($acceptLocale !== null) {
                return $acceptLocale;
            }
        }

        return self::normalizeLocale((string) ($config['locale'] ?? 'zh-cn'), $supported);
    }

    protected static function lookupLocales(string $locale, array $config): array
    {
        $supported = self::supportedLocales($config);
        $locales = [$locale];

        foreach ((array) ($config['fallback_locale'] ?? []) as $fallbackLocale) {
            $locales[] = self::normalizeLocale((string) $fallbackLocale, $supported);
        }

        return array_values(array_unique(array_filter($locales)));
    }

    protected static function supportedLocales(array $config): array
    {
        $locales = (array) ($config['supported_locale'] ?? []);
        if (!$locales) {
            $locales = array_merge(
                [(string) ($config['locale'] ?? 'zh-cn')],
                (array) ($config['fallback_locale'] ?? [])
            );
        }

        $normalized = [];
        foreach ($locales as $locale) {
            $locale = self::simpleNormalize((string) $locale);
            if ($locale !== '') {
                $normalized[] = $locale;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected static function normalizeLocale(string $locale, array $supportedLocales = []): string
    {
        $locale = self::simpleNormalize($locale);
        if ($locale === '') {
            return $locale;
        }

        return self::matchSupportedLocale($locale, $supportedLocales) ?? $locale;
    }

    protected static function simpleNormalize(string $locale): string
    {
        return strtolower(str_replace('_', '-', trim($locale)));
    }

    protected static function matchSupportedLocale(string $locale, array $supportedLocales): ?string
    {
        $locale = self::simpleNormalize($locale);
        if ($locale === '') {
            return null;
        }

        if (in_array($locale, $supportedLocales, true)) {
            return $locale;
        }

        $language = strtok($locale, '-') ?: $locale;
        if (in_array($language, $supportedLocales, true)) {
            return $language;
        }

        foreach ($supportedLocales as $supportedLocale) {
            $supportedLanguage = strtok($supportedLocale, '-') ?: $supportedLocale;
            if ($supportedLanguage === $language) {
                return $supportedLocale;
            }
        }

        return null;
    }

    protected static function preferredAcceptLanguage(string $header, array $supportedLocales): ?string
    {
        if ($header === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $index => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            [$locale, $parameters] = array_pad(explode(';', $part, 2), 2, '');
            $quality = 1.0;
            if ($parameters !== '' && preg_match('/(?:^|;)\s*q=([0-9.]+)/i', $parameters, $matches)) {
                $quality = max(0.0, min(1.0, (float) $matches[1]));
            }
            if ($quality <= 0.0) {
                continue;
            }

            $candidates[] = [
                'locale' => $locale,
                'quality' => $quality,
                'index' => $index,
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            return $right['quality'] <=> $left['quality']
                ?: $left['index'] <=> $right['index'];
        });

        foreach ($candidates as $candidate) {
            $matched = self::matchSupportedLocale($candidate['locale'], $supportedLocales);
            if ($matched !== null) {
                return $matched;
            }
        }

        return null;
    }

    protected static function requestContext(): array
    {
        $request = self::request();
        if (!$request) {
            return ['', ''];
        }

        $app = self::safePathPart((string) ($request->app ?? ''));
        $controllerPath = self::controllerPathFromRoute($request);

        if ($controllerPath === '') {
            [$classApp, $classControllerPath] = self::controllerPathFromClass((string) ($request->controller ?? ''));
            $app = $app ?: $classApp;
            $controllerPath = $classControllerPath;
        }

        return [$app, $controllerPath];
    }

    protected static function controllerPathFromRoute(Request $request): string
    {
        $controller = '';
        if ($request->route && method_exists($request->route, 'param')) {
            $controller = (string) $request->route->param('controller', '');
        }

        return self::normalizeControllerPath($controller);
    }

    protected static function controllerPathFromClass(string $controllerClass): array
    {
        if ($controllerClass === '') {
            return ['', ''];
        }

        $parts = explode('\\', trim($controllerClass, '\\'));
        $controllerIndex = array_search('controller', array_map('strtolower', $parts), true);
        if ($controllerIndex === false) {
            return ['', ''];
        }

        $appIndex = ($parts[0] ?? '') === 'plugin' ? 3 : 1;
        $app = (string) ($parts[$appIndex] ?? '');
        if (strtolower($app) === 'controller') {
            $app = '';
        }

        $controllerParts = array_slice($parts, $controllerIndex + 1);

        return [
            self::safePathPart($app),
            self::normalizeControllerPath(implode('/', $controllerParts)),
        ];
    }

    protected static function normalizeControllerPath(string $controller): string
    {
        $controller = trim(str_replace(['\\', '.'], '/', $controller), '/');
        if ($controller === '') {
            return '';
        }

        $parts = [];
        foreach (explode('/', $controller) as $part) {
            $part = self::safePathPart($part);
            if ($part !== '') {
                $parts[] = lcfirst($part);
            }
        }

        return implode('/', $parts);
    }

    protected static function safePathPart(string $part): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $part) ? $part : '';
    }

    protected static function packageFiles(string $app, string $controllerPath, string $locale, array $config): array
    {
        if ($app === '' || $locale === '') {
            return [];
        }

        $langDir = self::safePathPart((string) ($config['lang_dir'] ?? 'lang')) ?: 'lang';
        $basePath = base_path('app/' . $app . '/' . $langDir);
        $files = [];

        if ($controllerPath !== '') {
            $files[] = $basePath . '/' . $locale . '/' . $controllerPath . '.php';
        }

        $files[] = $basePath . '/' . $locale . '.php';

        return $files;
    }

    protected static function loadPackage(string $file): array
    {
        if (!array_key_exists($file, self::$packages)) {
            if (!is_file($file)) {
                self::$packages[$file] = [];
            } else {
                $package = require $file;
                self::$packages[$file] = is_array($package) ? $package : [];
            }
        }

        return self::$packages[$file];
    }

    protected static function format(string $message, array $vars): string
    {
        if (!$vars) {
            return $message;
        }

        if (array_is_list($vars)) {
            try {
                return vsprintf($message, $vars);
            } catch (Throwable) {
                return $message;
            }
        }

        $replace = [];
        foreach ($vars as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key, '%');
            $replace['%' . $key . '%'] = (string) $value;
        }

        return $replace ? strtr($message, $replace) : $message;
    }

    protected static function request(): ?Request
    {
        $request = request();

        return $request instanceof Request ? $request : null;
    }
}
