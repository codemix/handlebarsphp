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
     * Generates executable code from the given Abstract Syntax Tree.
     *
     * @param string $ast the ast to generate code from
     *
     * @return string the generated code
     */
    public function generate($ast)
    {
        $out = array();
        foreach($ast->body as $token)
            $out[] = $this->generateToken($token);
        return implode("", $out);
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

    /**
     * Generate the source code for the given token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateToken($token)
    {
        if (is_string($token)) {
            return $token;
        }
        $methodName = 'generate'.$token->type;
        if (method_exists($this, $methodName))
            return $this->{$methodName}($token);
        else
            return '';
    }

    /**
     * Generate the source code for the given `comment` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateComment($token)
    {
        $lines = array(
            '<?php',
            '/**'
        );
        foreach(preg_split("/(\r\n|\n\r|\r|\n)/", trim($token->value)) as $line) {
            $line = trim($line);
            if ($line === '')
                continue;
            $lines[] = ' * '.$line;
        }
        $lines[] = ' */';
        $lines[] = '?>';
        return implode("\n", $lines);

    }

    /**
     * Generate the source code for the given `block` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateBlock($token)
    {
        $methodName = 'generate'.$token->start->subject->name.'Block';
        if (method_exists($this, $methodName))
            return $this->{$methodName}($token);
        else
            return $this->generateCustomBlock($token);
    }

    /**
     * Generate the source code for the given `partial` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generatePartial($token)
    {
        if ($token->context === null)
            $context = '$'.$token->scopeName;
        else
            $context = $this->generateIdentifier($token->context);
        return "<?php \$this->partial('{$token->name}', {$context}); ?>";
    }

    /**
     * Generate the source code for the given `if` block token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateIfBlock($token)
    {
        $out = array();
        $out[] = "<?php if(!empty(".$this->generateIdentifier($token->start->params[0]).")): ?>";
        $out[] = $this->generate($token);
        $out[] = "<?php endif; ?>";
        return implode('', $out);
    }

    /**
     * Generate the source code for the given `unless` block token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateUnlessBlock($token)
    {
        $out = array();
        $out[] = "<?php if(empty(".$this->generateIdentifier($token->start->params[0]).")): ?>";
        $out[] = $this->generate($token);
        $out[] = "<?php endif; ?>";
        return implode('', $out);
    }

    /**
     * Generate the source code for the given `each` block token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateEachBlock($token)
    {
        $out = array();
        if (isset($token->start->params[0]))
            $identifier = $this->generateIdentifier($token->start->params[0]);
        else
            $identifier = '$this';
        $out[] = "<?php if (isset(".$identifier.")): foreach(".$identifier." as \${$token->iteratorKey} => \${$token->iteratorName}): ?>";
        $out[] = $this->generate($token);
        $out[] = "<?php endforeach; endif; ?>";
        return implode('', $out);
    }

    /**
     * Generate the source code for the given `with` block token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateWithBlock($token)
    {
        $out = array();
        if (isset($token->start->params[0]))
            $identifier = $this->generateIdentifier($token->start->params[0]);
        else
            $identifier = '$this';
        $out[] = "<?php if (isset(".$identifier.")): \${$token->iteratorName} = $identifier;?>";
        $out[] = $this->generate($token);
        $out[] = "<?php endif; ?>";
        return implode('', $out);
    }

    /**
     * Generate the source code for the given custom block token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateCustomBlock($token)
    {
        $out = array();
        $params = array();
        foreach($token->start->params as $param)
            $params[] = $this->generateToken($param);
        $params[] = 'ob_get_clean()';
        $out[] = '<?php ob_start(); ?>';
        $out[] = $this->generate($token);
        $out[] = "<?=\$this->{$token->start->subject->name}(".implode(', ', $params).")?>";
        return implode('', $out);
    }

    /**
     * Generate the source code for the given `else` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateElseExpression($token)
    {
        return "<?php else: ?>";
    }

    /**
     * Generate the source code for the given `identifier` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateIdentifier($token)
    {
        if ($token->type == "parentAccessor")
            $out = array('$'.$token->name);
        elseif ($token->name == "this")
            $out = array('$'.$token->scopeName);
        elseif ($token->name == '$')
            $out = array('$this');
        elseif (!empty($token->scopeName))
            $out = array('$'.$token->scopeName.'->'.$token->name);
        else
            $out = array('$'.$token->name);
        foreach($token->accessors as $accessor) {
            if ($accessor->type == 'integer')
                $out[] = '['.$accessor->value.']';
            else
                $out[] = '->'.$accessor->name;
        }
        return implode('', $out);
    }

    /**
     * Generate the source code for the given `expression` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateExpression($token)
    {
        if (count($token->params))
            return $this->generateCallExpression($token);
        else if ($token->subject->name == "else")
            return $this->generateElseExpression($token);
        else if ($token->raw) {
            $c = $this->generateIdentifier($token->subject);
            return "<?=empty($c) ? '' : $c ?>";
        }
        else {
            $c = $this->generateIdentifier($token->subject);
            return "<?=empty($c) ? '' : ".$this->wrapEncode($c)."?>";
        }

    }

    /**
     * Generate the source code for the given `reference` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateReference($token)
    {
        if ($token->raw) {
            return "<?=\${$token->scopeKey}?>";
        }
        else {
            return "<?=".$this->wrapEncode("\${$token->scopeKey}")."?>";
        }
    }

    /**
     * Generate the source code for the given `integer` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateInteger($token)
    {
        return $token->value;
    }

    /**
     * Generate the source code for the given `string` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateString($token)
    {
        return $token->delimiter.$token->value.$token->delimiter;
    }

    /**
     * Generate the source code for the given `call` token
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateCallExpression($token)
    {
        $params = array();
        foreach($token->params as $param) {
            $params[] = $this->generateToken($param);
        }
        $tempScope = $token->subject->scopeName;
        $token->subject->scopeName = 'this';
        $subject = $this->generateIdentifier($token->subject);
        $token->subject->scopeName = $tempScope;
        $code = $subject.'('.implode(', ', $params).')';
        if (!empty($token->raw))
            return '<?='.$code.'?>';
        else
            return '<?='.$this->wrapEncode($code).'?>';
    }
}
