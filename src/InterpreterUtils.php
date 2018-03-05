<?php

namespace UZTranslit;

class InterpreterUtils
{
    /**
     * "^[\p{Latin}[A-Za-z]+$"
     * /^(?:\p{Cyrillic}+|\p{Latin}+)$/
     *
     * \p{Cyrillic}, it matches any cyrillic character..
     * \p{Latin}, it matches any latin character.
     *
     * preg_match_all('/[\p{Latin}]+/u', 'АБВГД ENGLISH STRING', $matches);
     */
    public static function isLatin($text)
    {
        return preg_match("/^(?:\p{Latin}+)$/u", $text);
    }

    public static function isCyrillic($text)
    {
        return preg_match("/^(?:\p{Cyrillic}+)$/u", $text);
    }

    public static function isCyrillicOrLatin($text)
    {
        return preg_match("/^(?:\p{Cyrillic}+|\p{Latin}+)$/u", $text);
    }

    public static function isRussian($text)
    {
        return preg_match('/[А-Яа-яЁё]/u', $text);
    }

    public static function sortByLength($words)
    {
        $words = array_unique($words);
        usort($words,function ($a,$b) {
            return strlen($b) - strlen($a);
        });
        return $words;
    }

    public static function createToken($word)
    {
        $token = hash('crc32', $word);
        return $token;
    }
}