<?php

declare(strict_types=1);

namespace app;

use support\Request;
use support\Response;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var Request
     */
    protected Request $request;

    /**
     * 是否批量验证。
     */
    protected bool $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected array $middleware = [];

    /**
     * 默认响应输出类型,支持json/xml/jsonp
     * @var string
     */
    protected string $responseType = 'json';

    /**
     * 构造方法
     * @access public
     * @param Request|null $request 请求对象
     */
    public function __construct(?Request $request = null)
    {
        $this->request                 = $request ?: request();
        $this->request->controllerPath = str_replace('.', '/', $this->request->controller);

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化
     * @access protected
     */
    protected function initialize(): void
    {
    }

    /**
     * 操作成功
     * @param string      $msg     提示消息
     * @param mixed       $data    返回数据
     * @param int         $code    错误码
     * @param string|null $type    输出类型
     * @param array       $header  发送的 header 信息
     * @param array       $options Response 输出参数
     */
    protected function success(string $msg = '', mixed $data = null, int $code = 1, ?string $type = null, array $header = [], array $options = []): Response
    {
        return $this->result($msg, $data, $code, $type, $header, $options);
    }

    /**
     * 操作失败
     * @param string      $msg     提示消息
     * @param mixed       $data    返回数据
     * @param int         $code    错误码
     * @param string|null $type    输出类型
     * @param array       $header  发送的 header 信息
     * @param array       $options Response 输出参数
     */
    protected function error(string $msg = '', mixed $data = null, int $code = 0, ?string $type = null, array $header = [], array $options = []): Response
    {
        return $this->result($msg, $data, $code, $type, $header, $options);
    }

    /**
     * 返回 API 数据
     * @param string      $msg     提示消息
     * @param mixed       $data    返回数据
     * @param int         $code    错误码
     * @param string|null $type    输出类型
     * @param array       $header  发送的 header 信息
     * @param array       $options Response 输出参数
     */
    public function result(string $msg, mixed $data = null, int $code = 0, ?string $type = null, array $header = [], array $options = []): Response
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => time(),
            'data' => $data,
        ];

        $type = $type ?: $this->responseType;
        $statusCode = (int) ($header['statusCode'] ?? 200);
        unset($header['statusCode']);

        $jsonOptions = is_int($options)
            ? $options
            : (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = match ($type) {
            'xml' => xml(is_array($result) ? json_encode($result, $jsonOptions) : (string) $result),
            'jsonp' => jsonp($result, (string) ($options['callback'] ?? $this->request->input('callback', 'callback'))),
            default => json($result, $jsonOptions),
        };

        if ($statusCode !== 200) {
            $response = $response->withStatus($statusCode);
        }

        if ($header) {
            $response = $response->withHeaders($header);
        }

        return $response;
    }
}
