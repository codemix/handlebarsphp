<?php

namespace Handlebars;

/**
 * # Tokenizer
 *
 * The Tokenizer is responsible for taking a handlebars template as input
 * and turning it into an array of tokens that can be consumed by the parser.
 *
 * Tokens are either raw strings, which should pass through the compiler unmodified,
 * or simple objects with `type` properties.
 *
 * Each protected tokenize method should accept a single string as input
 * and either return the input directly (if no match was found), or return
 * a 2 element array, the first element of which should contain the AST entity and the second
 * element should be a string containing any remaining text that was not parsed.
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars
 */
class Tokenizer
{
    /**
     * Tokenize the input and return an array of tokens.
     *
     * @param string $input the input to tokenize.
     *
     * @return array the tokens.
     */
    public function tokenize($input)
    {
        $tokens = array();
        $remaining = $input;
        while(is_array($chunk = $this->chomp($remaining))) {
            list($head, $token, $remaining) = $chunk;
            if (!empty($head))
                $tokens[] = $head;
            $tokens[] = $token;
        }
        if (!is_array($chunk) && !empty($chunk))
            $tokens[] = $chunk;
        return $tokens;
    }

    /**
     * Consumes the first template tag in the given input.
     * If no tag is found the input is returned directly.
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise a 3 element
     * array containing the text before the token, the token itself and any remaining unparsed text.
     */
    protected function chomp($input)
    {
        if (!preg_match("/^(.*?)(\{\{(\{)?(((?!\}\}).)+)\}\}(\})?)(.*)$/msu", $input, $matches))
            return $input;
        $head = $matches[1];
        $tail = $matches[7];
        $tokenContent = $matches[4];
        $parsed = $this->tokenizeTag($tokenContent);
        if (!is_array($parsed)) {
            // this is an invalid tag
            $head .= $matches[2];
            $remaining = $this->chomp($tail);
            if (!is_array($remaining))
                return $head.$remaining;
            else
                return array(
                    $head.$remaining[0],
                    $remaining[1],
                    $remaining[2]
                );
        }
        list($token, $remaining) = $parsed;
        $token->raw = !empty($matches[3]) && !empty($matches[6]);

        return array($head, $token, $tail);
    }

    /**
     * Tokenize the content of a template tag.
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeTag($input)
    {
        $types = array(
            '#' => 'startBlock',
            '/' => 'endBlock',
            '@' => 'reference',
            '>' => 'partial',
            '!' => 'comment',
        );
        if (preg_match("/^(!|#|\/|\@|>)?(.*)/msu", $input, $matches)) {
            if (!empty($matches[1]))
                $type = $types[$matches[1]];
            else
                $type = 'expression';
            if ($type === 'partial')
                return $this->tokenizePartial(trim($matches[2]));
            elseif ($type === 'comment') {
                return $this->tokenizeComment($matches[2]);
            }
            $parsed = $this->tokenizeExpression($matches[2]);
            if (!is_array($parsed))
                return $input;
            list($expression, $remaining) = $parsed;
            $expression->type = $type;
            return array($expression, $remaining);

        }
        else
            return $input;
    }

    /**
     * Tokenize an integer
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeInteger($input)
    {
        if (!preg_match("/^(\.)?(\d+)(.*)/", $input, $matches))
            return $input;
        else
            return array(
                (object) array(
                    'type' => 'integer',
                    'value' => $matches[2]
                ),
                $matches[3]
            );
    }

    /**
     * Tokenize a string
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeString($input)
    {
        if (!preg_match("/^('|\")(.*?)(([^\\\])(\\1))(.*)/msu", $input, $matches))
            return $input;
        else
            return array(
                (object) array(
                    'type' => 'string',
                    'delimiter' => $matches[1],
                    'value' => $matches[2].$matches[4],
                ),
                $matches[6]
            );
    }

    /**
     * Tokenize a parent accessor
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeParentAccessor($input)
    {
        if (!preg_match("/^(\.\.\/)(.*)/msu", $input, $matches))
            return $input;
        $accessor = (object) array(
            'type' => 'parentAccessor',
            'name' => $matches[1],
            'depth' => 1
        );
        $remaining = $matches[2];
        while(preg_match("/^(\.\.\/)(.*)/msu", $remaining, $matches)) {
            $accessor->depth++;
            $remaining = $matches[2];
        }
        return array($accessor, $remaining);
    }

    /**
     * Tokenize part of an identifier
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeIdentifierPart($input)
    {

        if (preg_match("/^(\.)?([\$A-Z-a-z_][A-Za-z0-9_]*)(.*)/msu",$input, $matches))
            return array(
                (object) array(
                    'type' => $matches[1] ? 'accessor' : 'identifier',
                    'name' => $matches[2],
                ),
                $matches[3]
            );
        else
            return $input;
    }

    /**
     * Tokenize an accessor
     *
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeAccessor($input)
    {
        if (!is_array($parsed = $this->tokenizeParentAccessor($input)) && !is_array($parsed = $this->tokenizeIdentifierPart($input)))
            return $input;
        list($identifier, $remaining) = $parsed;
        $identifier->accessors = array();
        while(is_array($parsed = $this->tokenizeIdentifierPart($remaining)) || is_array($parsed = $this->tokenizeInteger($remaining))) {
            list($accessor, $remaining) = $parsed;
            $identifier->accessors[] = $accessor;
        }
        return array($identifier, $remaining);
    }

    /**
     * Tokenize an expression
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeExpression($input)
    {
        $parsed = $this->tokenizeAccessor($input);
        if (!is_array($parsed))
            return $input;
        list($accessor, $remaining) = $parsed;
        $expression = (object) array(
            'type' => 'expression',
            'subject' => $accessor,
            'params' => array(),
            'body' => array()
        );
        while (preg_match("/^\s+(.*)/mus", $remaining, $matches)) {
            $remaining = $matches[1];
            if (is_array($parsed = $this->tokenizeAccessor($remaining))) {
                list($param, $remaining) = $parsed;
                $expression->params[] = $param;
            }
            else if (is_array($parsed = $this->tokenizeInteger($remaining))) {
                list($param, $remaining) = $parsed;
                $expression->params[] = $param;
            }
            else if (is_array($parsed = $this->tokenizeString($remaining))) {
                list($param, $remaining) = $parsed;
                $expression->params[] = $param;
            }
            else
                break;
        }
        return array($expression, $remaining);


    }

    /**
     * Tokenize a partial call.
     * @param string $input the input
     *
     * @return string|array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizePartial($input)
    {
        $remaining = trim($input);
        $partial = (object) array(
            'type' => 'partial',
            'name' => null,
            'context' => null,
        );

        if (is_array($parsed = $this->tokenizeString($remaining))) {
            list($item, $remaining) = $parsed;
            $partial->name = $item->value;
        }
        else if (is_array($parsed = $this->tokenizeIdentifierPart($remaining))) {
            list($item, $remaining) = $parsed;
            $partial->name = $item->name;
        }
        else
            return $input;

        $remaining = trim($remaining);
        if (mb_strlen($remaining) && is_array($parsed = $this->tokenizeIdentifierPart($remaining))) {
            list($item, $remaining) = $parsed;
            $partial->context = $item;
        }
        return array($partial, $remaining);
    }


    /**
     * Tokenize a comment.
     * @param string $input the comment to tokenize
     *
     * @return array the input if no match was found, otherwise an
     * array containing the token and any remaining text.
     */
    protected function tokenizeComment($input)
    {
        return array(
            (object) array(
                'type' => 'comment',
                'value' => $input
            ),
            ''
        );
    }

}
