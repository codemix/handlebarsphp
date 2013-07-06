<?php

namespace Handlebars\Node;

class Expression extends Base
{

    /**
     * @var Identifier the expression subject
     */
    public $subject;

    /**
     * @var Base[] the parameters of the expression
     */
    public $params = array();

    /**
     * @var bool whether or not the result of the expression should be unescaped
     */
    public $unescaped = false;
}
