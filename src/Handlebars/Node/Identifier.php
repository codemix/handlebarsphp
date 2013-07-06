<?php

namespace Handlebars\Node;

use Handlebars\Exception;
use Handlebars\Token;

class Identifier extends Base
{

    /**
     * @var Token[] the identifier parts
     */
    public $parts = array();


    /**
     * Gets the simple name of the identifier
     * @return string
     */
    public function getName()
    {
        return implode(".", array_map(function(Token $part) { return $part->value; }, $this->parts));
    }

    /**
     * Resolves the identifier parts
     * 
     * @throws \Handlebars\Exception if the
     * @return Token[]|string[] the resolved parts
     */
    public function resolve()
    {
        $frame = $this->getFrame();
        $context = $frame;
        $parts = array();
        $hasContext = false;
        foreach($this->parts as $part) {
            if ($part->type === Token::ID) {
                if ($part->value === '.' || $part->value === 'this') {
                    $parts = array($context->getName());
                    $hasContext = true;
                }
                if ($part->value === '$') {
                    $parts = array('this');
                    $hasContext = true;
                }
                else if ($part->value === '..') {
                    $context = $context->parent;
                    if (!is_object($context))
                        throw new Exception("Identifier points to a parent out of reach (too deep).");
                    $parts = array($context->getName());
                    $hasContext = true;
                }
                else if (!$hasContext) {
                    $hasContext = true;
                    $contextName = $context->getName();
                    if (!empty($contextName))
                        $parts = array(
                            $contextName,
                            $part,
                        );
                    else
                        $parts = array(
                            $part
                        );
                }
                else
                    $parts[] = $part;
            }
            else
                $parts[] = $part;
        }
        return $parts;
    }
}
