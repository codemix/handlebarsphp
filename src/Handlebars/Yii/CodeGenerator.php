<?php

namespace Handlebars\Yii;

/**
 * Handlebars template code generator for Yii 1.x projects.
 *
 * Overrides the default code generator to use Yii's built in methods where possible.
 *
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars\Yii
 */
class CodeGenerator extends \Handlebars\CodeGenerator
{
    /**
     * @inheritDoc
     */
    protected function wrapEncode($code)
    {
        return '\CHtml::encode('.$code.')';
    }

    /**
     * @inheritDoc
     */
    protected function generatePartial($token)
    {
        if ($token->context === null)
            $context = '$'.$token->scopeName;
        else
            $context = $this->generateIdentifier($token->context);
        return "<?php \$this->renderPartial('{$token->name}', {$context}); ?>";
    }

    /**
     * Generates the source code for a 'content' block.
     * This turns into `$this->beginContent(..)` and `$this->endContent()` calls.
     *
     * @param object $token the token to generate code for
     *
     * @return string the generated code
     */
    protected function generateContentBlock($token)
    {
        $out = array();
        $params = array();
        foreach($token->start->params as $param)
            $params[] = $this->generateToken($param);
        $out[] = "<?php \$this->beginContent(".implode(', ', $params)."); ?>";
        $out[] = $this->generate($token);
        $out[] = '<?php $this->endContent(); ?>';
        return implode('', $out);
    }


}
