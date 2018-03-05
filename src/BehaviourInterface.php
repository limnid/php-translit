<?php

namespace UZTranslit;

interface BehaviourInterface
{
    /**
     * @param LatinTokenizer $text
     * @return object | null
     */
    public function next($text);
}