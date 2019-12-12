<?php

namespace Pagieco\Handlebars;

use InvalidArgumentException;

class Context
{
    protected array $stack = [];

    protected array $index = [];

    protected array $key = [];

    /**
     * Mustache rendering Context constructor.
     *
     * @param  mixed $context
     */
    public function __construct($context = null)
    {
        if ($context !== null) {
            $this->stack = [$context];
        }
    }

    /**
     * Push a new Context frame onto the stack.
     *
     * @param  mixed $value
     * @return void
     */
    public function push($value): void
    {
        $this->stack[] = $value;
    }

    /**
     * Push an Index onto the index stack
     *
     * @param  integer $index
     * @return void
     */
    public function pushIndex($index): void
    {
        $this->index[] = $index;
    }

    /**
     * Push a Key onto the key stack
     *
     * @param  string $key
     * @return void
     */
    public function pushKey(string $key): void
    {
        $this->key[] = $key;
    }

    /**
     * Pop the last Context frame from the stack.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->stack);
    }

    /**
     * Pop the last index from the stack.
     *
     * @return int
     */
    public function popIndex(): int
    {
        return array_pop($this->index);
    }

    /**
     * Pop the last key from the stack.
     *
     * @return string
     */
    public function popKey(): string
    {
        return array_pop($this->key);
    }

    /**
     * Get the last Context frame.
     *
     * @return mixed
     */
    public function last()
    {
        return end($this->stack);
    }

    /**
     * Get the index of current section item.
     *
     * @return mixed
     */
    public function lastIndex()
    {
        return end($this->index);
    }

    /**
     * Get the key of current object property.
     *
     * @return mixed
     */
    public function lastKey()
    {
        return end($this->key);
    }

    /**
     * Change the current context to one of current context members
     *
     * @param  string $variableName
     * @return mixed
     */
    public function with(string $variableName)
    {
        $value = $this->get($variableName);
        $this->push($value);

        return $value;
    }

    /**
     * Get a avariable from current context
     * Supported types :
     * variable , ../variable , variable.variable , .
     *
     * @param  string $variableName
     * @param  boolean $strict
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get(string $variableName, bool $strict = false)
    {
        //Need to clean up
        $variableName = trim($variableName);
        $level = 0;

        while (strpos($variableName, '../') === 0) {
            $variableName = trim(substr($variableName, 3));
            $level++;
        }

        if (count($this->stack) < $level) {
            if ($strict) {
                throw new InvalidArgumentException('can not find variable in context');
            }

            return '';
        }

        end($this->stack);

        while ($level) {
            prev($this->stack);
            $level--;
        }

        $current = current($this->stack);

        if (! $variableName) {
            if ($strict) {
                throw new InvalidArgumentException('can not find variable in context');
            }
            return '';
        }

        if ($variableName === '.' || $variableName === 'this') {
            return $current;
        }

        $chunks = explode('.', $variableName);

        foreach ($chunks as $chunk) {
            if (is_string($current) && $current === '') {
                return $current;
            }

            $current = $this->findVariableInContext($current, $chunk, $strict);
        }

        return $current;
    }

    /**
     * Check if $variable->$inside is available
     *
     * @param  mixed $variable
     * @param  string $inside
     * @param  boolean $strict
     * @return boolean
     * @throws \InvalidArgumentException
     */
    private function findVariableInContext($variable, string $inside, bool $strict = false): bool
    {
        $value = '';
        if (($inside !== '0' && empty($inside)) || ($inside === 'this')) {
            return $variable;
        }

        if (is_array($variable)) {
            if (isset($variable[$inside])) {
                $value = $variable[$inside];
            }
        } else if (is_object($variable)) {
            if (isset($variable->$inside)) {
                $value = $variable->$inside;
            } else if (is_callable([$variable, $inside])) {
                $value = $variable->$inside();
            }
        } else if ($inside === '.') {
            $value = $variable;
        } else if ($strict) {
            throw new InvalidArgumentException('can not find variable in context');
        }

        return $value;
    }
}
