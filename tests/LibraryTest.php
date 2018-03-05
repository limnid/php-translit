<?php

namespace Tests;

use UZTranslit\LatinBehaviour;
use UZTranslit\TextInterpreter;
use UZTranslit\LatinTokenizer;

class LibraryTest extends \PHPUnit_Framework_TestCase
{
    public function testTransliteration()
    {
        $textInterpreter = new TextInterpreter();
        $textInterpreter->setTokenizer(new LatinTokenizer());
        $textInterpreter->addBehavior(new LatinBehaviour([]));

        $source = 'Афғонистон бўйича конференция очилишида 
            Ўзбекистон ва Афғонистон президентлари сўзга чиқади. БМТ 
            бош котиби ҳамда қатор давлатлар ТИВ раҳбарлари анжуманда 
            қатнашиш учун таклиф қилинган.';
        
        $text = $textInterpreter
            ->process($source)
            ->getText();

        $this->assertNotEmpty($text);
    }
}