<?php

namespace Pagieco\Handlebars;

use DateTime;
use Traversable;
use LogicException;
use InvalidArgumentException;

class Helpers
{
    protected array $helpers = [];

    private array $tpl = [];

    protected array $builtinHelpers = [
        'if',
        'each',
        'with',
        'unless',
        'bindAttr',
        'upper',                // Put all chars in uppercase
        'lower',                // Put all chars in lowercase
        'capitalize',           // Capitalize just the first word
        'capitalize_words',     // Capitalize each words
        'reverse',              // Reverse a string
        'format_date',          // Format a date
        'inflect',              // Inflect the wording based on count ie. 1 album, 10 albums
        'default',              // If a variable is null, it will use the default instead
        'truncate',             // Truncate section
        'raw',                  // Return the source as is without converting
        'repeat',               // Repeat a section
        'define',               // Define a block to be used using "invoke"
        'invoke',               // Invoke a block that was defined with "define"
    ];

    /**
     * Create new helper container class
     *
     * @param  array $helpers
     * @throws \InvalidArgumentException
     */
    public function __construct(array $helpers = null)
    {
        foreach ($this->builtinHelpers as $helper) {
            $helperName = $this->underscoreToCamelCase($helper);
            $this->add($helper, [$this, "helper{$helperName}"]);
        }

        if ($helpers !== null) {
            if (! is_array($helpers) && ! $helpers instanceof Traversable) {
                throw new InvalidArgumentException('HelperCollection constructor expects an array of helpers');
            }

            foreach ($helpers as $name => $helper) {
                $this->add($name, $helper);
            }
        }
    }

    /**
     * Add a new helper to helpers
     *
     * @param  string $name
     * @param  callable $helper
     * @return void
     * @throws \InvalidArgumentException
     */
    public function add(string $name, callable $helper): void
    {
        $this->helpers[$name] = $helper;
    }

    /**
     * Check if $name helper is available
     *
     * @param  string $name
     * @return boolean
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->helpers);
    }

    /**
     * Get a helper. __magic__ method :)
     *
     * @param  string $name
     * @return callable
     * @throws \InvalidArgumentException
     */
    public function __get(string $name): callable
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException('Unknown helper :' . $name);
        }

        return $this->helpers[$name];
    }

    /**
     * Check if $name helper is available __magic__ method :)
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Add a new helper to helpers __magic__ method :)
     *
     * @param  string $name
     * @param  callable $helper
     * @return void
     */
    public function __set(string $name, callable $helper): void
    {
        $this->add($name, $helper);
    }

    /**
     * Unset a helper
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->helpers[$name]);
    }

    /**
     * Check whether a given helper is present in the collection.
     *
     * @param  string $name
     * @return void
     * @throws \InvalidArgumentException
     */
    public function remove(string $name): void
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException('Unknown helper: ' . $name);
        }
        unset($this->helpers[$name]);
    }

    /**
     * Clear the helper collection.
     * Removes all helpers from this collection
     *
     * @return void
     */
    public function clear(): void
    {
        $this->helpers = [];
    }

    /**
     * Check whether the helper collection is empty.
     *
     * @return boolean
     */
    public function isEmpty(): bool
    {
        return empty($this->helpers);
    }

    /**
     * Create handler for the 'if' helper.
     *
     * {{#if condition}}
     *      Something here
     * {{else}}
     *      something else here
     * {{/if}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperIf(Template $template, Context $context, string $args, string $source)
    {
        $tmp = $context->get($args);

        if ($tmp) {
            $template->setStopToken('else');
            $buffer = $template->render($context);
            $template->setStopToken(false);
            $template->discard();

            return $buffer;
        }

        return $this->renderElse($template, $context);
    }


    /**
     * Create handler for the 'each' helper.
     * example {{#each people}} {{name}} {{/each}}
     * example with slice: {{#each people[0:10]}} {{name}} {{/each}}
     * example with else
     *  {{#each Array}}
     *        {{.}}
     *  {{else}}
     *      Nothing found
     *  {{/each}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperEach(Template $template, Context $context, array $args, string $source)
    {
        [$keyname, $slice_start, $slice_end] = $this->extractSlice($args);

        $tmp = $context->get($keyname);

        if (is_array($tmp) || $tmp instanceof Traversable) {
            $tmp = array_slice($tmp, $slice_start, $slice_end);
            $buffer = '';
            $islist = array_values($tmp) === $tmp;

            if (is_array($tmp) && ! count($tmp)) {
                return $this->renderElse($template, $context);
            }

            foreach ($tmp as $key => $var) {
                $tpl = clone $template;

                if ($islist) {
                    $context->pushIndex($key);
                } else {
                    $context->pushKey($key);
                }

                $context->push($var);
                $tpl->setStopToken('else');
                $buffer .= $tpl->render($context);
                $context->pop();

                if ($islist) {
                    $context->popIndex();
                } else {
                    $context->popKey();
                }
            }
            return $buffer;
        }

        return $this->renderElse($template, $context);
    }

    /**
     * Applying the DRY principle here.
     * This method help us render {{else}} portion of a block
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @return string
     */
    private function renderElse(Template $template, Context $context): string
    {
        $template->setStopToken('else');
        $template->discard();
        $template->setStopToken(false);

        return $template->render($context);
    }


    /**
     * Create handler for the 'unless' helper.
     * {{#unless condition}}
     *      Something here
     * {{else}}
     *      something else here
     * {{/unless}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperUnless(Template $template, Context $context, array $args, string $source)
    {
        $tmp = $context->get($args);

        if (! $tmp) {
            $template->setStopToken('else');
            $buffer = $template->render($context);
            $template->setStopToken(false);
            $template->discard();

            return $buffer;
        }

        return $this->renderElse($template, $context);
    }

    /**
     * Create handler for the 'with' helper.
     * Needed for compatibility with PHP 5.2 since it doesn't support anonymous
     * functions.
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperWith(Template $template, Context $context, array $args, string $source)
    {
        $tmp = $context->get($args);
        $context->push($tmp);
        $buffer = $template->render($context);
        $context->pop();

        return $buffer;
    }

    /**
     * Create handler for the 'bindAttr' helper.
     * Needed for compatibility with PHP 5.2 since it doesn't support anonymous
     * functions.
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperBindAttr(Template $template, Context $context, array $args, string $source)
    {
        return $args;
    }

    /**
     * To uppercase string
     *
     * {{#upper data}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperUpper(Template $template, Context $context, array $args, string $source): string
    {
        return strtoupper($context->get($args));
    }

    /**
     * To lowercase string
     *
     * {{#lower data}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperLower(Template $template, Context $context, array $args, string $source): string
    {
        return strtolower($context->get($args));
    }

    /**
     * to capitalize first letter
     *
     * {{#capitalize}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperCapitalize(Template $template, Context $context, array $args, string $source): string
    {
        return ucfirst($context->get($args));
    }

    /**
     * To capitalize first letter in each word
     *
     * {{#capitalize_words data}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperCapitalizeWords(Template $template, Context $context, array $args, string $source): string
    {
        return ucwords($context->get($args));
    }

    /**
     * To reverse a string
     *
     * {{#reverse data}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperReverse(Template $template, Context $context, array $args, string $source): string
    {
        return strrev($context->get($args));
    }

    /**
     * Format a date
     *
     * {{#format_date date 'Y-m-d @h:i:s'}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     * @throws \Exception
     */
    public function helperFormatDate(Template $template, Context $context, array $args, string $source): string
    {
        preg_match("/(.*?)\s+(?:(?:\"|\')(.*?)(?:\"|\'))/", $args, $m);

        $keyname = $m[1];
        $format = $m[2];

        $date = $context->get($keyname);

        if ($format) {
            $dt = is_numeric($date) ? (new DateTime)->setTimestamp($date) : new DateTime($date);

            return $dt->format($format);
        }

        return $date;
    }

    /**
     * {{inflect count 'album' 'albums'}}
     * {{inflect count '%d album' '%d albums'}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperInflect(Template $template, Context $context, array $args, string $source): string
    {
        preg_match("/(.*?)\s+(?:(?:\"|\')(.*?)(?:\"|\'))\s+(?:(?:\"|\')(.*?)(?:\"|\'))/", $args, $m);

        $keyname = $m[1];
        $singular = $m[2];
        $plurial = $m[3];
        $value = $context->get($keyname);
        $inflect = ($value <= 1) ? $singular : $plurial;

        return sprintf($inflect, $value);
    }

    /**
     * Provide a default fallback
     *
     * {{default title "No title available"}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperDefault(Template $template, Context $context, array $args, string $source): string
    {
        preg_match("/(.*?)\s+(?:(?:\"|\')(.*?)(?:\"|\'))/", trim($args), $m);

        $keyname = $m[1];
        $default = $m[2];
        $value = $context->get($keyname);

        return ($value) ?: $default;
    }

    /**
     * Truncate a string to a length, and append and ellipsis if provided
     * {{#truncate content 5 "..."}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperTruncate(Template $template, Context $context, array $args, string $source): string
    {
        preg_match("/(.*?)\s+(.*?)\s+(?:(?:\"|\')(.*?)(?:\"|\'))/", trim($args), $m);

        $keyname = $m[1];
        $limit = $m[2];
        $ellipsis = $m[3];

        $value = substr($context->get($keyname), 0, $limit);

        if ($ellipsis && strlen($context->get($keyname)) > $limit) {
            $value .= $ellipsis;
        }

        return $value;
    }

    /**
     * Return the data source as is
     *
     * {{#raw}} {{/raw}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperRaw(Template $template, Context $context, array $args, string $source): string
    {
        return $source;
    }

    /**
     * Repeat section $x times.
     *
     * {{#repeat 10}}
     *      This section will be repeated 10 times
     * {{/repeat}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperRepeat(Template $template, Context $context, array $args, string $source): string
    {
        return str_repeat($template->render($context), (int)$args);
    }


    /**
     * Define a section to be used later by using 'invoke'
     *
     * --> Define a section: hello
     * {{#define hello}}
     *      Hello World!
     *
     *      How is everything?
     * {{/define}}
     *
     * --> This is how it is called
     * {{#invoke hello}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperDefine(Template $template, Context $context, array $args, string $source): string
    {
        $this->tpl['DEFINE'][$args] = clone($template);
    }

    /**
     * Invoke a section that was created using 'define'
     *
     * --> Define a section: hello
     * {{#define hello}}
     *      Hello World!
     *
     *      How is everything?
     * {{/define}}
     *
     * --> This is how it is called
     * {{#invoke hello}}
     *
     * @param  \Pagieco\Handlebars\Template $template
     * @param  \Pagieco\Handlebars\Context $context
     * @param  mixed $args
     * @param  string $source
     * @return mixed
     */
    public function helperInvoke(Template $template, Context $context, array $args, string $source): string
    {
        if (! isset($this->tpl['DEFINE'][$args])) {
            throw new LogicException(sprintf("Can't INVOKE '%s'. '%s' was not DEFINE ", $args, $args));
        }

        return $this->tpl['DEFINE'][$args]->render($context);
    }


    /**
     * Change underscore helper name to CamelCase
     *
     * @param  string $string
     * @return string
     */
    private function underscoreToCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * slice
     * Allow to split the data that will be returned
     * #loop[start:end] => starts at start trhough end -1
     * #loop[start:] = Starts at start though the rest of the array
     * #loop[:end] = Starts at the beginning through end -1
     * #loop[:] = A copy of the whole array
     *
     * #loop[-1]
     * #loop[-2:] = Last two items
     * #loop[:-2] = Everything except last two items
     *
     * @param  string $string
     * @return array
     */
    private function extractSlice(string $string): array
    {
        preg_match("/^([\w\._\-]+)(?:\[([\-0-9]*?:[\-0-9]*?)\])?/i", $string, $m);

        $slice_start = $slice_end = null;
        if (isset($m[2])) {
            [$slice_start, $slice_end] = explode(':', $m[2]);

            $slice_start = (int)$slice_start;
            $slice_end = $slice_end ? (int)$slice_end : null;
        }

        return [$m[1], $slice_start, $slice_end];
    }
}
