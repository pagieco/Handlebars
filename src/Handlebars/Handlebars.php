<?php

namespace Pagieco\Handlebars;

use Pagieco\Handlebars\Cache\Dummy;
use Pagieco\Handlebars\Loader\StringLoader;

class Handlebars
{
    private Tokenizer $tokenizer;

    private Parser $parser;

    private Helpers $helpers;

    private Loader $loader;

    private Loader $partialsLoader;

    private Cache $cache;

    private array $aliases = [];

    /**
     * Shortcut 'render' invocation.
     * Equivalent to calling `$handlebars->loadTemplate($template)->render($data);`
     *
     * @param  string $template
     * @param  mixed $data
     * @return string
     */
    public function render(string $template, array $data): string
    {
        return $this->loadTemplate($template)->render($data);
    }

    /**
     * Set helpers for current enfine
     *
     * @param  \Pagieco\Handlebars\Helpers $helpers
     * @return void
     */
    public function setHelpers(Helpers $helpers): void
    {
        $this->helpers = $helpers;
    }

    /**
     * Get helpers, or create new one if there is no helper
     *
     * @return \Pagieco\Handlebars\Helpers
     */
    public function getHelpers(): Helpers
    {
        if (! isset($this->helpers)) {
            $this->helpers = new Helpers();
        }

        return $this->helpers;
    }

    /**
     * Add a new helper.
     *
     * @param  string $name
     * @param  mixed $helper
     * @return void
     */
    public function addHelper(string $name, callable $helper): void
    {
        $this->getHelpers()->add($name, $helper);
    }

    /**
     * Get a helper by name.
     *
     * @param  string $name
     * @return callable
     */
    public function getHelper(string $name): callable
    {
        return $this->getHelpers()->__get($name);
    }

    /**
     * Check whether this instance has a helper.
     *
     * @param  string $name
     * @return boolean
     */
    public function hasHelper(string $name): bool
    {
        return $this->getHelpers()->has($name);
    }

    /**
     * Remove a helper by name.
     *
     * @param  string $name
     * @return void
     */
    public function removeHelper(string $name): void
    {
        $this->getHelpers()->remove($name);
    }

    /**
     * Set current loader
     *
     * @param  \Pagieco\Handlebars\Loader $loader
     * @return void
     */
    public function setLoader(Loader $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * get current loader
     *
     * @return \Pagieco\Handlebars\Loader
     */
    public function getLoader(): Loader
    {
        if (! isset($this->loader)) {
            $this->loader = new StringLoader();
        }

        return $this->loader;
    }

    /**
     * Set current partials loader
     *
     * @param  \Pagieco\Handlebars\Loader $loader
     * @return void
     */
    public function setPartialsLoader(Loader $loader): void
    {
        $this->partialsLoader = $loader;
    }

    /**
     * get current partials loader
     *
     * @return \Pagieco\Handlebars\Loader
     */
    public function getPartialsLoader(): Loader
    {
        if (! isset($this->partialsLoader)) {
            $this->partialsLoader = new StringLoader();
        }

        return $this->partialsLoader;
    }

    /**
     * Set cache  for current engine
     *
     * @param  \Pagieco\Handlebars\Cache $cache
     * @return void
     */
    public function setCache(Cache $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get cache
     *
     * @return \Pagieco\Handlebars\Cache
     */
    public function getCache(): Cache
    {
        if (! isset($this->cache)) {
            $this->cache = new Dummy();
        }

        return $this->cache;
    }

    /**
     * Set the Handlebars Tokenizer instance.
     *
     * @param  \Pagieco\Handlebars\Tokenizer $tokenizer
     * @return void
     */
    public function setTokenizer(Tokenizer $tokenizer): void
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Get the current Handlebars Tokenizer instance. If no Tokenizer instance has been
     * explicitly specified, this method will instantiate and return a new one.
     *
     * @return \Pagieco\Handlebars\Tokenizer
     */
    public function getTokenizer(): Tokenizer
    {
        if (! isset($this->tokenizer)) {
            $this->tokenizer = new Tokenizer();
        }

        return $this->tokenizer;
    }

    /**
     * Set the Handlebars Parser instance.
     *
     * @param  \Pagieco\Handlebars\Parser $parser
     * @return void
     */
    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * Get the current Handlebars Parser instance. If no Parser instance has been
     * explicitly specified, this method will instantiate and return a new one.
     *
     * @return \Pagieco\Handlebars\Parser
     */
    public function getParser(): Parser
    {
        if (! isset($this->parser)) {
            $this->parser = new Parser();
        }

        return $this->parser;
    }

    /**
     * Load a template by name with current template loader.
     *
     * @param  string $name
     * @return \Pagieco\Handlebars\Template
     */
    public function loadTemplate(string $name): Template
    {
        $source = $this->getLoader()->load($name);
        $tree = $this->tokenize($source);

        return new Template($this, $tree, $source);
    }

    /**
     * Load a partial by name with current partial loader.
     *
     * @param  string $name
     * @return \Pagieco\Handlebars\Template
     */
    public function loadPartial(string $name): Template
    {
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        $source = $this->getPartialsLoader()->load($name);
        $tree = $this->tokenize($source);

        return new Template($this, $tree, $source);
    }

    /**
     * Register partial alias.
     *
     * @param  string $alias
     * @param  string $content
     * @return void
     */
    public function registerPartial(string $alias, string $content): void
    {
        $this->aliases[$alias] = $content;
    }

    /**
     * Un-register partial alias.
     *
     * @param  string $alias
     * @return void
     */
    public function unRegisterPartial(string $alias): void
    {
        if (isset($this->aliases[$alias])) {
            unset($this->aliases[$alias]);
        }
    }

    /**
     * Load string into a template object.
     *
     * @param  string $source
     * @return \Pagieco\Handlebars\Template
     */
    public function loadString(string $source): Template
    {
        $tree = $this->tokenize($source);
        return new Template($this, $tree, $source);
    }

    /**
     * try to tokenize source, or get them from cache if available.
     *
     * @param  string $source
     * @return array
     */
    private function tokenize(string $source): array
    {
        $hash = md5($source);
        $tree = $this->getCache()->get($hash);

        if ($tree === false) {
            $tokens = $this->getTokenizer()->scan($source);
            $tree = $this->getParser()->parse($tokens);
            $this->getCache()->set($hash, $tree);
        }

        return $tree;
    }
}
