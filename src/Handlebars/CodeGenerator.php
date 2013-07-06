<?php

namespace Handlebars;

/**
 * # Code Generator
 *
 * The code generator is responsible for taking the Abstract Syntax Tree and turning it
 * into executable PHP code.
 *
 * Child classes can customize the generated output by overriding individual `generate` methods.
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars
 */
class CodeGenerator
{
    /**
     * @var string the prefix used when access helpers
     */
    public $helperScopePrefix = '$this->helpers->';


    /**
     * Generates executable code from the given Abstract Syntax Tree.
     *
     * @param Frame $ast the ast to generate code from
     *
     * @return string the generated code
     */
    public function generate(Frame $ast)
    {
        $out = array();
        foreach($ast->children as $child) {
            $out[] = $this->generateNode($child);
        }
        return implode("", $out);
    }

    /**
     * @param Node\Base|Frame $node
     *
     * @return string
     */
    protected function generateNode(Node\Base $node)
    {
        if ($node instanceof Frame)
            return $this->generate($node);
        else if ($node instanceof Node\Content) {
            return $this->generateContent($node);
        }
        else if ($node instanceof Node\Comment) {
            return $this->generateComment($node);
        }
        else if ($node instanceof Node\Block) {
            return $this->generateBlock($node);
        }
        else if ($node instanceof Node\Partial) {
            return $this->generatePartial($node);
        }
        else if ($node instanceof Node\Expression) {
            return $this->generateExpression($node);
        }
        else if ($node instanceof Node\Identifier) {
            return $this->generateIdentifier($node);
        }
        else if ($node instanceof Node\Hash) {
            return $this->generateHash($node);
        }
        else if ($node instanceof Node\Literal) {
            return $this->generateLiteral($node);
        }
        else
            return get_class($node)."\n";
    }

    /**
     * Generates the code for a content node.
     *
     * @param Node\Content $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateContent(Node\Content $node)
    {
        return $node->value;
    }

    /**
     * Generates the code for a comment node.
     *
     * @param Node\Comment $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateComment(Node\Comment $node)
    {
        return '<?php /** '.$node->value.' */ ?>';
    }

    /**
     * Generates the code for a block node.
     *
     * @param Node\Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateBlock(Node\Block $node)
    {
        $name = $node->subject->getName();
        $methodName = 'generate'.$name.'Block';
        if (method_exists($this, $methodName))
            return $this->{$methodName}($node);
        else
            return $this->generateBlockDefault($node);
    }

    /**
     * Generates the code for a generic block node.
     *
     * @param Node\Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateBlockDefault(Node\Block $node)
    {
        $out = array('<?php ob_start(); ?>');
        if ($node->body instanceof Frame)
            $body = $node->body->children;
        else
            $body = $node->body;
        foreach($body as $child)
            $out[] = $this->generateNode($child);

        $name = $node->subject->getName();
        $params = array_map(array($this, "generateNode"), $node->params);

        if (!empty($node->inverse)) {

            if ($node->inverse instanceof Frame)
                $inverse = $node->inverse->children;
            else
                $inverse = $node->inverse;

            if (count($inverse)) {
                $tmpBodyName = $node->getFrame()->createTempVarName();
                $out[] = '<?php '.$tmpBodyName.' = ob_get_clean(); ob_start(); ?>';

                foreach($inverse as $child)
                    $out[] = $this->generateNode($child);


                array_unshift($params, 'array('.$tmpBodyName.', ob_get_clean())');
            }
            else
                array_unshift($params, 'ob_get_clean()');
        }
        else
            array_unshift($params, 'ob_get_clean()');
        $out[] = '<?='.$this->helperScopePrefix.$name.'('.implode(', ', $params).')?>';
        return implode('', $out);
    }

    /**
     * Generates the code for an if block node.
     *
     * @param Node\Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateIfBlock(Node\Block $node)
    {
        $out = array('<?php if (');
        $params = array();
        foreach(array_map(array($this, "generateNode"), $node->params) as $param)
            $params[] = '!empty('.$param.')';

        $out[] = implode(' && ', $params);
        $out[] = '): ?>';
        foreach($node->body as $child)
            $out[] = $this->generateNode($child);
        if ($node->inverse && count($node->inverse)) {
            $out[] = '<?php else: ?>';
            foreach($node->inverse as $child)
                $out[] = $this->generateNode($child);
        }
        $out[] = '<?php endif; ?>';

        return implode('', $out);
    }


    /**
     * Generates the code for an unless block node.
     *
     * @param Node\Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateUnlessBlock(Node\Block $node)
    {
        $out = array('<?php if (');
        $params = array();
        foreach(array_map(array($this, "generateNode"), $node->params) as $param)
            $params[] = 'empty('.$param.')';

        $out[] = implode(' || ', $params);
        $out[] = '): ?>';
        foreach($node->body as $child)
            $out[] = $this->generateNode($child);
        if ($node->inverse && count($node->inverse)) {
            $out[] = '<?php else: ?>';
            foreach($node->inverse as $child)
                $out[] = $this->generateNode($child);
        }
        $out[] = '<?php endif; ?>';

        return implode('', $out);
    }

    /**
     * Generates the code for an each block node.
     *
     * @param Node\Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateEachBlock(Node\Block $node)
    {
        $param = $this->generateNode($node->params[0]);
        $body = $node->body;
        $out = array(
            '<?php if (!empty('.$param.')): foreach('.$param.' as $'.$body->getIteratorName().' => $'.$body->getName().'): ?>'
        );
        foreach($body->children as $child) {
            $out[] = $this->generateNode($child);
        }

        $out[] = '<?php endforeach; endif; ?>';

        return implode('', $out);
    }

    /**
     * Generates the code for a partial node.
     *
     * @param Node\Partial $node the node to generate code for
     *
     * @throws Exception if the node is invalid
     * @return string the generated code
     */
    protected function generatePartial(Node\Partial $node)
    {
        $out = '<?=$this->partial(';
        if ($node->name instanceof Node\Literal)
            $out .= $node->name->value;
        else if ($node->name instanceof Node\Identifier)
            $out .= '"'.$node->name->getName().'"';
        else
            throw new Exception("Expected literal or id, got ".get_class($node));

        if (!empty($node->context)) {
            $out .= ', '.$this->generateNode($node->context);
        }
        $out .= ')?>';
        return $out;
    }

    /**
     * Generates the code for an expression node.
     *
     * @param Node\Expression $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateExpression(Node\Expression $node)
    {
        if (count($node->params))
            return $this->generateCallExpression($node);

        if ($node->subject instanceof Node\Data) {
            $subject = $this->generateDataIdentifier($node->subject);
            if (!$node->unescaped)
                $out = $this->wrapEncode($subject);
            else
                $out = $subject;
            return '<?='.$out.'?>';
        }
        else {
            $subject = $this->generateIdentifier($node->subject);
            $out = '<?=(isset('.$subject.') ? ';
        }
        if (!$node->unescaped)
            $out .= $this->wrapEncode($subject);
        else
            $out .= $subject;

        $out .= ' : \'\')?>';
        return $out;
    }

    /**
     * Generates the code for a call expression node.
     *
     * @param Node\Expression $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateCallExpression(Node\Expression $node)
    {
        $subject = $this->generateIdentifier($node->subject, true);
        $params = array_map(array($this, "generateNode"), $node->params);
        $out = $subject.'('.implode(', ', $params).')';
        if (!$node->unescaped)
            $out = $this->wrapEncode($out);
        return '<?='.$out.'?>';
    }

    /**
     * Generates the code for a hash node.
     *
     * @param Node\Hash $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateHash(Node\Hash $node)
    {
        $out = 'array(';
        $out .= implode(', ', array_map(array($this, 'generateProperty'), $node->properties));
        $out .= ')';
        return $out;
    }

    /**
     * Generates the code for a property node.
     *
     * @param Node\Property $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateProperty(Node\Property $node)
    {
       return '"'.$node->key->getName().'" => '.$this->generateNode($node->value);
    }


    /**
     * Generates the code for a literal node.
     *
     * @param Node\Literal $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateLiteral(Node\Literal $node)
    {
        return $node->value;
    }


    /**
     * Generates the code for an identifier node.
     *
     * @param Node\Identifier $node the node to generate code for
     * @param bool $isHelper whether or not this is a helper
     *
     * @return string the generated code
     */
    protected function generateIdentifier(Node\Identifier $node, $isHelper = false)
    {
        if ($node instanceof Node\Data)
            return $this->generateDataIdentifier($node);
        $out = array();
        if ($isHelper) {
            $out[] = $this->helperScopePrefix;
        }
        $hasContext = false;
        foreach($node->resolve() as $part) {
            if ($part instanceof Token) {
                if ($part->type === Token::ID) {
                    if (!empty($part->options['quoted']))
                        $out[] = '["'.$part->value.'"]';
                    else if (!$hasContext) {
                        $hasContext = true;
                        $out[] = $part->value;
                    }
                    else
                        $out[] = '->'.$part->value;
                }
                else if ($part->type === Token::INTEGER)
                    $out[] = '['.$part->value.']';
                else
                    $out[] = '['.$part->value.']';
            }
            else if (!$hasContext) {
                $out[] = $part;
                $hasContext = true;
            }
            else
                $out[] = '->'.$part;

        }

        if ($isHelper)
            return implode('', $out);
        else
            return '$'.implode('', $out);
    }

    /**
     * Generates the code for a data identifier node.
     *
     * @param \Handlebars\Node\Data $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateDataIdentifier(Node\Data $node)
    {
        return '$'.$node->getFrame()->getIteratorName();
    }

    /**
     * Wrap the given code in a HTML encode function.
     * @param string $code the code to encode.
     *
     * @return string the wrapped code
     */
    protected function wrapEncode($code)
    {
        return '$this->encode('.$code.')';
    }
}
