<?php

namespace Handlebars\Node;

class Property extends Base
{
    /**
     * @var Identifier the property key
     */
    public $key;

    /**
     * @var Base the property value
     */
    public $value;
}
