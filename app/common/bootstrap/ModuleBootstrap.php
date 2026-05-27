<?php

namespace app\common\bootstrap;

use Throwable;
use Workerman\Worker;
use Webman\Bootstrap;
use support\Log;
use core\module\Server;

class ModuleBootstrap implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $installed = Server::installedList(root_path() . 'modules' . DIRECTORY_SEPARATOR);
        foreach ($installed as $item) {
            if ((int)($item['state'] ?? 0) !== 1 || empty($item['uid'])) {
                continue;
            }

            $moduleClass = Server::getClass((string)$item['uid']);
            if (!$moduleClass || !method_exists($moduleClass, 'AppInit')) {
                continue;
            }

            try {
                (new $moduleClass())->AppInit();
            } catch (Throwable $e) {
                Log::error('Module AppInit failed: ' . $e->getMessage(), [
                    'uid' => $item['uid'],
                ]);
            }
        }
    }
}
