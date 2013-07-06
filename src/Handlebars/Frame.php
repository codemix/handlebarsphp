<?php

namespace Handlebars;

/**
 * # Frame
 *
 * Represents a parser frame.
 *
 * @author Charles Pick <charles@codemix.com>
 * @license MIT
 * @package Handlebars
 */
class Frame
{
    /**
     * @var Frame the parent frame
     */
    public $parent;

    /**
     * @var Node\Base[]|Frame[] the contents of the frame
     */
    public $children = array();

    /**
     * @var array the names of the frames to use
     */
    public static $frameNames = array(
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
     * @var int the number of allocated temporary variables for this frame
     */
    protected $tmpCount = 0;

    /**
     * Create a unique temporary variable name for this frame
     * @return string the temporary variable name, with leading '$'
     */
    public function createTempVarName()
    {
        $name = '$__tmp'.ucfirst($this->getName()).'_'.chr(65 + $this->tmpCount);
        $this->tmpCount++;
        return $name;
    }

    /**
     * @return string the name of this frame
     */
    public function getName()
    {
        $depth = $this->getDepth();
        $names = array_values(static::$frameNames);
        return $names[$depth];
    }

    /**
     * @return string the name of the iterator for this frame
     */
    public function getIteratorName()
    {
        $depth = $this->getDepth();
        $names = array_keys(self::$frameNames);
        return $names[$depth];
    }

    /**
     * @return int the frame depth
     */
    public function getDepth()
    {
        if (is_object($this->parent))
            $depth = $this->parent->getDepth() + 1;
        else
            $depth = 0;

        return $depth;
    }

    /**
     * Adds a child node or frame
     * @param Node\Base|Frame $child the child to add
     *
     * @return $this the frame with the child added
     */
    public function addChild($child)
    {
        $child->parent = $this;
        $this->children[] = $child;
        return $this;
    }

    public function resolve()
    {

    }
}
