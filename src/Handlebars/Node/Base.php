<?php

namespace Handlebars\Node;

use \Handlebars\Frame;

class Base
{

    /**
     * @var Base|Frame the parent of this node
     */
    public $parent;


    public function getFrame()
    {
        if ($this->parent instanceof Frame)
            return $this->parent;
        else if ($this->parent instanceof Base)
            return $this->parent->getFrame();
        else
            return null;
    }
}
