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

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    public function get(?string $name = null, mixed $default = null): mixed
    {
        return $this->filterValue(parent::get($this->filterName($name), $default), $name);
    }

    public function post(?string $name = null, mixed $default = null): mixed
    {
        return $this->filterValue(parent::post($this->filterName($name), $default), $name);
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $this->filterValue(parent::input($this->filterName($name), $default), $name);
    }

    public function param(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->all();
        }

        return $this->input($name, $default);
    }

    private function filterName(?string $name): ?string
    {
        if ($name === null || !str_contains($name, '/')) {
            return $name;
        }

        return explode('/', $name, 2)[0];
    }

    private function filterValue(mixed $value, ?string $name): mixed
    {
        if ($name === null || !str_contains($name, '/')) {
            return $value;
        }

        $filter = explode('/', $name, 2)[1] ?? '';
        return match ($filter) {
            's' => (string) $value,
            'd' => (int) $value,
            'b' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'a' => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
