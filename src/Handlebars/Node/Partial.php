<?php

namespace Handlebars\Node;

class Partial extends Base
{

    /**
     * @var Literal|Identifier the partial name
     */
    public $name;

    /**
     * @var Base the partial context
     */
    public $context;
}
