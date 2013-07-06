<?php

namespace Handlebars;


class Token
{

    const CONTENT = 'content';
    const COMMENT = 'comment';
    const OPEN_PARTIAL = 'openPartial';
    const OPEN_BLOCK = 'openBlock';
    const OPEN_END_BLOCK = 'openEndBlock';
    const OPEN_INVERSE = 'openInverse';
    const OPEN_UNESCAPED = 'openUnescaped';
    const OPEN_COMMENT = 'openComment';
    const OPEN = 'open';
    const EQUALS = 'equals';
    const ID = 'id';
    const SEP = 'sep';
    const CLOSE_UNESCAPED = 'closeUnescaped';
    const CLOSE = 'close';
    const STRING = 'string';
    const DATA = 'data';
    const BOOLEAN = 'boolean';
    const INTEGER = 'integer';
    const INVERSE = 'inverse';
    const INVALID = 'invalid';


    /**
     * @var string the type of token
     */
    public $type;


    /**
     * @var string the token value
     */
    public $value;

    /**
     * @var array the extra options for the token
     */
    public $options = array();

    /**
     * Initializes the token
     *
     * @param string|null $type the token type
     * @param string|null $value the token value
     * @param array $options the extra options for the token
     */
    public function __construct($type = null, $value = null, $options = array())
    {
        $this->type = $type;
        $this->value = $value;
        $this->options = $options;
    }

    /**
     * @return null|string the token value
     */
    public function __toString()
    {
        return $this->value;
    }


}
