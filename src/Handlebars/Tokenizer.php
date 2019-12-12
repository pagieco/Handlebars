<?php

namespace Pagieco\Handlebars;

class Tokenizer
{
    // Finite state machine states
    protected const IN_TEXT = 0;
    protected const IN_TAG_TYPE = 1;
    protected const IN_TAG = 2;

    // Token types
    public const T_SECTION = '#';
    public const T_INVERTED = '^';
    public const T_END_SECTION = '/';
    public const T_COMMENT = '!';
    // XXX: remove partials support from tokenizer and make it a helper?
    public const T_PARTIAL = '>';
    public const T_PARTIAL_2 = '<';
    public const T_DELIM_CHANGE = '=';
    public const T_ESCAPED = '_v';
    public const T_UNESCAPED = '{';
    public const T_UNESCAPED_2 = '&';
    public const T_TEXT = '_t';

    // Valid token types
    private array $tagTypes = [
        self::T_SECTION => true,
        self::T_INVERTED => true,
        self::T_END_SECTION => true,
        self::T_COMMENT => true,
        self::T_PARTIAL => true,
        self::T_PARTIAL_2 => true,
        self::T_DELIM_CHANGE => true,
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true,
    ];

    // Interpolated tags
    private array $interpolatedTags = [
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true,
    ];

    // Token properties
    public const TYPE = 'type';
    public const NAME = 'name';
    public const OTAG = 'otag';
    public const CTAG = 'ctag';
    public const INDEX = 'index';
    public const END = 'end';
    public const INDENT = 'indent';
    public const NODES = 'nodes';
    public const VALUE = 'value';
    public const ARGS = 'args';

    protected int $state;
    protected ?string $tagType;
    protected ?string $tag;
    protected string $buffer;
    protected array $tokens;
    protected bool $seenTag;
    protected int $lineStart;
    protected string $otag;
    protected string $ctag;

    /**
     * Scan and tokenize template source.
     *
     * @param  string $text
     * @param  string $delimiters
     * @return array
     */
    public function scan(string $text, string $delimiters = null): array
    {
        if ($text instanceof HandlebarsString) {
            $text = $text->getString();
        }

        $this->reset();

        if ($delimiters = trim($delimiters)) {
            [$otag, $ctag] = explode(' ', $delimiters);

            $this->otag = $otag;
            $this->ctag = $ctag;
        }

        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            switch ($this->state) {
                case self::IN_TEXT:
                    if ($this->tagChange($this->otag, $text, $i)) {
                        $i--;
                        $this->flushBuffer();
                        $this->state = self::IN_TAG_TYPE;
                    } else if ($text[$i] === "\n") {
                        $this->filterLine();
                    } else {
                        $this->buffer .= $text[$i];
                    }
                    break;

                case self::IN_TAG_TYPE:
                    $i += strlen($this->otag) - 1;

                    if (isset($this->tagTypes[$text[$i + 1]])) {
                        $tag = $text[$i + 1];
                        $this->tagType = $tag;
                    } else {
                        $tag = null;
                        $this->tagType = self::T_ESCAPED;
                    }

                    if ($this->tagType === self::T_DELIM_CHANGE) {
                        $i = $this->changeDelimiters($text, $i);
                        $this->state = self::IN_TEXT;
                    } else {
                        if ($tag !== null) {
                            $i++;
                        }
                        $this->state = self::IN_TAG;
                    }

                    $this->seenTag = $i;
                    break;

                default:
                    if ($this->tagChange($this->ctag, $text, $i)) {
                        // Sections (Helpers) can accept parameters
                        // Same thing for Partials (little known fact)
                        if (in_array($this->tagType, [self::T_SECTION, self::T_PARTIAL, self::T_PARTIAL_2], true)) {
                            $newBuffer = explode(' ', trim($this->buffer), 2);
                            $args = '';

                            if (count($newBuffer) === 2) {
                                $args = $newBuffer[1];
                            }

                            $this->buffer = $newBuffer[0];
                        }

                        $t = [
                            self::TYPE => $this->tagType,
                            self::NAME => trim($this->buffer),
                            self::OTAG => $this->otag,
                            self::CTAG => $this->ctag,
                            self::INDEX => ($this->tagType === self::T_END_SECTION)
                                ? $this->seenTag - strlen($this->otag)
                                : $i + strlen($this->ctag),
                        ];

                        if (isset($args)) {
                            $t[self::ARGS] = $args;
                        }

                        $this->tokens[] = $t;

                        unset($t, $args);

                        $this->buffer = '';
                        $i += strlen($this->ctag) - 1;
                        $this->state = self::IN_TEXT;

                        if ($this->tagType === self::T_UNESCAPED) {
                            if ($this->ctag === '}}') {
                                $i++;
                            } else {
                                // Clean up `{{{ tripleStache }}}` style tokens.
                                $lastIndex = count($this->tokens) - 1;
                                $lastName = $this->tokens[$lastIndex][self::NAME];

                                if (substr($lastName, -1) === '}') {
                                    $this->tokens[$lastIndex][self::NAME] = trim(
                                        substr($lastName, 0, -1)
                                    );
                                }
                            }
                        }
                    } else {
                        $this->buffer .= $text[$i];
                    }

                    break;
            }
        }

        $this->filterLine(true);

        return $this->tokens;
    }

    /**
     * Helper function to reset tokenizer internal state.
     *
     * @return void
     */
    protected function reset(): void
    {
        $this->state = self::IN_TEXT;
        $this->tagType = null;
        $this->tag = null;
        $this->buffer = '';
        $this->tokens = [];
        $this->seenTag = false;
        $this->lineStart = 0;
        $this->otag = '{{';
        $this->ctag = '}}';
    }

    /**
     * Flush the current buffer to a token.
     *
     * @return void
     */
    protected function flushBuffer(): void
    {
        if (! empty($this->buffer)) {
            $this->tokens[] = [
                self::TYPE => self::T_TEXT,
                self::VALUE => $this->buffer
            ];

            $this->buffer = '';
        }
    }

    /**
     * Test whether the current line is entirely made up of whitespace.
     *
     * @return boolean
     */
    protected function lineIsWhitespace(): bool
    {
        $tokensCount = count($this->tokens);

        for ($j = $this->lineStart; $j < $tokensCount; $j++) {
            $token = $this->tokens[$j];
            if (isset($this->tagTypes[$token[self::TYPE]])) {
                if (isset($this->interpolatedTags[$token[self::TYPE]])) {
                    return false;
                }
            } else if (($token[self::TYPE] === self::T_TEXT) && preg_match('/\S/', $token[self::VALUE])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter out whitespace-only lines and store indent levels for partials.
     *
     * @param  bool $noNewLine
     * @return void
     */
    protected function filterLine(bool $noNewLine = false): void
    {
        $this->flushBuffer();

        if ($this->seenTag && $this->lineIsWhitespace()) {
            $tokensCount = count($this->tokens);
            for ($j = $this->lineStart; $j < $tokensCount; $j++) {
                if ($this->tokens[$j][self::TYPE] === self::T_TEXT) {
                    if (isset($this->tokens[$j + 1]) && $this->tokens[$j + 1][self::TYPE] === self::T_PARTIAL) {
                        $this->tokens[$j + 1][self::INDENT] = $this->tokens[$j][self::VALUE];
                    }

                    $this->tokens[$j] = null;
                }
            }
        } else if (! $noNewLine) {
            $this->tokens[] = [self::TYPE => self::T_TEXT, self::VALUE => "\n"];
        }

        $this->seenTag = false;
        $this->lineStart = count($this->tokens);
    }

    /**
     * Change the current Mustache delimiters. Set new `otag` and `ctag` values.
     *
     * @param  string $text
     * @param  int $index
     * @return int
     */
    protected function changeDelimiters(string $text, int $index): int
    {
        $startIndex = strpos($text, '=', $index) + 1;
        $close = '=' . $this->ctag;
        $closeIndex = strpos($text, $close, $index);

        [$otag, $ctag] = explode(' ', trim(substr($text, $startIndex, $closeIndex - $startIndex)));

        $this->otag = $otag;
        $this->ctag = $ctag;

        return $closeIndex + strlen($close) - 1;
    }

    /**
     * Test whether it's time to change tags.
     *
     * @param  string $tag
     * @param  string $text
     * @param  int $index
     * @return boolean
     */
    protected function tagChange(string $tag, string $text, int $index): bool
    {
        return substr($text, $index, strlen($tag)) === $tag;
    }
}
