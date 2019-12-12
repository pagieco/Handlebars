<?php

namespace Pagieco\Handlebars\Cache;

use RuntimeException;
use InvalidArgumentException;
use Pagieco\Handlebars\Cache;

class Disk implements Cache
{
    private string $path;
    private string $prefix;
    private string $suffix;

    /**
     * Construct the disk cache.
     *
     * @param  string $path
     * @param  string $prefix
     * @param  string $suffix
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct(string $path, string $prefix = '', string $suffix = '')
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Must specify disk cache path');
        } else if (! is_dir($path)) {
            if (! mkdir($path, 0777, true) && ! is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }

            if (! is_dir($path)) {
                throw new RuntimeException('Could not create cache file path');
            }
        }

        $this->path = $path;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }

    /**
     * Gets the full disk path for a given cache item's file,
     * taking into account the cache path, optional prefix,
     * and optional extension.
     *
     * @param  string $name
     * @return string
     */
    private function getPath(string $name): string
    {
        return $this->path . DIRECTORY_SEPARATOR .
            $this->prefix . $name . $this->suffix;
    }

    /**
     * Get cache for $name if it exists.
     *
     * @param  string $name
     * @return mixed
     */
    public function get(string $name)
    {
        $path = $this->getPath($name);

        return (file_exists($path)) ? unserialize(file_get_contents($path)) : false;
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
        $path = $this->getPath($name);

        file_put_contents($path, serialize($value));
    }

    /**
     * Remove cache
     *
     * @param  string $name
     * @return void
     */
    public function remove(string $name): void
    {
        $path = $this->getPath($name);

        unlink($path);
    }
}
