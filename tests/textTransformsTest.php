<?php

use SailCMS\Text;

test('deburr', function ()
{
    expect(Text::from('ÀÉÔÊÉ')->deburr()->value())->toBe('AEOEE');
})->group('text');

test('kebab', function ()
{
    expect(Text::from('Hello World')->kebab()->value())->toBe('hello-world');
})->group('text');

test('slug', function ()
{
    expect(Text::from('Hello world of PHP!$#')->slug()->value())->toBe('hello-world-of-php');
})->group('text');

test('camel', function ()
{
    expect(Text::from('Hello world of PHP')->camel()->value())->toBe('helloWorldOfPHP');
})->group('text');

test('snake', function ()
{
    expect(Text::from('Hello world of PHP')->snake()->value())->toBe('hello_world_of_php');
})->group('text');

test('upper', function ()
{
    expect(Text::from('Hello world of PHP')->upper()->value())->toBe('HELLO WORLD OF PHP');
})->group('text');

test('lower', function ()
{
    expect(Text::from('Hello world of PHP')->lower()->value())->toBe('hello world of php');
})->group('text');

test('capitalize', function ()
{
    expect(Text::from('hello world')->capitalize()->value())->toBe('Hello World');
})->group('text');

test('capitalize first only', function ()
{
    expect(Text::from('hello world')->capitalize(true)->value())->toBe('Hello world');
})->group('text');

test('trim (both)', function ()
{
    expect(Text::from('  Hello World  ')->trim()->value())->toBe('Hello World');
})->group('text');

test('trim (left)', function ()
{
    expect(Text::from('  Hello World')->trimLeft()->value())->toBe('Hello World');
})->group('text');

test('trim (right)', function ()
{
    expect(Text::from('Hello World  ')->trimRight()->value())->toBe('Hello World');
})->group('text');

test('pluralize', function ()
{
    expect(Text::from('apple')->pluralize()->value())->toBe('apples');
})->group('text');

test('singularize', function ()
{
    expect(Text::from('apples')->singularize()->value())->toBe('apple');
})->group('text');

test('reverse', function ()
{
    expect(Text::from('apples')->reverse()->value())->toBe('selppa');
})->group('text');

test('reverse accented', function ()
{
    expect(Text::from('éric')->reverse()->value())->toBe('ciré');
})->group('text');

test('hex', function ()
{
    expect(Text::from('apple')->hex()->value())->toBe('6170706c65');
})->group('text');

test('bin', function ()
{
    expect(Text::from('6170706c65')->bin()->value())->toBe('apple');
})->group('text');

test('binary', function ()
{
    expect(Text::from('apple')->binary()->value())->toBe('1100001 1110000 1110000 1101100 1100101');
})->group('text');

test('text', function ()
{
    expect(Text::from('1100001 1110000 1110000 1101100 1100101')->text()->value())->toBe('apple');
})->group('text');

test('pad', function ()
{
    expect(Text::from('Hello')->pad(7)->value())->toBe('Hello  ');
})->group('text');

test('replace', function ()
{
    expect(Text::from('Hello')->replace('Hello', 'Yellow')->value())->toBe('Yellow');
})->group('text');

test('shuffle', function ()
{
    expect(Text::from('Hello World!')->shuffle()->value())->not->toBe('Hello World!');
})->group('text');

test('safe (xss)', function ()
{
    expect(Text::from('<script>alert(localStorage.get("sailcms_key"));</script>')->safe()->value())
        ->not->toBe('<script>alert(localStorage.get("sailcms_key"));</script>');
})->group('text');

test('stripTags', function ()
{
    expect(Text::from('<a href="">test</a>')->stripTags()->value())->not->toBe('<a href="">test</a>');
})->group('text');

test('specialChars (xss)', function ()
{
    expect(Text::from('<?=Hello World!<script>?>')->specialChars()->value())->not->toBe('<?=Hello World!<script>?>');
})->group('text');

test('entities', function ()
{
    expect(Text::from('Hé!')->entities()->value())->toBe('H&eacute;!');
})->group('text');

test('chunks', function ()
{
    expect(Text::from('HelloWorld!')->chunks(4)->length)->toBe(3);
})->group('text');

test('format', function ()
{
    expect(Text::from('Hello %s!')->format(['World'])->value())->toBe('Hello World!');
})->group('text');

test('truncate', function ()
{
    expect(Text::from('Hello World!')->truncate(8)->value())->toBe('Hello...');
})->group('text');

test('truncate (test length)', function ()
{
    expect(Text::from('Hello World of PHP!')->truncate(8)->length)->toBe(8);
})->group('text');

test('safeTruncate', function ()
{
    expect(Text::from('Hello World of PHP!')->safeTruncate(10)->value())->toBe('Hello ...');
})->group('text');

test('basename', function ()
{
    expect(Text::from('/var/tmp/name-of-file.txt')->basename()->value())->toBe('name-of-file.txt');
})->group('text');

test('url', function ()
{
    expect(Text::from('/products/my-product')->url()->value())->toBe(env('SITE_URL') . '/products/my-product');
})->group('text');

test('url with missing / at start of string', function ()
{
    expect(Text::from('products/my-product')->url()->value())->toBe(env('SITE_URL') . '/products/my-product');
})->group('text');

test('extension just filename', function ()
{
    expect(Text::from('myfileisoftype.txt')->extension()->value())->toBe('txt');
})->group('text');

test('extension full path', function ()
{
    expect(Text::from('/var/tmp/path/longone/ok/myfileisoftype.txt')->extension()->value())->toBe('txt');
})->group('text');

test('extension no extension', function ()
{
    expect(Text::from('/var/tmp/path/longone/ok/myfileisoftype')->extension()->value())->toBe('');
})->group('text');

test('nl', function ()
{
    expect(Text::from('hello<br/>world')->nl()->value())->toBe("hello\nworld");
})->group('text');

test('br', function ()
{
    expect(Text::from("hello\nworld")->br()->value())->toBe("hello<br/>world");
})->group('text');

test('censor', function ()
{
    expect(Text::from("Hello Fucking World of Shit!")->censor(['fucking', 'fuck', 'shit'])->value())->toBe("Hello ******* World of ****!");
})->group('text');

test('substr', function ()
{
    expect(Text::from("Hello World!")->substr(0, 5)->value())->toBe("Hello");
})->group('text');