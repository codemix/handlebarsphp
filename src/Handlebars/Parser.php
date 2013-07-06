<?php

namespace Handlebars;
/**
 * # Parser
 *
 * A simple parser for handlebars templates, consumes tokens produced by the `Tokenizer` and
 * produces a simple Abstract Syntax Tree.
 *
 * Usage:
 * <pre>
 * $parser = new Handlebars\Parser();
 * $ast = $parser->parse("Hello, {{name}}, how are you?");
 * print_r($ast);
 * </pre>
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars
 */
class Parser
{
    /**
     * The names of the iterators and their contexts to use when parsing.
     * @var array
     */
    public $iteratorNames = array(
        '_' => '',
        'i' => 'data1',
        'j' => 'data2',
        'k' => 'data3',
        'm' => 'data4',
        'n' => 'data5',
        'o' => 'data6',
        'p' => 'data7',
        'q' => 'data8',
        'r' => 'data9',
        's' => 'data10',
        't' => 'data11',
        'u' => 'data12',
        'v' => 'data13',
        'w' => 'data14',
        'x' => 'data15',
        'y' => 'data16',
        'z' => 'data17',
    );
    /**
     * @var Tokenizer the tokenizer for this parser
     */
    protected $tokenizer;

    /**
     * @param \Handlebars\Tokenizer $tokenizer
     */
    public function setTokenizer($tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * @return \Handlebars\Tokenizer
     */
    public function getTokenizer()
    {
        if ($this->tokenizer === null)
            $this->tokenizer = new Tokenizer();
        return $this->tokenizer;
    }


    /**
     * Parse the given input and return an abstract syntax tree
     *
     * @param string $input the input to tokenize and parse
     *
     * @return object the abstract syntax tree
     */
    public function parse($input)
    {
        $ast = new Frame();
        $frame = $ast;
        $context = null;
        $tokens = $this->getTokenizer()->tokenize($input);
        while ($token = array_shift($tokens) /* @var Token $token */) {
            if (($result = $this->parseToken($frame, $token, $tokens)) !== false) {
                list($node, $tokens) = $result;
            }
            else {
                $node = new Node\Invalid();
                $node->parts[] = $token;
                $node->reason = "Unexpected Token: ".$token->type;
            }

            $frame->addChild($node);
        }
        return $ast;
    }

    /**
     * Attempts to parse a token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseToken(Frame $frame, Token $head, $tail)
    {
        if (($result = $this->parseContent($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseComment($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseOpenPartial($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseOpenBlock($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseOpenUnescaped($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseOpen($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseIdentifier($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else if (($result = $this->parseLiteral($frame, $head, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else
            return false;
        return array($node, $tail);
    }

    /**
     * Expect a certain sequence of token types and return them, or throw an exception if the
     * tokens don't match the given sequence.
     *
     * @param string[] $expected the expected token types
     * @param Token[] $tokens the tokens to inspect
     *
     * @throws Exception if there is an error
     * @return Token[] an array of matched tokens
     */
    protected function expect($expected, $tokens)
    {
        $matched = array();
        foreach($expected as $i => $type) {
            if (!isset($tokens[$i]))
                throw new Exception('Unexpected end of input, expected '.$type);
            elseif ($tokens[$i]->type != $type)
                throw new Exception('Expected '.$type.' got '.$tokens[$i]->type);
            else
                $matched[] = $tokens[$i];
        }
        array_splice($tokens, 0, count($expected));
        return array($matched, $tokens);
    }

    /**
     * Consume a certain sequence of token types and return them, return false if the
     * tokens don't match the given sequence.
     *
     * @param string[] $expected the expected token types
     * @param Token[] $tokens the tokens to inspect
     *
     * @return Token[]|bool an array of matched tokens or false
     */
    protected function consume($expected, $tokens)
    {
        $matched = array();
        foreach($expected as $i => $type) {
            if (!isset($tokens[$i]))
                return false;
            elseif ($tokens[$i]->type != $type)
                return false;
            else
                $matched[] = $tokens[$i];
        }
        array_splice($tokens, 0, count($expected));
        return array($matched, $tokens);
    }

    /**
     * Attempts to parse a content token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseContent(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::CONTENT)
            return false;
        $node = new Node\Content();
        $node->value = $head->value;
        return array($node, $tail);
    }

    /**
     * Attempts to parse a comment token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseComment(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN_COMMENT)
            return false;
        list($tokens, $tail) = $this->expect(array(Token::COMMENT, Token::CLOSE), $tail);

        $node = new Node\Comment();
        $node->value = $tokens[0]->value;
        return array($node, $tail);
    }


    /**
     * Attempts to parse an open inverse token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the nodes and the remaining unparsed tokens.
     */
    protected function parseInverse(Frame $frame, Token $head, $tail)
    {
        array_unshift($tail, $head);
        if (($result = $this->consume(array(Token::OPEN, Token::INVERSE, Token::CLOSE), $tail)) === false)
            return false;
        list($tokens, $tail) = $result;
        $next = array_shift($tail);
        if (($result = $this->parseBlockBody($frame, $next, $tail)) !== false) {
            list($body, $tail) = $result;
        }
        else
            $body = array();

        return array($body, $tail);
    }
    /**
     * Attempts to parse an open token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseOpen(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN)
            return false;
        $next = array_shift($tail);
        if ($next->type === Token::INVERSE)
            return false;
        if (($result = $this->parseExpression($frame, $next, $tail)) === false)
            throw new Exception('No expression in tag');

        list($node, $tail) = $result;

        list($closer, $tail) = $this->expect(array(Token::CLOSE), $tail);

        return array($node, $tail);
    }

    /**
     * Attempts to parse an open unescaped token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseOpenUnescaped(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN_UNESCAPED)
            return false;

        $next = array_shift($tail);
        if (($result = $this->parseExpression($frame, $next, $tail)) === false)
            throw new Exception('No expression in tag');

        list($node, $tail) = $result; /* @var Node\Expression $node */
        $node->unescaped = true;
        list($closer, $tail) = $this->expect(array(Token::CLOSE_UNESCAPED), $tail);

        return array($node, $tail);
    }

    /**
     * Attempts to parse an open partial token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseOpenPartial(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN_PARTIAL)
            return false;
        $node = new Node\Partial();
        $next = array_shift($tail); /* @var Token $next */
        if (($result = $this->parseIdentifier($frame, $next, $tail)) !== false) {
            list($child, $tail) = $result;
            $node->name = $child;
            $child->parent = $node;
        }
        else if (($result = $this->parseLiteral($frame, $next, $tail)) !== false) {
            list($child, $tail) = $result;
            $node->name = $child;
        }
        else
            throw new Exception('Unexpected token '.$next->type.', expected identifier or literal');


        // see if there is a context

        $next = array_shift($tail); /* @var Token $next */
        if (($result = $this->parseIdentifier($frame, $next, $tail)) !== false) {
            list($child, $tail) = $result;
            $node->context = $child;
            $child->parent = $node;
        }
        else
            array_unshift($tail, $next);

        list($closers, $tail) = $this->expect(array(Token::CLOSE), $tail);

        return array($node, $tail);
    }


    /**
     * Attempts to parse an open block token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseOpenBlock(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN_BLOCK)
            return false;
        $node = new Node\Block();

        // first, grab the subject, it should be an identifier
        $next = array_shift($tail);
        if (($result = $this->parseIdentifier($frame, $next, $tail)) !== false) {
            list($subject, $tail) = $result;
            $node->subject = $subject;
            $subject->parent = $node;
        }
        else
            throw new Exception("No identifier for block");

        // now look for some params
        $next = array_shift($tail);
        if (($result = $this->parseParams($frame, $next, $tail)) !== false) {
            list($params, $tail) = $result;
            $node->params = $params;
            foreach($node->params as $param)
                $param->parent = $node;
        }
        else
            array_unshift($tail, $next);

        // the next token should close the tag `}}`
        list($closer, $tail) = $this->expect(array(Token::CLOSE), $tail);

        // now parse the block body
        $next = array_shift($tail);
        if ($this->requiresNewFrame($node)) {
            $node->body = new Frame();
            $node->body->parent = $frame;
            if (($result = $this->parseBlockBody($node->body, $next, $tail)) !== false) {
                list($children, $tail) = $result;
                $next = array_shift($tail);
                foreach($children as $child)
                    $node->body->addChild($child);
            }
        }
        else if (($result = $this->parseBlockBody($frame, $next, $tail)) !== false) {
            list($children, $tail) = $result;
            $node->body = $children;
            foreach($node->body as $child)
                $child->parent = $node;
            $next = array_shift($tail);
        }

        // we should now expect an inverse or end block token

        if ($this->requiresNewFrame($node)) {
            $node->inverse = new Frame();
            $node->inverse->parent = $frame;
            if (($result = $this->parseInverse($node->inverse, $next, $tail)) !== false) {
                list($inverse, $tail) = $result;
                $next = array_shift($tail);
                foreach($inverse as $child)
                    $node->inverse->addChild($child);
            }
        }
        elseif (($result = $this->parseInverse($frame, $next, $tail)) !== false) {
            list($inverse, $tail) = $result;
            $next = array_shift($tail);
            $node->inverse = $inverse;
            foreach($node->inverse as $child)
                $child->parent = $node;
        }

        if (($result = $this->parseOpenEndBlock($frame, $next, $tail)) === false)
           throw new Exception('Expected openEndBlock, got '.$next->type);

        list($ender, $tail) = $result;
        $node->ender = $ender;

        // and done.
        return array($node, $tail);
    }


    /**
     * Attempts to parse an expression token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseExpression(Frame $frame, Token $head, $tail)
    {


        // first, grab the subject, it should be an identifier
        if (($result = $this->parseIdentifier($frame, $head, $tail)) === false)
            return false;

        $node = new Node\Expression();
        list($subject, $tail) = $result;
        $node->subject = $subject;
        $subject->parent = $node;
        // now look for some params
        $next = array_shift($tail);
        if (($result = $this->parseParams($frame, $next, $tail)) !== false) {
            list($params, $tail) = $result;
            $node->params = $params;
            foreach($node->params as $param)
                $param->parent = $node;
        }
        else
            array_unshift($tail, $next);

        return array($node, $tail);
    }


    /**
     * Attempts to parse the body of the block
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the nodes and the remaining unparsed tokens.
     */
    protected function parseBlockBody(Frame $frame, Token $head, $tail)
    {
        $nodes = array();
        array_unshift($tail, $head);
        while($head = array_shift($tail)) {
            if (($result = $this->parseToken($frame, $head, $tail))) {
                list($node, $tail) = $result;
                $nodes[] = $node;
            }
            else {
                array_unshift($tail, $head);
                break;
            }
        }

        return array($nodes, $tail);
    }

    /**
     * Attempts to parse an open end block token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid.
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseOpenEndBlock(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::OPEN_END_BLOCK)
            return false;


        $next = array_shift($tail);
        if (($result = $this->parseIdentifier($frame, $next, $tail)) !== false) {
            list($node, $tail) = $result;
        }
        else
            throw new Exception('Expected identifier, got'.$next->type);

        list($tokens, $tail) = $this->expect(array(Token::CLOSE), $tail);
        return array($node, $tail);
    }

    /**
     * Determines whether or not the given block requires a new parser frame.
     * By default, only `each` and `with` blocks require new frames.
     *
     * @param Node\Block $node the block
     *
     * @return bool true if the block requires a frame, otherwise false.
     */
    protected function requiresNewFrame(Node\Block $node)
    {
        $name = $node->subject->getName();
        return $name == "each" || $name == "with";
    }

    /**
     * Attempts to parse an open partial token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the nodes and the remaining unparsed tokens.
     */
    protected function parseParams(Frame $frame, Token $head, $tail)
    {
        $nodes = array();
        while($head->type === Token::BOOLEAN || $head->type === Token::INTEGER || $head->type === Token::STRING || $head->type === Token::DATA || $head->type === Token::ID) {
            if (($result = $this->parseHash($frame, $head, $tail)) !== false) {
                list($node, $tail) = $result;
                $head = array_shift($tail);
                $nodes[] = $node;
            }
            elseif (($result = $this->parseIdentifier($frame, $head, $tail)) !== false) {
                list($node, $tail) = $result;
                $head = array_shift($tail);
                $nodes[] = $node;
            }
            else if (($result = $this->parseLiteral($frame, $head, $tail)) !== false) {
                list($node, $tail) = $result;
                $head = array_shift($tail);
                $nodes[] = $node;
            }
            else {
                break;
            }
        }
        array_unshift($tail, $head);
        return array($nodes, $tail);
    }

    /**
     * Attempts to parse a series of property tokens.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid.
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseHash(Frame $frame, Token $head, $tail)
    {
        $node = new Node\Hash();
        $properties = array();
        while(($result = $this->parseProperty($frame, $head, $tail)) !== false) {
            list($property, $tail) = $result;
            $head = array_shift($tail);
            $property->parent = $node;
            $properties[] = $property;
        }
        if (!count($properties))
            return false;
        array_unshift($tail, $head);

        $node->properties = $properties;
        return array($node, $tail);
    }

    /**
     * Attempts to parse a property token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @throws Exception if the sequence is invalid.
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseProperty(Frame $frame, Token $head, $tail)
    {
        if (($result = $this->parseIdentifier($frame, $head, $tail)) === false)
            return false;
        list($key, $tail) = $result;
        $next = array_shift($tail);
        if ($next->type !== Token::EQUALS)
            return false;
        $next = array_shift($tail);
        if (($result = $this->parseToken($frame, $next, $tail)) === false)
            throw new Exception('Expected literal or identifier, got '.$next->type);
        list($value, $tail) = $result;

        $node = new Node\Property();
        $key->parent = $node;
        $node->key = $key;
        $value->parent = $node;
        $node->value = $value;

        return array($node, $tail);
    }


    /**
     * Attempts to parse an identifier token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseIdentifier(Frame $frame, Token $head, $tail)
    {
        if ($head->type === Token::DATA)
            return $this->parseData($frame, $head, $tail);
        if ($head->type !== Token::ID)
            return false;
        $node = new Node\Identifier();
        $node->parts[] = $head;
        $expectSep = true;
        while (true) {
            $next = array_shift($tail); /* @var Token $next */
            if ($expectSep) {
                $expectSep = false;
                if ($next->type !== Token::SEP) {
                    array_unshift($tail, $next);
                    break;
                }
            }
            elseif ($next->type === Token::ID) {
                $node->parts[] = $next;
                $expectSep = true;
            }
            elseif ($next->type === Token::INTEGER) {
                $node->parts[] = $next;
                $expectSep = true;
            }
            else {
                array_unshift($tail, $next);
                break;
            }
        }

        return array($node, $tail);
    }

    /**
     * Attempts to parse an data token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseData(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::DATA)
            return false;
        list($tokens, $tail) = $this->expect(array(Token::ID), $tail);
        $head = $tokens[0];
        $node = new Node\Data();
        $node->parts[] = $head;
        $expectSep = true;
        while (true) {
            $next = array_shift($tail); /* @var Token $next */
            if ($expectSep) {
                $expectSep = false;
                if ($next->type !== Token::SEP) {
                    array_unshift($tail, $next);
                    break;
                }
            }
            elseif ($next->type === Token::ID) {
                $node->parts[] = $next;
                $expectSep = true;
            }
            else {
                array_unshift($tail, $next);
                break;
            }
        }

        return array($node, $tail);
    }

    /**
     * Attempts to parse a literal token.
     *
     * @param Frame $frame the current frame
     * @param Token $head the token to parse
     * @param Token[] $tail the remaining unparsed tokens
     *
     * @return array|bool false if the token doesn't match, otherwise an
     * array containing the node and the remaining unparsed tokens.
     */
    protected function parseLiteral(Frame $frame, Token $head, $tail)
    {
        if ($head->type !== Token::STRING
            && $head->type !== Token::INTEGER
            && $head->type !== Token::BOOLEAN
        )
            return false;

        $node = new Node\Literal();
        $node->type = $head->type;
        if ($head->type === Token::STRING && isset($head->options['delimiter']))
            $node->value = $head->options['delimiter'].$head->value.$head->options['delimiter'];
        else
            $node->value = $head->value;
        return array($node, $tail);

    }

    /**
     * Parse the given input and return an abstract syntax tree
     *
     * @param string $input the input to tokenize and parse
     *
     * @return object the abstract syntax tree
     */
    public function parse2($input)
    {
        $iteratorData = $this->iteratorNames;
        $iteratorNames = array_keys($iteratorData);
        $scope = array(array('_', $iteratorData['_']));
        $scopeDepth = 0;
        $ast = (object) array('body' => array());
        $context = $ast;
        $stack = array($context);
        foreach($this->getTokenizer()->tokenize($input) as $token) {
            if (is_string($token)) {
                $context->body[] = $token;
                continue;
            }
            switch($token->type) {
                case "startBlock";
                    $block = (object) array(
                        'type' => 'block',
                        'start' => $token,
                        'body' => array(),
                        'end' => null,
                    );
                    if ($token->subject->name == "each" || $token->subject->name == "with") {

                        $block->scopeKey = $iteratorNames[$scopeDepth];
                        $block->scopeName = $iteratorData[$iteratorNames[$scopeDepth]];
                        $scopeDepth++;
                        $scope[] = array($iteratorNames[$scopeDepth], $iteratorData[$iteratorNames[$scopeDepth]]);
                        $block->iteratorKey = $iteratorNames[$scopeDepth];
                        $block->iteratorName = $iteratorData[$iteratorNames[$scopeDepth]];
                    }
                    else {
                        $scope[] = array($iteratorNames[$scopeDepth], $iteratorData[$iteratorNames[$scopeDepth]]);
                        $block->scopeKey = $iteratorNames[$scopeDepth];
                        $block->scopeName = $iteratorData[$iteratorNames[$scopeDepth]];
                    }
                    $stack[] = $context;
                    $context->body[] = $block;
                    $context = $block;
                    $token = $block;
                    break;
                case "endBlock";
                    if ($token->subject->name == "each" || $token->subject->name == "with") {
                        array_pop($scope);
                        $scopeDepth--;
                    }
                    $context->end = $token;
                    $context = array_pop($stack);
                    break;
                default;
                    $context->body[] = $token;
            }
            $this->resolveToken($token, $scope);
        }
        return $ast;
    }

    /**
     * Resolve the correct scope / iterators for the given token
     * @param object $token the token to resolve
     * @param array $scope the scope stack
     */
    protected function resolveToken($token, $scope)
    {
        list($scopeKey, $scopeName) = $scope[count($scope) - 1];
        switch($token->type) {
            case "block";
                array_pop($scope);
                list($scopeKey, $scopeName) = $scope[count($scope) - 1];
                $token->scopeKey = $scopeKey;
                $token->scopeName = $scopeName;
                $this->resolveToken($token->start->subject, $scope);
                foreach($token->start->params as $child)
                    $this->resolveToken($child, $scope);
                break;
            case "partial";
                $token->scopeKey = $scopeKey;
                $token->scopeName = $scopeName;
                if ($token->context !== null)
                    $this->resolveToken($token->context, $scope);
                break;
            case "expression";
            case "startBlock";
                $token->scopeKey = $scopeKey;
                $token->scopeName = $scopeName;
                $this->resolveToken($token->subject, $scope);
                foreach($token->params as $child)
                    $this->resolveToken($child, $scope);
                break;
            case "identifier";
                $token->scopeKey = $scopeKey;
                $token->scopeName = $scopeName;
                break;
            case "parentAccessor";
                for($i = 0; $i < $token->depth; $i++) {
                    array_pop($scope);
                    list($scopeKey, $scopeName) = $scope[count($scope) - 1];
                }
                $token->name = $scopeName;
                break;
            default;
                $token->scopeKey = $scopeKey;
                $token->scopeName = $scopeName;
        }
    }


}
