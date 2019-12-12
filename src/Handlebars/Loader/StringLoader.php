<?php

namespace Pagieco\Handlebars\Loader;

use Pagieco\Handlebars\Loader;
use Pagieco\Handlebars\HandlebarsString;

class StringLoader implements Loader
{
    public function load(string $name): HandlebarsString
    {
        return new HandlebarsString($name);
    }
}
