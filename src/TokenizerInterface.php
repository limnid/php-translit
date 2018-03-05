<?php

namespace UZTranslit;

interface TokenizerInterface
{
    public function addToken($token, $value);
    public function setText($text);
    public function getText();
    public function getTokens();
    public function clearTokens();
    public function tokenize();
    public function normalize();
}