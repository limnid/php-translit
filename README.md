# Transliterator

**Install**
```$xslt
composer install
```

**Run test**
```$xslt
php vendor/bin/phpunit
```

**Example**

```$xslt
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
```

**Result**

```$xslt
Afg‘oniston bo‘yicha konferensiya ochilishida
O‘zbekiston va Afg‘oniston prezidentlari so‘zga chiqadi. BMT
bosh kotibi hamda qator davlatlar TIV rahbarlari anjumanda
qatnashish uchun taklif qilingan.
```
