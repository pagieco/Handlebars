<?php

namespace Pagieco\Handlebars;

interface Loader
{
    /**
     * Load a Template by name.
     *
     * @param  string $name
     * @return \Pagieco\Handlebars\HandlebarsString
     */
    public function load(string $name): HandlebarsString;
}
