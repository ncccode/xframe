<?php

namespace core\service\upload\driver;

use core\utils\Filesystem;
use Webman\Config;
use Webman\Http\UploadFile;
use Webman\Exception\FileException;
use core\service\upload\Driver;

/**
 * 上传到本地磁盘的驱动
 * @see Driver
 */
class Local extends Driver
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = [
            'root' => public_path(),
            'url'  => '',
        ];
        $disk = Config::get('filesystem.disks.public');
        if (is_array($disk)) {
            $this->options = array_merge($this->options, $disk);
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * 保存文件
     * @param UploadFile $file
     * @param string       $saveName
     * @return bool
     */
    public function save(UploadFile $file, string $saveName): bool
    {
        $saveName = $this->getWritableFullPath($saveName);
        $file->move($saveName);
        @chmod($saveName, 0666 & ~umask());
        return true;
    }

    /**
     * 删除文件
     * @param string $saveName
     * @return bool
     */
    public function delete(string $saveName): bool
    {
        $saveFullName = $this->getExistingFullPath($saveName);
        if ($saveFullName && is_file($saveFullName)) {
            @unlink($saveFullName);
            Filesystem::delEmptyDir(dirname($saveFullName));
        }
        return true;
    }

    /**
     * 获取资源 URL 地址
     * @param string      $saveName 资源保存名称
     * @param string|bool $domain   是否携带域名 或者直接传入域名
     * @param string      $default  默认值
     * @return string
     */
    public function url(string $saveName, string|bool $domain = true, string $default = ''): string
    {
        $saveName = $this->clearRootPath($saveName);

        if ($domain === true) {
            $domain = '//' . request()->host();
        } elseif ($domain === false) {
            $domain = '';
        }

        $saveName = $saveName ?: $default;
        if (!$saveName) return $domain;

        $regex = "/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/i";
        if (preg_match('/^http(s)?:\/\//', $saveName) || preg_match($regex, $saveName) || $domain === false) {
            return $saveName;
        }
        return str_replace('\\', '/', $domain . $saveName);
    }

    /**
     * 文件是否存在
     * @param string $saveName
     * @return bool
     */
    public function exists(string $saveName): bool
    {
        $saveFullName = $this->getExistingFullPath($saveName);
        return $saveFullName !== '' && file_exists($saveFullName);
    }

    /**
     * 获取文件的完整存储路径
     * @param string $saveName
     * @param bool   $baseName 是否包含文件名
     * @return string
     */
    public function getFullPath(string $saveName, bool $baseName = false): string
    {
        $savePathInfo = pathinfo($saveName);
        $root         = $this->getRootPath();
        $dirName      = $savePathInfo['dirname'] . '/';

        // 以 root 路径开始时单独返回，避免重复调用此方法时造成 $dirName 的错误拼接
        if (str_starts_with($saveName, $root)) {
            return Filesystem::fsFit($baseName || !isset($savePathInfo['extension']) ? $saveName : $dirName);
        }

        return Filesystem::fsFit($root . $dirName . ($baseName ? $savePathInfo['basename'] : ''));
    }

    public function clearRootPath(string $saveName): string
    {
        return str_replace($this->getRootPath(), '', Filesystem::fsFit($saveName));
    }

    public function getRootPath(): string
    {
        return rtrim(Filesystem::fsFit(str_replace($this->options['url'], '', $this->options['root'])), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    protected function getExistingFullPath(string $saveName): string
    {
        $path = realpath($this->getFullPath($saveName, true));
        if (!$path) {
            return '';
        }

        $root = realpath($this->getRootPath());
        if (!$root) {
            return '';
        }

        $path = rtrim(Filesystem::fsFit($path), DIRECTORY_SEPARATOR);
        $root = rtrim(Filesystem::fsFit($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path . DIRECTORY_SEPARATOR, $root)) {
            return '';
        }

        return $path;
    }

    protected function getWritableFullPath(string $saveName): string
    {
        if (
            str_contains($saveName, "\0")
            || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $saveName)
            || preg_match('/^[A-Za-z]:[\\\\/]/', $saveName)
        ) {
            throw new FileException('Upload path is not allowed');
        }

        $fullPath = $this->getFullPath($saveName, true);
        $root = realpath($this->getRootPath());
        $dirname = dirname($fullPath);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }
        $dir = realpath($dirname);
        if (!$root || !$dir) {
            throw new FileException('Upload directory does not exist');
        }

        $root = rtrim(Filesystem::fsFit($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $dir = rtrim(Filesystem::fsFit($dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($dir, $root)) {
            throw new FileException('Upload path is not allowed');
        }

        return $fullPath;
    }
}
