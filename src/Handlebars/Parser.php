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
