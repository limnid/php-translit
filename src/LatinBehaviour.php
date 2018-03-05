<?php

namespace UZTranslit;

class LatinBehaviour implements BehaviourInterface
{
    private $alphabets = [
        'dict' => [],
        'uz' => [
            "а", "б", "в", "г", "д", "ж", "з", "и",
            "й", "к", "л", "м", "н", "о", "п", "р", "с", "т",
            "у", "ф", "х", "ч", "ш", "ъ", "ь", "щ",
            "ғ", "қ", "ў", "ҳ", "я", "э", "ю", "е", "ё", "ц",
            "А", "Б", "В", "Г", "Д", "Ж", "З", "И",
            "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т",
            "У", "Ф", "Х", "Ч", "Ш", "Ъ", "Ь", "Щ",
            "Ғ", "Қ", "Ў", "Ҳ", "Я", "Э", "Ю", "Е", "Ё", "Ц",
            "№"
        ],
        'oz' => [
            "a", "b", "v", "g", "d", "j", "z", "i",
            "y", "k", "l", "m", "n", "o", "p", "r", "s", "t",
            "u", "f", "x", "ch", "sh", "’", "", "",
            "g‘", "q", "o‘", "h", "ya", "e", "yu", "e", "yo", "ts",
            "A", "B", "V", "G", "D", "J", "Z", "I",
            "Y", "K", "L", "M", "N", "O", "P", "R", "S", "T",
            "U", "F", "X", "Ch", "Sh", "’", "", "",
            "G‘", "Q", "O‘", "H", "Ya", "E", "Yu", "E", "Yo", "Ts",
            "#"
        ],
        'vovels' => [
            "а", "у", "о", "ы", "и", "э", "я", "ю", "ё", "е", "ў"
        ],
        'uppers_latin' => [
            "A", "B", "V", "G", "D", "J", "Z", "I", "Y", "K", "L", "M",
            "N", "O", "P", "R", "S", "T", "U", "F", "X", "Ch", "Sh",
            "G‘", "Q", "O‘", "H", "Ya", "E", "Yu", "E", "Yo", "Ts"
        ],
        'uppers_cyrillic' => [
            "А", "Б", "В", "Г", "Д", "Ж", "З", "И",
            "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т",
            "У", "Ф", "Х", "Ч", "Ш", "Ъ", "Ь", "Щ",
            "Ғ", "Қ", "Ў", "Ҳ", "Я", "Э", "Ю", "Е", "Ё", "Ц"
        ],
        'doubles' => [
            "Ch", "Sh", "Ya", "Yu", "Yo", "Yu", "Ts"
        ]
    ];

    public function __construct($dictionary)
    {
        if (count($dictionary) > 0) {
            $this->alphabets['dict'] = $dictionary;
        }
    }

    public function isPartUppercase($string)
    {
        return (bool) preg_match("/[A-Z]/", $string);
    }

    public function mbStringToArray($string)
    {
        $array = [];
        $strLen = mb_strlen($string);
        while ($strLen) {
            $array[] = mb_substr($string, 0, 1, "UTF-8");
            $string = mb_substr($string, 1, $strLen, "UTF-8");
            $strlen = mb_strlen($string);
        }
        return $array;
    }

    public function translit($text)
    {
        return trim(str_replace($this->alphabets['uz'], $this->alphabets['oz'], $text));
    }

    private function isUpperLatin($char)
    {
        return in_array($char, $this->alphabets['uppers_latin']);
    }

    private function isUpperCyrillic($char)
    {
        return in_array($char, $this->alphabets['uppers_cyrillic']);
    }

    /**
     * Правило для словаря
     *
     * @param $word
     * @return string
     */
    private function rule_dict($word)
    {
        $chars = preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY);
        $dictionary = array_keys($this->alphabets['dict']);
        $word_key = mb_strtolower($word);

        // Если такое слово есть в словаре
        if (in_array($word_key, $dictionary)) {
            // Достаем слово
            $word = $this->alphabets['dict'][$word_key];

            // Разбиваем переведенное слово на символы
            $translit_chars = preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY);
            $translit_chars_result = [];

            for ($i = 0; $i < count($translit_chars); $i++) {
                // Если в оригинале первая буква была заглавная то и в переведенном слове делаем букву заглавной
                if ($i == 0 && $this->isUpperCyrillic($chars[0])) {
                    // $translit_chars_result = mb_convert_case($word, MB_CASE_TITLE, "UTF-8");
                    $translit_chars_result[] = mb_convert_case($translit_chars[$i], MB_CASE_UPPER, "UTF-8");
                    continue;
                }
                $translit_chars_result[] = $translit_chars[$i];
            }

            $word = implode('', $translit_chars_result);
        }

        return $word;
    }

    /**
     * Правило для "ц"
     *
     * @param $word
     * @return string
     */
    private function rule_s($word)
    {
        $chars = preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY);
        $chars_result = [];
        for ($i = 0; $i < count($chars); $i++) {
            if (mb_strtolower($chars[$i]) == 'ц') {
                // Если символ в начале слова или в середине после согласной
                if ($i == 0 || !in_array($chars[$i - 1], $this->alphabets['vovels'])) {
                    $chars_result[] = str_replace(["ц", "Ц"], ["s", "S"], $chars[$i]);
                    continue;
                }
            }
            $chars_result[] = $chars[$i];
        }
        return implode('', $chars_result);
    }

    /**
     * Правило для "e"
     *
     * @param $word
     * @return string
     */
    private function rule_e($word)
    {
        $chars = preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY);
        $chars_result = [];
        for ($i = 0; $i < count($chars); $i++) {
            if (mb_strtolower($chars[$i]) == 'е') {
                // Если символ в начале слова или в середине после гласной
                if ($i == 0 || in_array($chars[$i - 1], $this->alphabets['vovels'])) {
                    $chars_result[] = str_replace(["е", "Е"], ["ye", "Ye"], $chars[$i]);
                    continue;
                }
            }
            $chars_result[] = $chars[$i];
        }
        return implode('', $chars_result);
    }

    /**
     * Правило для "Аббревиатур"
     * применяется после трансилитерации
     *
     * @param $word
     * @return string
     */
    private function rule_abbreviation_latin($word)
    {
        $chars = preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY);
        $stack = [];
        $chars_result = [];
        for ($i = 0; $i < count($chars); $i++) {
            $stack[] = $chars[$i];
            if (count($stack) > 2) {
                array_shift($stack);
            }
            // Если это Sh Ch и т.д.
            if (in_array(implode('', $stack), $this->alphabets['doubles'])) {
                $toUppercase = false;
                // Если предыдущий символ заглавный
                if (isset($chars[$i - 2]) && $this->isUpperLatin($chars[$i - 2])) {
                    $toUppercase = true;
                }
                // Если следующий символ заглавный
                if (isset($chars[$i + 1]) && $this->isUpperLatin($chars[$i + 1])) {
                    $toUppercase = true;
                }
                if ($toUppercase) {
                    $chars_result[] = mb_strtoupper($chars[$i]);
                    continue;
                }
            }
            $chars_result[] = $chars[$i];
        }
        return implode('', $chars_result);
    }

    public function next($tokenizer)
    {
        foreach ($tokenizer->getTokens() as $token => $word) {
            $word = $this->rule_dict($word);
            $word = $this->rule_s($word);
            $word = $this->rule_e($word);
            $word = $this->translit($word);
            $word = $this->rule_abbreviation_latin($word);
            $tokenizer->addToken($token, $word);
        }

        return $tokenizer;
    }
}