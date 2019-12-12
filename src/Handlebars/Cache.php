<?php

namespace Pagieco\Handlebars;

interface Cache
{
    public function get(string $name);

    public function set(string $name, $value): void;

    public function remove(string $name): void;
}
