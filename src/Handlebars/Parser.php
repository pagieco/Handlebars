<?php

namespace Pagieco\Handlebars;

use ArrayIterator;
use LogicException;

class Parser
{
    /**
     * Process array of tokens and convert them into parse tree
     *
     * @param  array $tokens
     * @return array
     */
    public function parse(array $tokens = []): array
    {
        return $this->buildTree(new ArrayIterator($tokens));
    }

    /**
     * Helper method for recursively building a parse tree.
     *
     * @param  \ArrayIterator $tokens
     * @return array
     * @throws \LogicException
     */
    private function buildTree(ArrayIterator $tokens): array
    {
        $stack = [];

        do {
            $token = $tokens->current();
            $tokens->next();

            if ($token === null) {
                continue;
            }

            if ($token[Tokenizer::TYPE] === Tokenizer::T_END_SECTION) {
                $newNodes = [];

                do {
                    $result = array_pop($stack);

                    if ($result === null) {
                        throw new LogicException('Unexpected closing tag: /' . $token[Tokenizer::NAME]);
                    }

                    if (! array_key_exists(Tokenizer::NODES, $result)
                        && isset($result[Tokenizer::NAME])
                        && $result[Tokenizer::NAME] === $token[Tokenizer::NAME]) {
                        $result[Tokenizer::NODES] = $newNodes;
                        $result[Tokenizer::END] = $token[Tokenizer::INDEX];
                        $stack[] = $result;

                        break 2;
                    }

                    array_unshift($newNodes, $result);
                } while (true);
            } else {
                $stack[] = $token;
            }
        } while ($tokens->valid());

        return $stack;
    }
}
