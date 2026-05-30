<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;
use support\exception\PageNotFoundException;
use support\think\Db;
use Webman\App;
use Webman\Config;
use Webman\Route;

$getBackendApps = static function (): array {
    $apps = Config::get('xframe.backend_apps', []);
    if (!is_array($apps)) {
        return [];
    }

    $reservedPaths = ['admin', 'api', 'app', 'assets', 'static'];
    $normalized = [];
    foreach ($apps as $name => $appConfig) {
        $name = trim((string) $name);
        if ($name === '' || !is_array($appConfig)) {
            continue;
        }

        $path = '/' . trim((string) ($appConfig['path'] ?? $name), '/');
        if (
            !preg_match('#^/[A-Za-z0-9][A-Za-z0-9_-]*$#', $path)
            || in_array(strtolower(trim($path, '/')), $reservedPaths, true)
        ) {
            continue;
        }

        $app = trim((string) ($appConfig['app'] ?? $name), '\\/');
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $app)) {
            continue;
        }

        $normalized[$name] = [
            'name' => $name,
            'path' => $path,
            'app'  => $app,
        ];
    }

    return $normalized;
};

$normalizeAdminEntrance = static function (string $entrance) use ($getBackendApps): string {
    $entrance = trim($entrance ?: '/admin', '/');
    $reserved = ['api', 'app', 'assets', 'static'];
    foreach ($getBackendApps() as $backendApp) {
        $reserved[] = strtolower(trim((string) $backendApp['path'], '/'));
    }
    if (
        !preg_match('/^[A-Za-z0-9]+$/', $entrance)
        || in_array(strtolower($entrance), $reserved, true)
    ) {
        return 'admin';
    }
    return $entrance;
};

$getAdminEntrance = static function () use ($normalizeAdminEntrance): string {
    try {
        $entrance = (string) Db::name('config')->where('name', 'admin_entrance')->value('value');
    } catch (Throwable) {
        $entrance = '/admin';
    }
    return $normalizeAdminEntrance($entrance);
};

$isAdminApiRequest = static function (Request $request): bool {
    return (bool) ($request->input('server') || $request->header('server'));
};

$dispatchDefaultController = static function (Request $request, ?string $path = null) {
    static $parseControllerAction = null;

    $parseControllerAction ??= new \ReflectionMethod(App::class, 'parseControllerAction');

    $controllerAndAction = $parseControllerAction->invoke(null, $path ?? $request->path());
    if (!$controllerAndAction) {
        throw new PageNotFoundException();
    }

    $request->plugin = $controllerAndAction['plugin'] ?? '';
    $request->app = $controllerAndAction['app'] ?? '';
    $request->controller = $controllerAndAction['controller'] ?? '';
    $request->action = $controllerAndAction['action'] ?? '';
    $request->route = null;

    $callback = App::getCallback(
        $request->plugin,
        $request->app,
        [$request->controller, $request->action],
        [],
        false
    );

    return $callback($request);
};

$handleAdminEntrance = static function (Request $request, string $path = '') use ($dispatchDefaultController, $isAdminApiRequest) {
    if ($isAdminApiRequest($request)) {
        $path = trim($path, '/');
        return $dispatchDefaultController($request, '/admin' . ($path === '' ? '' : '/' . $path));
    }

    return response()->file(base_path('public/index.html'));
};

$handleBackendEntrance = static function (Request $request, array $backendApp, string $path = '') use ($dispatchDefaultController, $isAdminApiRequest) {
    if ($isAdminApiRequest($request)) {
        $path = trim($path, '/');
        $app = trim((string)($backendApp['app'] ?? $backendApp['name'] ?? ''), '/');
        return $dispatchDefaultController($request, '/' . $app . ($path === '' ? '' : '/' . $path));
    }

    return response()->file(base_path('public/index.html'));
};

foreach ($getBackendApps() as $backendApp) {
    Route::any($backendApp['path'] . '[/{path:.+}]', function (Request $request, string $path = '') use ($handleBackendEntrance, $backendApp) {
        return $handleBackendEntrance($request, $backendApp, $path);
    });
}

Route::any('/admin[/{path:.+}]', function (Request $request, string $path = '') use ($getAdminEntrance, $handleAdminEntrance) {
    if ($getAdminEntrance() !== 'admin') {
        throw new PageNotFoundException();
    }

    return $handleAdminEntrance($request, $path);
});

Route::any('/{adminEntrance:[A-Za-z0-9]+}[/{path:.+}]', function (
    Request $request,
    string $adminEntrance,
    string $path = ''
) use ($dispatchDefaultController, $getAdminEntrance, $handleAdminEntrance) {
    if ($adminEntrance !== $getAdminEntrance()) {
        return $dispatchDefaultController($request);
    }

    return $handleAdminEntrance($request, $path);
});
