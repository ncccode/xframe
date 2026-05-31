<?php

namespace core\module;

use Throwable;
use core\utils\Depends;
use core\utils\Exception;
use core\utils\Filesystem;
use ZipArchive;
use support\think\Db;
use FilesystemIterator;
use Webman\Config;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * 模块服务类
 */
class Server
{
    private static string $apiBaseUrl = '/api/store/';

    private static function apiUrl(string $path): string
    {
        $baseUrl = rtrim((string)Config::get('xframe.api_url', ''), '/');
        return $baseUrl . self::$apiBaseUrl . ltrim($path, '/');
    }

    private static function siteDomain(): string
    {
        try {
            return '//' . request()->host();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * 调用模块市场 JSON API。
     *
     * @throws Exception
     */
    private static function requestStore(string $method, string $path, array $data = [], string $token = ''): array
    {
        $method = strtoupper($method);
        $url    = self::apiUrl($path);
        if ($method === 'GET' && $data) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }

        $headers = [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'server: 1',
        ];
        if ($token !== '') {
            $headers[] = 'x-user-token: ' . $token;
        }

        $options = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
        ];
        if ($method !== 'GET') {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options['http']['header'] .= "\r\nContent-Type: application/json";
            $options['http']['content'] = $payload === false ? '{}' : $payload;
        }

        $content = @file_get_contents($url, false, stream_context_create($options));
        $status  = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int)$matches[1];
                break;
            }
        }

        if ($content === false || $status >= 500) {
            throw new Exception('module store request failed', 0, ['url' => $url, 'status' => $status]);
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            throw new Exception('module store response error', 0, ['url' => $url, 'status' => $status]);
        }
        if (($json['code'] ?? 0) != 1) {
            throw new Exception((string)($json['msg'] ?? 'module store request failed'), (int)($json['code'] ?? 0), (array)($json['data'] ?? []));
        }

        return $json;
    }

    private static function downloadStore(string $path, array $query = [], string $token = ''): string
    {
        $url = self::apiUrl($path);
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'Accept: application/zip, application/octet-stream, application/json',
            'X-Requested-With: XMLHttpRequest',
            'server: 1',
        ];
        if ($token !== '') {
            $headers[] = 'x-user-token: ' . $token;
        }
        $content = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout'       => 120,
            ],
        ]));
        if ($content === false || $content === '' || stripos($content, '<title>系统发生错误</title>') !== false) {
            throw new Exception('package download failed', 0, ['url' => $url]);
        }
        if (str_starts_with(ltrim($content), '{')) {
            $json = json_decode($content, true);
            if (is_array($json)) {
                throw new Exception((string)($json['msg'] ?? 'package download failed'), (int)($json['code'] ?? 0), (array)($json['data'] ?? []));
            }
        }

        return $content;
    }

    /**
     * 下载
     * @throws Throwable
     */
    public static function download(string $uid, string $dir, array $extend = []): string
    {
        $tmpFile = $dir . $uid . ".zip";
        $token   = (string)($extend['x-user-token'] ?? '');
        $content = self::downloadStore('download', array_merge(['uid' => $uid, 'server' => 1], $extend), $token);

        if ($write = fopen($tmpFile, 'w')) {
            fwrite($write, $content);
            fclose($write);
            return $tmpFile;
        }
        throw new Exception("No permission to write temporary files");
    }

    /**
     * 安装预检
     * @throws Throwable
     */
    public static function installPreCheck(array $query = []): bool
    {
        $token = (string)($query['x-user-token'] ?? '');
        self::requestStore('GET', 'preCheck', $query, $token);
        return true;
    }

    public static function installRecord(string $uid, string $version, string $status, string $message = '', string $token = ''): void
    {
        $token = $token ?: (string)(request()->header('x-user-token') ?: request()->input('token', ''));
        if ($token === '') {
            return;
        }

        try {
            self::requestStore('POST', 'installRecord', [
                'uid'        => $uid,
                'version'    => $version,
                'status'     => $status,
                'message'    => $message,
                'siteDomain' => self::siteDomain(),
                'sysVersion' => (string)Config::get('xframe.version'),
            ], $token);
        } catch (Throwable) {
            // 安装结果回报不能影响本地模块安装状态。
        }
    }

    public static function getConfig(string $dir, $key = ''): array
    {
        $configFile = $dir . 'config.json';
        if (!is_dir($dir) || !is_file($configFile)) {
            return [];
        }
        $configContent = @file_get_contents($configFile);
        $configContent = json_decode($configContent, true);
        if (!$configContent) {
            return [];
        }
        if ($key) {
            return $configContent[$key] ?? [];
        }
        return $configContent;
    }

    public static function getDepend(string $dir, string $key = ''): array
    {
        if ($key) {
            return self::getConfig($dir, $key);
        }
        $configContent = self::getConfig($dir);
        $dependKey     = ['require', 'require-dev', 'dependencies', 'devDependencies', 'nuxtDependencies', 'nuxtDevDependencies'];
        $dependArray   = [];
        foreach ($dependKey as $item) {
            if (array_key_exists($item, $configContent) && $configContent[$item]) {
                $dependArray[$item] = $configContent[$item];
            }
        }
        return $dependArray;
    }

    /**
     * 检查 XFrame 模块依赖。
     *
     * config.json:
     * {
     *     "xframeModules": {
     *         "user": ">=1.0.0"
     *     }
     * }
     *
     * @throws Throwable
     */
    public static function moduleDependencyCheck(string $dir, string $installDir): void
    {
        $depends = self::getConfig($dir, 'xframeModules');
        if (!$depends) {
            return;
        }

        $installed = [];
        foreach (self::installedList($installDir) as $module) {
            if (!isset($module['uid'])) {
                continue;
            }
            $installed[(string)$module['uid']] = $module;
        }

        foreach ($depends as $uid => $constraint) {
            $uid = (string)$uid;
            $constraint = trim((string)$constraint);
            $module = $installed[$uid] ?? null;
            if (!$module || (int)($module['state'] ?? 0) !== Manage::INSTALLED) {
                throw new Exception('Module dependency missing', 0, [
                    'uid'        => $uid,
                    'constraint' => $constraint,
                ]);
            }

            $version = (string)($module['version'] ?? '');
            if (!self::moduleVersionSatisfies($version, $constraint)) {
                throw new Exception('Module dependency version mismatch', 0, [
                    'uid'              => $uid,
                    'constraint'       => $constraint,
                    'installedVersion' => $version,
                ]);
            }
        }
    }

    private static function moduleVersionSatisfies(string $version, string $constraint): bool
    {
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $constraints = array_filter(array_map('trim', explode(',', $constraint)));
        foreach ($constraints as $item) {
            if (!preg_match('/^(>=|<=|>|<|=)?\s*v?(.+)$/i', $item, $matches)) {
                return false;
            }

            $operator = $matches[1] ?: '=';
            $required = ltrim((string)$matches[2], 'vV');
            $installed = ltrim($version, 'vV');

            $compare = version_compare($installed, $required);
            $passed = match ($operator) {
                '>'  => $compare > 0,
                '>=' => $compare >= 0,
                '<'  => $compare < 0,
                '<=' => $compare <= 0,
                default => $compare === 0,
            };

            if (!$passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * 依赖冲突检查
     * @throws Throwable
     */
    public static function dependConflictCheck(string $dir): array
    {
        $depend     = self::getDepend($dir);
        $serverDep  = new Depends(root_path() . 'composer.json', 'composer');
        $webDep     = new Depends(root_path() . 'web' . DIRECTORY_SEPARATOR . 'package.json');
        $webNuxtDep = new Depends(root_path() . 'web-nuxt' . DIRECTORY_SEPARATOR . 'package.json');
        $sysDepend  = [
            'require'             => $serverDep->getDepends(),
            'require-dev'         => $serverDep->getDepends(true),
            'dependencies'        => $webDep->getDepends(),
            'devDependencies'     => $webDep->getDepends(true),
            'nuxtDependencies'    => $webNuxtDep->getDepends(),
            'nuxtDevDependencies' => $webNuxtDep->getDepends(true),
        ];

        $conflict = [];
        foreach ($depend as $key => $item) {
            $conflict[$key] = array_uintersect_assoc($item, $sysDepend[$key], function ($a, $b) {
                return $a == $b ? -1 : 0;
            });
        }
        return $conflict;
    }

    /**
     * 获取模块[冲突]文件列表
     * @param string $dir          模块目录
     * @param bool   $onlyConflict 是否只获取冲突文件
     */
    public static function getFileList(string $dir, bool $onlyConflict = false): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $fileList       = [];
        $overwriteDir   = self::getOverwriteDir();
        $moduleFileList = self::getRuntime($dir, 'files');

        if ($moduleFileList) {
            // 有冲突的文件
            if ($onlyConflict) {
                // 排除的文件
                $excludeFile = [
                    'info.ini'
                ];
                foreach ($moduleFileList as $file) {
                    // 如果是要安装到项目的文件，从项目根目录开始，如果不是，从模块根目录开始
                    $path          = Filesystem::fsFit(str_replace($dir, '', $file['path']));
                    $paths         = explode(DIRECTORY_SEPARATOR, $path);
                    $overwriteFile = in_array($paths[0], $overwriteDir) ? root_path() . $path : $dir . $path;
                    if (is_file($overwriteFile) && !in_array($path, $excludeFile) && (filesize($overwriteFile) != $file['size'] || md5_file($overwriteFile) != $file['md5'])) {
                        $fileList[] = $path;
                    }
                }
            } else {
                // 要安装的文件
                foreach ($overwriteDir as $item) {
                    $baseDir = $dir . $item;
                    foreach ($moduleFileList as $file) {
                        if (!str_starts_with($file['path'], $baseDir)) continue;
                        $fileList[] = Filesystem::fsFit(str_replace($dir, '', $file['path']));
                    }
                }
            }
            return $fileList;
        }

        foreach ($overwriteDir as $item) {
            $baseDir = $dir . $item;
            if (!is_dir($baseDir)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getPathName();
                    $path     = str_replace($dir, '', $filePath);
                    $path     = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

                    if ($onlyConflict) {
                        $overwriteFile = root_path() . $path;
                        if (is_file($overwriteFile) && (filesize($overwriteFile) != filesize($filePath) || md5_file($overwriteFile) != md5_file($filePath))) {
                            $fileList[] = $path;
                        }
                    } else {
                        $fileList[] = $path;
                    }
                }
            }
        }
        return $fileList;
    }

    public static function getOverwriteDir(): array
    {
        return [
            'app',
            'config',
            'database',
            'extend',
            'modules',
            'public',
            'vendor',
            'web',
            'web-nuxt',
        ];
    }

    public static function importSql(string $dir): bool
    {
        $sqlFile  = $dir . 'install.sql';
        $tempLine = '';
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            foreach ($lines as $line) {
                if (str_starts_with($line, '--') || $line == '' || str_starts_with($line, '/*')) {
                    continue;
                }

                $tempLine .= $line;
                if (str_ends_with(trim($line), ';')) {
                    $connection = (string)Config::get('think-orm.default', 'mysql');
                    $prefix     = (string)Config::get("think-orm.connections.$connection.prefix", '');
                    $tempLine = str_ireplace('__PREFIX__', $prefix, $tempLine);
                    $tempLine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $tempLine);
                    try {
                        Db::execute($tempLine);
                    } catch (Throwable) {
                        // $e->getMessage();
                    }
                    $tempLine = '';
                }
            }
        }
        return true;
    }

    public static function installedList(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $installedDir  = scandir($dir);
        $installedList = [];
        foreach ($installedDir as $item) {
            if ($item === '.' or $item === '..' || is_file($dir . $item)) {
                continue;
            }
            $tempDir = $dir . $item . DIRECTORY_SEPARATOR;
            if (!is_dir($tempDir)) {
                continue;
            }
            $info = self::getIni($tempDir);
            if (!isset($info['uid'])) {
                continue;
            }
            $runtime               = self::getRuntime($tempDir);
            $info['installed']     = true;
            $info['installSource'] = (string)($runtime['installSource'] ?? ($info['installSource'] ?? ''));
            $installedList[] = $info;
        }
        return $installedList;
    }

    public static function getInstalledIds(string $dir): array
    {
        $installedIds = [];
        $installed    = self::installedList($dir);
        foreach ($installed as $item) {
            $installedIds[] = $item['uid'];
        }
        return $installedIds;
    }

    /**
     * 解析出包含 info.ini 的模块根目录（zip 内常见多套一层与模块同名的文件夹）
     */
    public static function resolveModuleDir(string $baseDir): string
    {
        $base = rtrim(Filesystem::fsFit($baseDir), "/\\");
        if ($base === '') {
            return DIRECTORY_SEPARATOR;
        }
        $base .= DIRECTORY_SEPARATOR;
        if (is_file($base . 'info.ini')) {
            return $base;
        }
        $candidates = [];
        foreach (scandir($base) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $base . $item;
            if (is_dir($p) && is_file($p . DIRECTORY_SEPARATOR . 'info.ini')) {
                $candidates[] = $p . DIRECTORY_SEPARATOR;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        return $base;
    }

    /**
     * 将 [section] 下存放的元数据展平到顶层（parse_ini_file(..., true) 时键不在顶层会校验失败）
     */
    private static function normalizeModuleIniData(array $info): array
    {
        if (isset($info['uid']) && !is_array($info['uid'])) {
            return $info;
        }
        foreach ($info as $v) {
            if (is_array($v) && isset($v['uid']) && !is_array($v['uid'])) {
                $flat = $v;
                foreach ($info as $k => $top) {
                    if (!is_array($top)) {
                        $flat[$k] = $top;
                    }
                }
                return $flat;
            }
        }
        return $info;
    }

    /**
     * 获取模块ini
     * @param string $dir 模块目录路径
     */
    public static function getIni(string $dir): array
    {
        $infoFile = $dir . 'info.ini';
        $info     = [];
        if (is_file($infoFile)) {
            $raw = @parse_ini_file($infoFile, true, INI_SCANNER_TYPED);
            if ($raw === false) {
                $raw = @parse_ini_file($infoFile, false, INI_SCANNER_TYPED) ?: [];
            }
            $info = is_array($raw) ? $raw : [];
            if ($info) {
                $info = self::normalizeModuleIniData($info);
            }
        }
        return $info;
    }

    /**
     * 设置模块ini
     * @param string $dir 模块目录路径
     * @param array  $arr 新的ini数据
     * @return bool
     * @throws Throwable
     */
    public static function setIni(string $dir, array $arr): bool
    {
        $infoFile = $dir . 'info.ini';
        $ini      = [];
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $ini[] = "[$key]";
                foreach ($val as $ikey => $ival) {
                    $ini[] = "$ikey = $ival";
                }
            } else {
                $ini[] = "$key = $val";
            }
        }
        if (!file_put_contents($infoFile, implode("\n", $ini) . "\n", LOCK_EX)) {
            throw new Exception("Configuration file has no write permission");
        }
        return true;
    }

    public static function getClass(string $uid, string $type = 'event', ?string $class = null): string
    {
        $name = parse_name($uid);
        if (!is_null($class) && strpos($class, '.')) {
            $class                    = explode('.', $class);
            $class[count($class) - 1] = parse_name(end($class), 1);
            $class                    = implode('\\', $class);
        } else {
            $class = parse_name(is_null($class) ? $name : $class, 1);
        }
        $namespace = match ($type) {
            'controller' => '\\modules\\' . $name . '\\controller\\' . $class,
            default => '\\modules\\' . $name . '\\' . $class,
        };
        return class_exists($namespace) ? $namespace : '';
    }

    public static function execEvent(string $uid, string $event, array $params = []): void
    {
        $eventClass = self::getClass($uid);
        if (class_exists($eventClass)) {
            $handle = new $eventClass();
            if (method_exists($eventClass, $event)) {
                $handle->$event($params);
            }
        }
    }

    /**
     * 分析 WebBootstrap 代码
     */
    public static function analysisWebBootstrap(string $uid, string $dir): array
    {
        $bootstrapFile = $dir . 'webBootstrap.stub';
        if (!file_exists($bootstrapFile)) return [];
        $bootstrapContent = file_get_contents($bootstrapFile);
        $pregArr          = [
            'mainTsImport'     => '/#main.ts import code start#([\s\S]*?)#main.ts import code end#/i',
            'mainTsStart'      => '/#main.ts start code start#([\s\S]*?)#main.ts start code end#/i',
            'appVueImport'     => '/#App.vue import code start#([\s\S]*?)#App.vue import code end#/i',
            'appVueOnMounted'  => '/#App.vue onMounted code start#([\s\S]*?)#App.vue onMounted code end#/i',
            'nuxtAppVueImport' => '/#web-nuxt\/app.vue import code start#([\s\S]*?)#web-nuxt\/app.vue import code end#/i',
            'nuxtAppVueStart'  => '/#web-nuxt\/app.vue start code start#([\s\S]*?)#web-nuxt\/app.vue start code end#/i',
        ];
        $codeStrArr       = [];
        foreach ($pregArr as $key => $item) {
            preg_match($item, $bootstrapContent, $matches);
            if (isset($matches[1]) && $matches[1]) {
                $mainImportCodeArr = array_filter(preg_split('/\r\n|\r|\n/', $matches[1]));
                if ($mainImportCodeArr) {
                    $codeStrArr[$key] = "\n";
                    if (count($mainImportCodeArr) == 1) {
                        foreach ($mainImportCodeArr as $codeItem) {
                            $codeStrArr[$key] .= $codeItem . self::buildMarkStr('module-line-mark', $uid, $key);
                        }
                    } else {
                        $codeStrArr[$key] .= self::buildMarkStr('module-multi-line-mark-start', $uid, $key);
                        foreach ($mainImportCodeArr as $codeItem) {
                            $codeStrArr[$key] .= $codeItem . "\n";
                        }
                        $codeStrArr[$key] .= self::buildMarkStr('module-multi-line-mark-end', $uid, $key);
                    }
                }
            }
            unset($matches);
        }

        return $codeStrArr;
    }

    /**
     * 安装 WebBootstrap
     */
    public static function installWebBootstrap(string $uid, string $dir): void
    {
        $bootstrapCode = self::analysisWebBootstrap($uid, $dir);
        if (!$bootstrapCode) {
            return;
        }

        $webPath     = root_path() . 'web' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $webNuxtPath = root_path() . 'web-nuxt' . DIRECTORY_SEPARATOR;
        $filePaths   = [
            'mainTsImport'     => $webPath . 'main.ts',
            'mainTsStart'      => $webPath . 'main.ts',
            'appVueImport'     => $webPath . 'App.vue',
            'appVueOnMounted'  => $webPath . 'App.vue',
            'nuxtAppVueImport' => $webNuxtPath . 'app.vue',
            'nuxtAppVueStart'  => $webNuxtPath . 'app.vue',
        ];

        $marks = [
            'mainTsImport'     => self::buildMarkStr('import-root-mark'),
            'mainTsStart'      => self::buildMarkStr('start-root-mark'),
            'appVueImport'     => self::buildMarkStr('import-root-mark'),
            'appVueOnMounted'  => self::buildMarkStr('onMounted-root-mark'),
            'nuxtAppVueImport' => self::buildMarkStr('import-root-mark'),
            'nuxtAppVueStart'  => self::buildMarkStr('start-root-mark'),
        ];

        foreach ($bootstrapCode as $key => $item) {
            if ($item && isset($marks[$key])) {
                $content = file_get_contents($filePaths[$key]);
                $markPos = stripos($content, $marks[$key]);
                if ($markPos && strripos($content, self::buildMarkStr('module-line-mark', $uid, $key)) === false && strripos($content, self::buildMarkStr('module-multi-line-mark-start', $uid, $key)) === false) {
                    $content = substr_replace($content, $item, $markPos + strlen($marks[$key]), 0);
                    file_put_contents($filePaths[$key], $content);
                }
            }
        }
    }

    /**
     * 卸载 WebBootstrap
     */
    public static function uninstallWebBootstrap(string $uid): void
    {
        $webPath     = root_path() . 'web' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $webNuxtPath = root_path() . 'web-nuxt' . DIRECTORY_SEPARATOR;
        $filePaths   = [
            'mainTsImport'     => $webPath . 'main.ts',
            'mainTsStart'      => $webPath . 'main.ts',
            'appVueImport'     => $webPath . 'App.vue',
            'appVueOnMounted'  => $webPath . 'App.vue',
            'nuxtAppVueImport' => $webNuxtPath . 'app.vue',
            'nuxtAppVueStart'  => $webNuxtPath . 'app.vue',
        ];

        $marksKey = [
            'mainTsImport',
            'mainTsStart',
            'appVueImport',
            'appVueOnMounted',
            'nuxtAppVueImport',
            'nuxtAppVueStart',
        ];

        foreach ($marksKey as $item) {
            if (!is_file($filePaths[$item])) {
                continue;
            }
            $content                  = file_get_contents($filePaths[$item]);
            $moduleLineMark           = self::buildMarkStr('module-line-mark', $uid, $item);
            $moduleMultiLineMarkStart = self::buildMarkStr('module-multi-line-mark-start', $uid, $item);
            $moduleMultiLineMarkEnd   = self::buildMarkStr('module-multi-line-mark-end', $uid, $item);

            // 寻找标记，找到则将其中内容删除
            $moduleLineMarkPos = strripos($content, $moduleLineMark);
            if ($moduleLineMarkPos !== false) {
                $delStartTemp = explode($moduleLineMark, $content);
                $delStartPos  = strripos(rtrim($delStartTemp[0], "\n"), "\n");
                $delEndPos    = stripos($content, "\n", $moduleLineMarkPos);
                $content      = substr_replace($content, '', $delStartPos, $delEndPos - $delStartPos);
            }

            $moduleMultiLineMarkStartPos = stripos($content, $moduleMultiLineMarkStart);
            if ($moduleMultiLineMarkStartPos !== false) {
                $moduleMultiLineMarkStartPos--;
                $moduleMultiLineMarkEndPos = stripos($content, $moduleMultiLineMarkEnd);
                $delLang                   = ($moduleMultiLineMarkEndPos + strlen($moduleMultiLineMarkEnd)) - $moduleMultiLineMarkStartPos;
                $content                   = substr_replace($content, '', $moduleMultiLineMarkStartPos, $delLang);
            }

            if ($moduleLineMarkPos || $moduleMultiLineMarkStartPos) {
                file_put_contents($filePaths[$item], $content);
            }
        }
    }

    /**
     * 构建 WebBootstrap 需要的各种标记字符串
     * @param string $type
     * @param string $uid    模块UID
     * @param string $extend 扩展数据
     * @return string
     */
    public static function buildMarkStr(string $type, string $uid = '', string $extend = ''): string
    {
        $nonTabKeys = ['mti', 'avi', 'navi', 'navs'];
        $extend     = match ($extend) {
            'mainTsImport' => 'mti',
            'mainTsStart' => 'mts',
            'appVueImport' => 'avi',
            'appVueOnMounted' => 'avo',
            'nuxtAppVueImport' => 'navi',
            'nuxtAppVueStart' => 'navs',
            default => '',
        };
        return match ($type) {
            'import-root-mark' => '// modules import mark, Please do not remove.',
            'start-root-mark' => '// modules start mark, Please do not remove.',
            'onMounted-root-mark' => '// Modules onMounted mark, Please do not remove.',
            'module-line-mark' => ' // Code from module \'' . $uid . "'" . ($extend ? "($extend)" : ''),
            'module-multi-line-mark-start' => (in_array($extend, $nonTabKeys) ? '' : self::tab()) . "// Code from module '$uid' start" . ($extend ? "($extend)" : '') . "\n",
            'module-multi-line-mark-end' => (in_array($extend, $nonTabKeys) ? '' : self::tab()) . "// Code from module '$uid' end",
            default => '',
        };
    }

    protected static function tab(int $num = 1): string
    {
        return str_pad('', 4 * $num);
    }

    public static function getNuxtVersion()
    {
        $nuxtPackageJsonPath = Filesystem::fsFit(root_path() . 'web-nuxt/package.json');
        if (is_file($nuxtPackageJsonPath)) {
            $nuxtPackageJson = file_get_contents($nuxtPackageJsonPath);
            $nuxtPackageJson = json_decode($nuxtPackageJson, true);
            if ($nuxtPackageJson && isset($nuxtPackageJson['version'])) {
                return $nuxtPackageJson['version'];
            }
        }
        return false;
    }

    /**
     * 创建 .runtime
     */
    public static function createRuntime(string $dir, array $extend = []): void
    {
        $runtimeFilePath = $dir . '.runtime';
        $files           = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY
        );
        $filePaths       = [];
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $pathName = $file->getPathName();
                if ($pathName == $runtimeFilePath) continue;
                $filePaths[] = [
                    'path' => Filesystem::fsFit($pathName),
                    'size' => filesize($pathName),
                    'md5'  => md5_file($pathName),
                ];
            }
        }

        file_put_contents($runtimeFilePath, json_encode(array_merge([
            'files' => $filePaths,
            'pure'  => Config::get('xframe.module_pure_install'),
        ], $extend)));
    }

    /**
     * 读取 .runtime
     */
    public static function getRuntime(string $dir, string $key = ''): mixed
    {
        $runtimeFilePath   = $dir . '.runtime';
        $runtimeContent    = @file_get_contents($runtimeFilePath);
        $runtimeContentArr = json_decode($runtimeContent, true);
        if (!$runtimeContentArr) return [];

        if ($key) {
            return $runtimeContentArr[$key] ?? [];
        } else {
            return $runtimeContentArr;
        }
    }

    /**
     * @throws Throwable
     */
    public static function getModulePackageComment(string $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new Exception('Unable to open the zip file');
        }

        try {
            return (string)$zip->getArchiveComment();
        } finally {
            $zip->close();
        }
    }
}
