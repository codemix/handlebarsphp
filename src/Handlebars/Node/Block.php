<?php

namespace Handlebars\Node;

use \Handlebars\Frame;

class Block extends Expression
{

    /**
     * @var Frame|Base[] the block content
     */
    public $body = array();

    /**
     * @var Frame|Base[] the inverse block content
     */
    public $inverse = array();

    /**
     * @var Identifier the identifier that ends this block
     */
    public $ender;
}
