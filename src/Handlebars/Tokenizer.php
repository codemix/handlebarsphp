<?php

namespace Handlebars;

/**
 * # Tokenizer
 *
 * The Tokenizer is responsible for taking a handlebars template as input
 * and turning it into an array of tokens that can be consumed by the parser.
 *
 * Tokens are either raw strings, which should pass through the compiler unmodified,
 * or simple objects with `type` properties.
 *
 * Each protected tokenize method should accept a single string as input
 * and either return the input directly (if no match was found), or return
 * a 2 element array, the first element of which should contain the AST entity and the second
 * element should be a string containing any remaining text that was not parsed.
 *
 * @author Charles Pick <charles@codemix.com>
 * @licence MIT
 * @package Handlebars
 */
class Tokenizer
{
    /**
     * Tokenize the input and return an array of tokens.
     *
     * @param string $input the input to tokenize.
     *
     * @return array the tokens.
     */
    public function tokenize($input)
    {
        $tokens = array();
        while(mb_strlen($input) > 0) {
            if (($result = $this->tokenizeContent($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeTag($input)) !== false) {
                list($tok, $input) = $result;
                foreach($tok as $token)
                    $tokens[] = $token;
            }
            else
                throw new \Exception('Invalid Input: '.$input);
        }
        return $tokens;
    }

    /**
     * Tokenizes content and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeContent($input)
    {
        if (preg_match("/^(.*?)(\{\{)(.*)/mus", $input, $matches)) {
            if (strlen($matches[1]) == 0)
                return false;
            $input = $matches[1];
            $remaining = $matches[2].$matches[3];
        }
        else
            $remaining = '';
        return array(new Token(Token::CONTENT, $input), $remaining);
    }

    /**
     * Tokenizes a tag and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeTag($input)
    {
        if (($result = $this->tokenizeOpenTag($input)) === false)
            return false;
        list($open, $input) = $result;
        if ($open->type === Token::OPEN_COMMENT) {
            list($tokens, $input) = $this->tokenizeCommentBody($input);
            array_unshift($tokens, $open);
        }
        else if (($result = $this->tokenizeTagBody($input)) !== false) {
            list($tokens, $input) = $result;
            array_unshift($tokens, $open);
        }
        else
            $tokens = array($open);
        if (($result = $this->tokenizeCloseTag($input)) !== false) {
            list($close, $input) = $result;
            $tokens[] = $close;
        }
        else
            $tokens[] = new Token(Token::INVALID);

        return array($tokens, $input);
    }

    /**
     * Tokenizes an open tag and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeOpenTag($input)
    {
        if (!preg_match("/^(\{\{)([>|#|\/|^|&|!|{])?(.*)/mus", $input, $matches))
            return false;
        $token = new Token;
        if (!empty($matches[2])) {
            switch($matches[2]) {
                case ">":
                    $token->type = Token::OPEN_PARTIAL;
                    break;
                case "#":
                    $token->type = Token::OPEN_BLOCK;
                    break;
                case "/";
                    $token->type = Token::OPEN_END_BLOCK;
                    break;
                case "^":
                    $token->type = Token::OPEN_INVERSE;
                    break;
                case "{";
                    $token->type = Token::OPEN_UNESCAPED;
                    break;
                case "&";
                    $token->type = Token::OPEN;
                    break;
                case "!";
                    $token->type = Token::OPEN_COMMENT;
                    break;
            }
        }
        else
            $token->type = Token::OPEN;
        $token->value = $matches[1].$matches[2];
        return array($token, $matches[3]);
    }

    /**
     * Tokenizes an open tag and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeCloseTag($input)
    {
        if (!preg_match("/^\s*(\}\})(\})?(.*)/mus", $input, $matches))
            return false;
        if ($matches[2])
            $token = new Token(Token::CLOSE_UNESCAPED, $matches[1].$matches[2]);
        else
            $token = new Token(Token::CLOSE, $matches[1]);
        return array($token, $matches[3]);
    }

    /**
     * Tokenizes the body of a comment and if successful returns a 2 element array
     * containing the parsed tokens and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the tokens and remaining input or false if no match found.
     */
    protected function tokenizeCommentBody($input)
    {
        if (preg_match("/^--([^(--\}\})]*)(--)(\}\})(.*)/mus", $input, $matches)) {
            $body = $matches[1];
            $tail = $matches[3].$matches[4];
        }
        else if (preg_match("/^([^(\}\})]*)(\}\})(.*)/mus", $input, $matches)) {
            $body = $matches[1];
            $tail = $matches[2].$matches[3];
        }
        else {
            $body = "";
            $tail = $input;
        }
        return array(array(new Token(Token::COMMENT, $body)), $tail);
    }

    /**
     * Tokenizes the body of a tag and if successful returns a 2 element array
     * containing the parsed tokens and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the tokens and remaining input or false if no match found.
     */
    protected function tokenizeTagBody($input)
    {
        $input = trim($input);
        $tokens = array();
        while(mb_strlen($input) > 0) {
            if (($result = $this->tokenizeEquals($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeData($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeInteger($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeString($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeBoolean($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeInverse($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeIdentifier($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else if (($result = $this->tokenizeSeparator($input)) !== false) {
                list($token, $input) = $result;
                $tokens[] = $token;
            }
            else {
                break;
            }
            $input = trim($input);
        }
        return array($tokens, $input);
    }

    /**
     * Tokenizes an equals sign and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeEquals($input)
    {
        if (!preg_match("/^\s*(=)(.*)/mus", $input, $matches))
            return false;
        return array(new Token(Token::EQUALS, $matches[1]), $matches[2]);
    }

    /**
     * Tokenizes an integer and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeInteger($input)
    {
        if (!preg_match("/^\s*(\d+)(.*)/us", $input, $matches))
            return false;
        return array(new Token(Token::INTEGER, $matches[1]), $matches[2]);
    }

    /**
     * Tokenizes a string and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeString($input)
    {
        if (!preg_match("/^\s*('|\")(.*?)(([^\\\])(\\1))(.*)/us", $input, $matches))
            return false;
        return array(new Token(Token::STRING, $matches[2].$matches[4], array('delimiter' => $matches[1])), $matches[6]);
    }

    /**
     * Tokenizes a boolean and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeBoolean($input)
    {
        if (!preg_match("/^\s*(true|false)(\s|\})(.*)/us", $input, $matches))
            return false;
        return array(new Token(Token::BOOLEAN, $matches[1]), $matches[2].$matches[3]);
    }


    /**
     * Tokenizes an identifier and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeIdentifier($input)
    {
        if (preg_match("/^\s*(\.\.)(.*)/us", $input, $matches))
            return array(new Token(Token::ID, $matches[1]), $matches[2]);
        else if (preg_match("/^\s*(\.)(\}|\/|\s+)(.*)/us", $input, $matches))
            return array(new Token(Token::ID, $matches[1]), $matches[2].$matches[3]);
        elseif (preg_match("/^\s*\[([^\]]*)\](.*)/us", $input, $matches))
            return array(new Token(Token::ID, $matches[1], array('quoted' => true)), $matches[2]);
        else if (preg_match("/^\s*([^\!\s'\"#%\-\/;,\.;\->@\[\^`\{\~\}\]=]+)(.*)/us", $input, $matches))
            return array(new Token(Token::ID, $matches[1]), $matches[2]);
        else
            return false;
    }

    /**
     * Tokenizes an inverse token and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeInverse($input)
    {
        if (preg_match("/^\s*(else)\b(.*)/us", $input, $matches))
            return array(new Token(Token::INVERSE, $matches[1]), $matches[2]);
        else
            return false;
    }

    /**
     * Tokenizes a data reference and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeData($input)
    {
        if (!preg_match("/^\s*(@)(.*)/mus", $input, $matches))
            return false;
        return array(new Token(Token::DATA, $matches[1]), $matches[2]);
    }

    /**
     * Tokenizes a separator and if successful returns a 2 element array
     * containing the token and any remaining unparsed input. If unsuccessful
     * returns false.
     *
     * @param string $input the input to tokenize
     *
     * @return array|bool the array containing the token and remaining input or false if no match found.
     */
    protected function tokenizeSeparator($input)
    {
        if (!preg_match("/^\s*([\[\/\]\.])(.*)/mus", $input, $matches))
            return false;
        return array(new Token(Token::SEP, $matches[1]), $matches[2]);
    }
}
