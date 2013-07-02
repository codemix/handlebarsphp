<?php

namespace Handlebars\Yii;

use Handlebars\Compiler;

/**
 * # Handlebars View Renderer
 *
 * A Yii view renderer for handlebars templates.
 *
 * ## Configuration
 *
 * Configure the component in your application config:
 *
 * <pre>
 * 'components' => array(
 *      'viewRenderer' => array(
 *          'class' => 'Handlebars\Yii\ViewRenderer'
 *      ),
 *   ...
 *  ),
 * </pre>
 *
 * ## Usage
 *
 * Instead of using `.php` view files, create `.handlebars` files and use handlebars syntax.
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars\Yii
 */
class ViewRenderer extends \CViewRenderer
{
    /**
     * @var string the extension name of the view file. Defaults to '.handlebars'.
     */
    public $fileExtension='.handlebars';

    /**
     * @var Compiler the handlebars compiler to use
     */
    protected $_compiler;

    /**
     * @param Compiler $compiler
     */
    public function setCompiler($compiler)
    {
        $this->_compiler = $compiler;
    }

    /**
     * @return Compiler
     */
    public function getCompiler()
    {
        if ($this->_compiler === null) {
            $this->_compiler = new Compiler();
            $this->_compiler->setGenerator(new CodeGenerator());
        }
        return $this->_compiler;
    }

    /**
     * @inheritDoc
     */
    protected function generateViewFile($sourceFile, $viewFile)
    {
        $source = file_get_contents($sourceFile);
        $output = $this->getCompiler()->compile($source);
        file_put_contents($viewFile, $output);
    }

    /**
     * @inheritDoc
     */
    public function renderFile($context, $sourceFile, $data, $return)
    {
        $processed = array();
        foreach($data as $key => $value) {
            if (is_array($value))
                $value = self::castArrayToObject($value);
            $processed[$key] = $value;
        }
        return parent::renderFile($context, $sourceFile, $processed, $return);
    }


    /**
     * Recursively cast an array to an object.
     * @param array $arr the array to cast
     *
     * @return object the object
     */
    public static function castArrayToObject($arr)
    {
        $isNumeric = true;
        foreach($arr as $key => $value) {
            if (is_array($value))
                $arr[$key] = self::castArrayToObject($value);
            $isNumeric = $isNumeric && is_int($key);
        }
        if ($isNumeric)
            return $arr;
        else
            return new \ArrayObject($arr, \ArrayObject::ARRAY_AS_PROPS);
    }

}
