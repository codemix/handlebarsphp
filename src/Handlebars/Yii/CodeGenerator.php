<?php

namespace Handlebars\Yii;
use Handlebars\Exception;
use Handlebars\Node\Block;
use Handlebars\Node\Identifier;
use Handlebars\Node\Literal;
use Handlebars\Node\Partial;


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
     * @var string the prefix used when access helpers
     */
    public $helperScopePrefix = '$this->viewHelper->';

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
    protected function generatePartial(Partial $node)
    {
        $out = '<?=$this->renderPartial(';
        if ($node->name instanceof Literal)
            $out .= $node->name->value;
        else if ($node->name instanceof Identifier)
            $out .= '"'.$node->name->getName().'"';
        else
            throw new Exception("Expected literal or id, got ".get_class($node));

        if (!empty($node->context)) {
            $out .= ', '.$this->generateNode($node->context);
        }
        else
            $out .= ', array()';
        $out .= ', true)?>';
        return $out;
    }

    /**
     * Generates the code for an if block node.
     *
     * @param Block $node the node to generate code for
     *
     * @return string the generated code
     */
    protected function generateContentBlock(Block $node)
    {
        $out = array();
        $params = array_map(array($this, "generateNode"), $node->params);
        $out[] = "<?php \$this->beginContent(".implode(', ', $params)."); ?>";
        foreach($node->body as $child)
            $out[] = $this->generateNode($child);
        $out[] = '<?php $this->endContent(); ?>';
        return implode('', $out);
    }


}
