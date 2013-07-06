<?php

namespace Handlebars\Node;


class Invalid extends Identifier
{
    /**
     * @var string the reason the node is invalid
     */
    public $reason;

    /**
     * @var Base the previously assembled node
     */
    public $previous;
}
