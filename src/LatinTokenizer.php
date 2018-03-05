<?php

namespace UZTranslit;

class LatinTokenizer implements TokenizerInterface
{
    private $text;
    private $tokens = [];

    public function addToken($token, $value)
    {
        $this->tokens[$token] = $value;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function clearTokens()
    {
        $this->tokens = [];
    }

    public function getText()
    {
        return $this->text;
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function tokenize()
    {
        foreach ($this->getTokens() as $token => $word) {
            $this->text = str_replace($word, '{'.$token.'}', $this->getText());
        }
    }

    public function normalize()
    {
        foreach ($this->getTokens() as $token => $word) {
            $this->text = str_replace('{'.$token.'}', $word, $this->getText());
        }
    }
}