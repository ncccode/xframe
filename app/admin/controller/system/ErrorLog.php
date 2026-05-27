<?php

namespace app\admin\controller\system;

use support\Response;
use app\common\controller\Admin;

/**
 * 错误日志
 */
class ErrorLog extends Admin
{
    protected string $path = '';

    public function initialize(): void
    {
        parent::initialize();
        $this->path = base_path('errlog') . DIRECTORY_SEPARATOR;
        $this->ensureLogDir();
    }

    /**
     * 错误日志
     */
    public function index(): Response
    {
        $list = $this->getLogFiles();
        $total = count($list);
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, (int)$this->request->get('limit', 10));
        $list = array_slice($list, ($page - 1) * $limit, $limit);

        return $this->success('', [
            'list' => $list,
            'total' => $total,
            'remark' => \get_route_remark(),
        ]);
    }

    /**
     * 下载
     */
    public function download(): Response
    {
        $filename = $this->request->input('filename', $this->request->input('file', ''));
        $file = $this->resolveLogFile((string)$filename);
        if (!$file) {
            return $this->error(__('Illegal filename'));
        }

        return response()->download($file, basename($file));
    }

    /**
     * 查看
     */
    public function item(): Response
    {
        $file = $this->resolveLogFile((string)$this->request->input('filename', ''));
        if (!$file) {
            return $this->error(__('Log file not found'));
        }

        return $this->success('', [
            'content' => (string)file_get_contents($file),
        ]);
    }

    /**
     * 全部删除
     */
    public function delall(): Response
    {
        $count = 0;
        foreach ($this->getLogFileIterator() as $file) {
            if ($file->isFile() && $file->getFilename() !== '.gitignore' && @unlink($file->getPathname())) {
                $count++;
            }
        }

        return $this->success(__('Deleted successfully'), [
            'count' => $count,
        ]);
    }

    /**
     * 单个或批量删除
     */
    public function del(): Response
    {
        $filenames = $this->request->input('ids', []);
        if (!$filenames) {
            $filenames = $this->request->input('filename', '');
        }
        if (is_string($filenames)) {
            $filenames = array_filter(explode(',', $filenames));
        }
        if (!is_array($filenames) || !$filenames) {
            return $this->error(__('No rows were deleted'));
        }

        $count = 0;
        foreach ($filenames as $filename) {
            $file = $this->resolveLogFile((string)$filename);
            if (!$file) {
                return $this->error(__('Illegal filename'));
            }
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count > 0
            ? $this->success(__('Deleted successfully'), ['count' => $count])
            : $this->error(__('No rows were deleted'));
    }

    protected function ensureLogDir(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    protected function getLogFiles(): array
    {
        $list = [];
        $quickSearch = trim((string)$this->request->get('quickSearch', ''));
        foreach ($this->getLogFileIterator() as $file) {
            if (!$file->isFile() || $file->getFilename() === '.gitignore') {
                continue;
            }
            $name = $this->relativeFilename($file->getPathname());
            if ($quickSearch !== '' && stripos($name, $quickSearch) === false) {
                continue;
            }
            $list[] = [
                'name' => $name,
                'size' => $this->formatBytes($file->getSize()),
                'time' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        $sort = array_column($list, 'time');
        array_multisort($sort, SORT_DESC, $list);
        return $list;
    }

    protected function getLogFileIterator(): \Iterator
    {
        $this->ensureLogDir();
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS)
        );
    }

    protected function resolveLogFile(string $filename): ?string
    {
        $filename = $this->normalizeFilename($filename);
        if ($filename === '') {
            return null;
        }

        $root = realpath($this->path);
        $file = realpath($this->path . $filename);
        if (!$root || !$file || !is_file($file)) {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);
        if (!str_starts_with($file, $root)) {
            return null;
        }

        return $file;
    }

    protected function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);
        if (
            $filename === ''
            || $filename === '.gitignore'
            || str_contains($filename, "\0")
            || str_contains($filename, '\\')
            || str_starts_with($filename, '/')
            || preg_match('/^[A-Za-z]:\//', $filename)
            || preg_match('#(^|/)\.\.(?:/|$)#', $filename)
        ) {
            return '';
        }

        return $filename;
    }

    protected function relativeFilename(string $path): string
    {
        $relative = substr($path, strlen($this->path));
        return str_replace('\\', '/', $relative);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float)$bytes;
        $index = 0;
        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return ($index === 0 ? (string)$bytes : rtrim(rtrim(number_format($size, 2), '0'), '.')) . ' ' . $units[$index];
    }
}
