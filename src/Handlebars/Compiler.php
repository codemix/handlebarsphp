<?php

namespace Handlebars;
/**
 * Handlebars template compiler.
 *
 * The compiler is responsible for generating an Abstract Syntax Tree for a template using the parser
 * and turning that AST into a executable PHP code using the code generator.
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars
 */
class Compiler
{
    /**
     * @var CodeGenerator the code generator for the compiler
     */
    protected $generator;

    /**
     * @var Parser the handlebars parser to use
     */
    protected $parser;

    /**
     * @param \Handlebars\CodeGenerator $generator
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;
    }

    /**
     * @return \Handlebars\CodeGenerator
     */
    public function getGenerator()
    {
        if ($this->generator === null)
            $this->generator = new CodeGenerator();
        return $this->generator;
    }

    /**
     * @param \Handlebars\Parser $parser
     */
    public function setParser($parser)
    {
        $this->parser = $parser;
    }

    /**
     * @return \Handlebars\Parser
     */
    public function getParser()
    {
        if ($this->parser === null)
            $this->parser = new Parser;
        return $this->parser;
    }


    /**
     * Compile the given handlebars template source into executable code.
     *
     * @param string $input the template source
     *
     * @return string the generated code
     */
    public function compile($input)
    {
        $ast = $this->getParser()->parse($input);
        return $this->getGenerator()->generate($ast);
    }

}
