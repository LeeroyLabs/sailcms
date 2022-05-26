<?php

use SailCMS\Core\Text;

test('Deburr', function ()
{
    expect(Text::deburr('ÀÉÔÊÉ'))->toBe('AEOEE');
});

test('kebabCase', function ()
{
    expect(Text::kebabCase('Hello World'))->toBe('hello-world');
});

test('slugify string "Hello world of PHP!$#"', function ()
{
    expect(Text::slugify('Hello world of PHP!$#'))->toBe('hello-world-of-php');
});

test('camelCase string "Hello world of PHP" to "HelloWorldOfPHP"', function ()
{
    expect(Text::camelCase('Hello world of PHP'))->toBe('helloWorldOfPHP');
});

test('snakeCase string "Hello world of PHP" to "hello_world_of_php"', function ()
{
    expect(Text::snakeCase('Hello world of PHP'))->toBe('hello_world_of_php');
});