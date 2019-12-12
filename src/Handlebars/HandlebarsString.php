<?php

namespace Pagieco\Handlebars;

class HandlebarsString
{
    private string $string = '';

    public function __construct(string $string)
    {
        $this->setString($string);
    }

    public function __toString(): string
    {
        return $this->getString();
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function setString(string $string): void
    {
        $this->string = $string;
    }
}
