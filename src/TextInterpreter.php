<?php

namespace UZTranslit;
use UZTranslit\WordsParser\Text\TextWordsParser;

/**
 * @property TokenizerInterface $tokenizer
 */
class TextInterpreter
{
    private $behaviours = [];
    private $tokenizer;

    /**
     * @param TokenizerInterface $tokenizer
     */
    public function setTokenizer($tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * @param BehaviourInterface $behaviour
     */
    public function addBehavior($behaviour)
    {
        $this->behaviours[] = $behaviour;
    }

    public function isCyrillic($text) {
        return preg_match('/[А-Яа-яЁё]/u', $text);
    }

    public function isLatin($text) {
        return preg_match("/^(?:\p{Latin}+)$/u", $text);
    }

    /**
     * @param $text
     * @return TokenizerInterface
     */
    public function process($text)
    {
        $this->tokenizer->clearTokens();
        $this->tokenizer->setText($text);

        /**
         * Parse text
         */
        $wp = new TextWordsParser(array('Latin', 'Cyrillic'));
        $textFormatted = $wp->parse($text, $words, $sentences, $uniques, $offset_map);
        $words = InterpreterUtils::sortByLength($words);

        /**
         * Tokenize
         */
        foreach ($words as $word) {
            if (is_numeric($word) || $this->isLatin($word)) continue;
            $token = InterpreterUtils::createToken($word);
            $this->tokenizer->addToken($token, $word);
        }

        $this->tokenizer->tokenize();

        /**
         * Behaviours
         */
        foreach ($this->behaviours as $behaviour) {
            $this->tokenizer = $behaviour->next($this->tokenizer);
        }

        $this->tokenizer->normalize();

        return $this->tokenizer;
    }
}