<?php

namespace Pagieco\Handlebars\Cache;

use Pagieco\Handlebars\Cache;

class Dummy implements Cache
{
    private array $cache = [];

    /**
     * Get cache for $name if exist.
     *
     * @param  string $name
     * @return mixed
     */
    public function get(string $name)
    {
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        return false;
    }

    /**
     * Set a cache
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function set(string $name, $value): void
    {
        $this->cache[$name] = $value;
    }

    /**
     * Remove cache
     *
     * @param  string $name
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->cache[$name]);
    }
}
