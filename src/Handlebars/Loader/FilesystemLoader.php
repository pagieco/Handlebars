<?php

namespace Pagieco\Handlebars\Loader;

use Pagieco\Handlebars\Loader;
use Pagieco\Handlebars\HandlebarsString;

class FilesystemLoader implements Loader
{
    private string $_baseDir;
    private string $_extension = '.handlebars';
    private string $_prefix = '';
    private array $_templates = [];

    /**
     * Handlebars filesystem Loader constructor.
     *
     * $options array allows overriding certain Loader options during instantiation:
     *
     *     $options = array(
     *         // extension used for Handlebars templates. Defaults to '.handlebars'
     *         'extension' => '.other',
     *     );
     *
     * @param  string|array $baseDirs A path contain template files or array of paths
     * @param  array $options Array of Loader options (default: array())
     *
     * @throws \RuntimeException if $baseDir does not exist.
     */
    public function __construct(string $baseDirs, array $options = [])
    {
        if (is_string($baseDirs)) {
            $baseDirs = [rtrim(realpath($baseDirs), '/')];
        } else {
            foreach ($baseDirs as &$dir) {
                $dir = rtrim(realpath($dir), '/');
            }
            unset($dir);
        }

        $this->_baseDir = $baseDirs;

        foreach ($this->_baseDir as $dir) {
            if (! is_dir($dir)) {
                throw new \RuntimeException(
                    'FilesystemLoader baseDir must be a directory: ' . $dir
                );
            }
        }

        if (isset($options['extension'])) {
            $this->_extension = '.' . ltrim($options['extension'], '.');
        }

        if (isset($options['prefix'])) {
            $this->_prefix = $options['prefix'];
        }
    }

    /**
     * Load a Template by name.
     *
     *     $loader = new FilesystemLoader(dirname(__FILE__).'/views');
     *     // loads "./views/admin/dashboard.handlebars";
     *     $loader->load('admin/dashboard');
     *
     * @param  string $name
     * @return \Pagieco\Handlebars\HandlebarsString
     */
    public function load(string $name): HandlebarsString
    {
        if (! isset($this->_templates[$name])) {
            $this->_templates[$name] = $this->loadFile($name);
        }

        return new HandlebarsString($this->_templates[$name]);
    }

    /**
     * Helper function for loading a Handlebars file by name.
     *
     * @param  string $name
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function loadFile(string $name): string
    {
        $fileName = $this->getFileName($name);

        if ($fileName === false) {
            throw new \InvalidArgumentException('Template ' . $name . ' not found.');
        }

        return file_get_contents($fileName);
    }

    /**
     * Helper function for getting a Handlebars template file name.
     *
     * @param  string $name
     * @return string
     */
    protected function getFileName(string $name): string
    {
        foreach ($this->_baseDir as $baseDir) {
            $fileName = $baseDir . '/';
            $fileParts = explode('/', $name);
            $file = array_pop($fileParts);

            if (substr($file, strlen($this->_prefix)) !== $this->_prefix) {
                $file = $this->_prefix . $file;
            }

            $fileParts[] = $file;
            $fileName .= implode('/', $fileParts);
            $lastCharacters = substr($fileName, 0 - strlen($this->_extension));

            if ($lastCharacters !== $this->_extension) {
                $fileName .= $this->_extension;
            }

            if (file_exists($fileName)) {
                return $fileName;
            }
        }

        return false;
    }
}
