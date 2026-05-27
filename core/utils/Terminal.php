<?php
// +----------------------------------------------------------------------
// | xframe
// +----------------------------------------------------------------------
// | Copyright (c) https://xframe.vip
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// +----------------------------------------------------------------------

namespace core\utils;

use Throwable;
use support\Response;
use Webman\Config;
use app\common\library\Auth;
use core\module\Manage;
use core\service\token\TokenExpirationException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Protocols\Http\ServerSentEvents;

class Terminal
{
    /**
     * @var ?Terminal 对象实例
     */
    protected static ?Terminal $instance = null;

    /**
     * @var string 当剝执行的命令 $command 的 key
     */
    protected string $commandKey = '';

    /**
     * @var array proc_open 的坂数
     */
    protected array $descriptorsPec = [];

    /**
     * @var resource|bool proc_open 返回的 resource
     */
    protected $process = false;

    /**
     * @var array proc_open 的管靓
     */
    protected array $pipes = [];

    /**
     * @var int proc执行状思:0=未执行,1=执行中,2=执行完毕
     */
    protected int $procStatusMark = 0;

    /**
     * @var array proc执行状思数杮
     */
    protected array $procStatusData = [];

    /**
     * @var string 命令在剝坰的uuid
     */
    protected string $uuid = '';

    /**
     * @var string 扩展信杯
     */
    protected string $extend = '';

    /**
     * @var string 命令执行输出文件
     */
    protected string $outputFile = '';

    /**
     * @var string 命令执行实时输出内容
     */
    protected string $outputContent = '';

    /**
     * @var bool 是否已直接向 Workerman 连接发送 SSE 响应头
     */
    protected bool $eventStreamStarted = false;

    /**
     * @var string 自动构建的剝端文件的 outDir（相对于根目录）
     */
    protected static string $distDir = 'web' . DIRECTORY_SEPARATOR . 'dist';

    /**
     * @var array 状思标识
     */
    protected array $flag = [
        // 连接戝功
        'link-success'   => 'command-link-success',
        // 执行戝功
        'exec-success'   => 'command-exec-success',
        // 执行完戝
        'exec-completed' => 'command-exec-completed',
        // 执行出错
        'exec-error'     => 'command-exec-error',
    ];

    /**
     * 初始化
     */
    public static function instance(): Terminal
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->uuid   = request()->param('uuid', '');
        $this->extend = request()->param('extend', '');

        // 初始化日志文件
        $outputDir        = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'terminal';
        $this->outputFile = $outputDir . DIRECTORY_SEPARATOR . 'exec.log';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        file_put_contents($this->outputFile, '');

        /**
         * 命令执行结果输出到文件而丝是管靓
         * 因为输出到管靓时有延迟，而文件虽然需覝频繝读坖和对比内容，但是输出实时的
         */
        $this->descriptorsPec = [0 => ['pipe', 'r'], 1 => ['file', $this->outputFile, 'w'], 2 => ['file', $this->outputFile, 'w']];
    }

    /**
     * 获坖命令
     * @param string $key 命令key
     * @return array|bool
     */
    public static function getCommand(string $key): bool|array
    {
        if (!$key) {
            return false;
        }

        $commands = Config::get('terminal.commands');
        if (stripos($key, '.')) {
            $key = explode('.', $key);
            if (!array_key_exists($key[0], $commands) || !is_array($commands[$key[0]]) || !array_key_exists($key[1], $commands[$key[0]])) {
                return false;
            }
            $command = $commands[$key[0]][$key[1]];
        } else {
            if (!array_key_exists($key, $commands)) {
                return false;
            }
            $command = $commands[$key];
        }
        if (!is_array($command)) {
            $command = [
                'cwd'     => root_path(),
                'command' => $command,
            ];
        } else {
            $command['cwd'] = root_path() . $command['cwd'];
        }

        if (str_contains($command['command'], '%')) {
            $args = request()->param('extend', '');
            $args = explode('~~', $args);
            $args = array_map('escapeshellarg', $args);

            array_unshift($args, $command['command']);
            $command['command'] = call_user_func_array('sprintf', $args);
        }

        $command['cwd'] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $command['cwd']);
        return $command;
    }

    /**
     * 执行命令
     * @param bool $authentication 是坦鉴权
     * @throws Throwable
     */
    public function exec(bool $authentication = true): Response
    {
        $this->sendHeader();

        if (!$this->eventStreamStarted) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            if (!ob_get_level()) ob_start();
        }

        $this->commandKey = request()->param('command');

        $command = self::getCommand($this->commandKey);
        if (!$command) {
            $this->execError('The command was not allowed to be executed', true);
            return $this->eventStreamResponse();
        }

        if ($authentication) {
            try {
                $token = get_auth_token();
                $auth  = Auth::instance();
                $auth->init($token);

                if (!$auth->isLogin() || !$auth->isSuperAdmin()) {
                    $this->execError("You are not super administrator or not logged in", true);
                    return $this->eventStreamResponse();
                }
            } catch (TokenExpirationException) {
                $this->execError(__('Token expiration'));
                return $this->eventStreamResponse();
            }
        }

        $this->beforeExecution();
        $this->outputFlag('link-success');

        if (!empty($command['notes'])) {
            $this->output('> ' . __($command['notes']), false);
        }
        $this->output('> ' . $command['command'], false);

        $this->process = proc_open($command['command'], $this->descriptorsPec, $this->pipes, $command['cwd']);
        if (!is_resource($this->process)) {
            $this->execError('Failed to execute', true);
            return $this->eventStreamResponse();
        }
        while ($this->getProcStatus()) {
            $contents = file_get_contents($this->outputFile);
            if (strlen($contents) && $this->outputContent != $contents) {
                $newOutput = str_replace($this->outputContent, '', $contents);
                $this->checkOutput($contents, $newOutput);
                if (preg_match('/\r\n|\r|\n/', $newOutput)) {
                    $this->output($newOutput);
                    $this->outputContent = $contents;
                }
            }

            // 输出执行状思信杯
            if ($this->procStatusMark === 2) {
                $this->output('exitCode: ' . $this->procStatusData['exitcode']);
                if ($this->procStatusData['exitcode'] === 0) {
                    if ($this->successCallback()) {
                        $this->outputFlag('exec-success');
                    } else {
                        $this->output('Error: Command execution succeeded, but callback execution failed');
                        $this->outputFlag('exec-error');
                    }
                } else {
                    $this->outputFlag('exec-error');
                }
            }

            usleep(500000);
        }
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->outputFlag('exec-completed');
        return $this->eventStreamResponse();
    }

    /**
     * 获坖执行状思
     * @throws Throwable
     */
    public function getProcStatus(): bool
    {
        $this->procStatusData = proc_get_status($this->process);
        if ($this->procStatusData['running']) {
            $this->procStatusMark = 1;
            return true;
        } elseif ($this->procStatusMark === 1) {
            $this->procStatusMark = 2;
            return true;
        } else {
            return false;
        }
    }

    /**
     * 输出 EventSource 数杮
     * @param string $data
     * @param bool   $callback
     */
    public function output(string $data, bool $callback = true): void
    {
        $data = self::outputFilter($data);
        $data = [
            'data'   => $data,
            'uuid'   => $this->uuid,
            'extend' => $this->extend,
            'key'    => $this->commandKey,
        ];
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($data) {
            $this->finalOutput($data);
            if ($callback) $this->outputCallback($data);
            @ob_flush();// 刷新浝览器缓冲区
        }
    }


    /**
     * 检查输出
     * @param string $outputs   全部输出内容
     * @param string $rowOutput 当剝输出内容（行）
     */
    public function checkOutput(string $outputs, string $rowOutput): void
    {
        if (str_contains($rowOutput, '(Y/n)')) {
            $this->execError('Interactive output detected, please manually execute the command to confirm the situation.', true);
        }
    }

    /**
     * 输出状思标记
     * @param string $flag
     */
    public function outputFlag(string $flag): void
    {
        $this->output($this->flag[$flag], false);
    }

    /**
     * 输出坎回调
     */
    public function outputCallback($data): void
    {

    }

    /**
     * 戝功坎回调
     * @return bool
     * @throws Throwable
     */
    public function successCallback(): bool
    {
        if (stripos($this->commandKey, '.')) {
            $commandKeyArr = explode('.', $this->commandKey);
            $commandPKey   = $commandKeyArr[0] ?? '';
        } else {
            $commandPKey = $this->commandKey;
        }

        if ($commandPKey == 'web-build') {
            if (!self::mvDist() && !self::publishedDistExists()) {
                $this->output('Build succeeded, but move file failed. Please operate manually.');
                return false;
            }
        } elseif ($commandPKey == 'web-install' && $this->extend) {
            [$type, $value] = explode(':', $this->extend);
            if ($type == 'module-install' && $value) {
                Manage::instance($value)->dependentInstallComplete('npm');
            }
        } elseif ($commandPKey == 'composer' && $this->extend) {
            [$type, $value] = explode(':', $this->extend);
            if ($type == 'module-install' && $value) {
                Manage::instance($value)->dependentInstallComplete('composer');
            }
        } elseif ($commandPKey == 'nuxt-install' && $this->extend) {
            [$type, $value] = explode(':', $this->extend);
            if ($type == 'module-install' && $value) {
                Manage::instance($value)->dependentInstallComplete('nuxt_npm');
            }
        }
        return true;
    }

    /**
     * 执行剝埋点
     */
    public function beforeExecution(): void
    {
        if ($this->commandKey == 'test.pnpm') {
            @unlink(root_path() . 'public' . DIRECTORY_SEPARATOR . 'npm-install-test' . DIRECTORY_SEPARATOR . 'pnpm-lock.yaml');
        } elseif ($this->commandKey == 'web-install.pnpm') {
            @unlink(root_path() . 'web' . DIRECTORY_SEPARATOR . 'pnpm-lock.yaml');
        }
    }

    /**
     * 输出过滤
     */
    public static function outputFilter($str): string
    {
        $str  = trim($str);
        $preg = '/\[(.*?)m/i';
        $str  = preg_replace($preg, '', $str);
        $str  = str_replace(["\r\n", "\r", "\n"], "\n", $str);
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
    }

    /**
     * 执行错误
     */
    public function execError($error, $break = false): void
    {
        $this->output('Error:' . $error);
        $this->outputFlag('exec-error');
        if ($break) $this->break();
    }

    /**
     * 退出执行
     */
    public function break(): void
    {
        // webman 控制器通过返回 SSE Response 结束执行。
    }

    /**
     * 执行一个命令并以字符串的方弝返回执行输出
     * 代替 exec 使用，这样就坪需覝解除 proc_open 的函数禝用了
     * @param $commandKey
     * @return string|bool
     */
    public static function getOutputFromProc($commandKey): bool|string
    {
        if (!function_exists('proc_open') || !function_exists('proc_close')) {
            return false;
        }
        $command = self::getCommand($commandKey);
        if (!$command) {
            return false;
        }
        $descriptorsPec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process        = proc_open($command['command'], $descriptorsPec, $pipes, null, null);
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            $info .= stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return self::outputFilter($info);
        }
        return '';
    }

    public static function publishedDistExists(): bool
    {
        return is_file(root_path() . 'public' . DIRECTORY_SEPARATOR . 'index.html')
            && is_dir(root_path() . 'public' . DIRECTORY_SEPARATOR . 'assets');
    }

    public static function mvDist(): bool
    {
        $distPath      = root_path() . self::$distDir . DIRECTORY_SEPARATOR;
        $indexHtmlPath = $distPath . 'index.html';
        $assetsPath    = $distPath . 'assets';
        if (!file_exists($indexHtmlPath) || !file_exists($assetsPath)) {
            return false;
        }

        $toIndexHtmlPath = root_path() . 'public' . DIRECTORY_SEPARATOR . 'index.html';
        $toAssetsPath    = root_path() . 'public' . DIRECTORY_SEPARATOR . 'assets';
        @unlink($toIndexHtmlPath);
        Filesystem::delDir($toAssetsPath);

        if (rename($indexHtmlPath, $toIndexHtmlPath) && rename($assetsPath, $toAssetsPath)) {
            Filesystem::delDir($distPath);
            return true;
        } else {
            return false;
        }
    }

    public static function changeTerminalConfig($config = []): bool
    {
        // 丝保存在数杮库中，因为切杢包管睆器时，数杮库资料坯能还未酝置
        $oldPackageManager = Config::get('terminal.npm_package_manager');
        $newPackageManager = request()->post('manager', $config['manager'] ?? $oldPackageManager);

        if ($oldPackageManager == $newPackageManager) {
            return true;
        }

        $buildConfigFile    = config_path() . 'terminal.php';
        $buildConfigContent = @file_get_contents($buildConfigFile);
        $buildConfigContent = preg_replace("/'npm_package_manager'(\s+)=>(\s+)'$oldPackageManager'/", "'npm_package_manager'\$1=>\$2'$newPackageManager'", $buildConfigContent);
        $result             = @file_put_contents($buildConfigFile, $buildConfigContent);
        return (bool)$result;
    }

    protected function eventStreamResponse(): Response
    {
        if ($this->eventStreamStarted && $connection = $this->connection()) {
            $connection->close();
        }

        return response('', 200, $this->eventStreamHeaders());
    }

    /**
     * 最终输出
     */
    public function finalOutput(string $data): void
    {
        if ($this->eventStreamStarted && $connection = $this->connection()) {
            $connection->send(new ServerSentEvents(['event' => 'message', 'data' => $data]));
        } else {
            echo 'data: ' . $data . "\n\n";
        }
    }

    /**
     * 坑逝哝应头
     */
    public function sendHeader(): void
    {
        $headers = $this->eventStreamHeaders();

        if ($connection = $this->connection()) {
            $connection->send(new WorkermanResponse(200, $headers));
            $this->eventStreamStarted = true;
        } else {
            foreach ($headers as $name => $val) {
                header($name . (!is_null($val) ? ':' . $val : ''));
            }
        }
    }

    protected function eventStreamHeaders(): array
    {
        return array_merge($this->corsHeaders(), [
            'X-Accel-Buffering' => 'no',
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
        ]);
    }

    protected function corsHeaders(): array
    {
        $origin       = (string)request()->header('origin', '*');
        $allowDomains = array_filter(array_map('trim', explode(',', (string)Config::get('xframe.cors_request_domain', ''))));
        $originHost   = parse_url($origin, PHP_URL_HOST) ?: $origin;

        return [
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, Ba-Token, Batoken, lang, server, xtoken, x-user-token',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Expose-Headers'    => 'xtoken',
            'Access-Control-Allow-Origin'      => (!$allowDomains || in_array($originHost, $allowDomains, true)) ? $origin : '*',
        ];
    }

    protected function connection(): ?TcpConnection
    {
        $connection = request()->connection ?? null;
        return $connection instanceof TcpConnection ? $connection : null;
    }
}
