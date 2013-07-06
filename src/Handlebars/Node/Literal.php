<?php

namespace Handlebars\Node;

class Literal extends Base
{
    const STRING = 'string';
    const INTEGER = 'integer';
    const BOOLEAN = 'boolean';

    /**
     * @var string the literal type
     */
    public $type;

    /**
     * @var string the literal value
     */
    public $value;
}
