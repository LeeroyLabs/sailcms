<?php

use SailCMS\Collection;
use SailCMS\Types\Sorting;

beforeEach(function ()
{
    $this->collection = new Collection(['hello', 'world']);
});

test('Traversal #1: index.object_property', function ()
{
    $class1 = (object)['prop' => 'hello world', 'prop2' => 'unused'];
    $class2 = (object)['prop' => 'You Found Me!'];

    $col = new Collection([$class1, $class2]);
    $value = $col->get('1.prop');

    expect($value)->toBe('You Found Me!');
});

test('Traversal #2: (collection of collections) index.index.property (0.2.test)', function ()
{
    $class1 = (object)['test' => 'Hello World!'];
    $col1 = new Collection(['abc', 'def', $class1]);
    $col2 = new Collection(['rst', 'uvw', 'xyz']);

    $col = new Collection([$col1, $col2]);
    $value = $col->get('0.2.test');

    expect($value)->toBe('Hello World!');
});

test('Get length of collection', function ()
{
    expect($this->collection->length)->toBe(2);
});

test('Unwrap collection', function ()
{
    expect($this->collection->unwrap())->toBeArray();
});

test('Unwrap collection with embedded collections', function ()
{
    $subCollection = new Collection(['sub', 'collection', 'data']);

    $this->collection->push($subCollection);

    $unwrapped = $this->collection->unwrap();

    foreach ($unwrapped as $key => $value) {
        expect($value instanceof Collection)->not->toBe(Collection::class);
    }
});

test('Push element into the collection', function ()
{
    $this->collection->push('one more');
    expect($this->collection->last)->toBe('one more');
});

test('Push using Spread method', function ()
{
    $col = new Collection([1, 2, 3]);
    $length = $this->collection->pushSpread(...$col)->length;
    expect($length)->toBe(5);
});

test('Prepend element into the collection', function ()
{
    $this->collection->prepend('first!');
    expect($this->collection->first)->toBe('first!');
});

test('Added and element with key', function ()
{
    $this->collection->add('akey', 'test');
    expect($this->collection->akey)->not->toBeNull();
});

test('Reverse the collection', function ()
{
    $arr = $this->collection->reverse()->unwrap();
    expect($arr[0] === 'world')->toBeTrue();
    expect($arr[1] === 'hello')->toBeTrue();
});

test('Simple chaining (reverse, first)', function ()
{
    $value = $this->collection->reverse()->first;
    expect($value === 'world')->toBeTrue();
});

test('A bit more complex chaining (reverse, slice, first)', function ()
{
    $value = $this->collection->reverse()->slice(0, 1)->first;
    expect($value === 'world')->toBeTrue();
});

test('Get collection keys', function ()
{
    $keys = $this->collection->keys();

    expect($keys->at(0))->toBe(0);
    expect($keys->at(1))->toBe(1);
});

test('Map a collection', function ()
{
    $mapped = $this->collection->map(fn($el) => "->{$el}");
    expect($mapped->first)->toBe('->hello');
});

test('Filter a collection', function ()
{
    $filtered = $this->collection->filter(fn($el) => ($el !== 'world'));
    expect($filtered->first)->toBe('hello');
});

test('Create chunks of the collection by 2 (end up with 3 chunks)', function ()
{
    $arr = [1, 2, 3, 4];
    $chunks = $this->collection->pushSpread(...$arr)->chunks(2);
    expect($chunks->length)->toBe(3);
});

test('Collection shuffling', function ()
{
    $arr = [];
    $a = 0;
    $max = 1000;

    while ($a < $max) {
        $arr[] = $a;
        $a++;
    }

    $this->collection->pushSpread(...$arr);
    $first = $this->collection->first;
    $second = $this->collection->at(1);

    $shuf = $this->collection->shuffle();
    $sfirst = $shuf->first;
    $ssecond = $shuf->at(1);

    expect($first)->not->toBe($sfirst)->and($second)->not->toBe($ssecond);
});

test('Check if collection contains a certain value', function ()
{
    expect($this->collection->contains('hello'))->toBe(true);
});

test('Remove duplicates from collection', function ()
{
    $arr = ['hello', 'bob', 'jones', 'john', 'hello'];
    $this->collection->pushSpread(...$arr);

    $before = $this->collection->length;
    $after = $this->collection->dedup(SORT_STRING)->length;

    expect($before)->toBeGreaterThan($after);
});

test('JSON serialization', function ()
{
    expect(json_encode($this->collection))->not->toBe("{}");
});

test('Run Each loop on collection', function ()
{
    $data = Collection::init();
    $length = 0;

    $this->collection->each(function ($key, $value) use ($data)
    {
        $data->push($key);
    });

    $finalLength = $data->length;

    expect($finalLength)->toBeGreaterThan($length);
});

test('Find value in collection', function ()
{
    $result = $this->collection->find(fn($key, $value) => str_contains($value, 'll'));
    expect($result)->toBe("hello");
});

test('Find index of value in collection', function ()
{
    $result = $this->collection->findIndex(fn($key, $value) => str_contains($value, 'll'));
    expect($result)->toBe(0);
});

test('Sort array numerically without keeping keys', function ()
{
    $col = new Collection([100, 25, 30, 21, 43, 55]);
    $value = $col->sort(Sorting::ASC, SORT_NUMERIC)->unwrap();
    expect($value[0])->toBe(21);
});

test('Sort array numerically with keeping keys', function ()
{
    $col = new Collection([100, 25, 30, 21, 43, 55]);
    $value = $col->sort(Sorting::ASC, SORT_NUMERIC, true);

    expect($value->keys()->first)->toBe(3);
    expect($value->first)->toBe(21);
});

test('Sort array by object key', function ()
{
    $class1 = (object)['key' => 'Émilie'];
    $class2 = (object)['key' => 'Jennifer'];
    $class3 = (object)['key' => 'Bah Baka'];

    $col = new Collection([$class1, $class2, $class3]);
    $final = $col->sortBy('key');

    expect($final->first->key)->toBe('Bah Baka');
    expect($final->last->key)->toBe('Jennifer');
});

test('Empty collection', function ()
{
    $col = Collection::init();
    expect($col->empty)->toBe(true);
});

test('Pop element out of collection', function ()
{
    $col = new Collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $col2 = $col->pop(2);

    expect($col2->length)->toBe(2);
    expect($col->length)->toBe(8);
});

test('Get differences between 2 collections', function ()
{
    $col1 = new Collection(['a', 'b', 'c', 'd', 'e']);
    $col2 = new Collection(['a', 'b', 'f', 'g', 'h', 'i']);
    $diff = $col1->diff($col2);

    expect($diff->length)->toBe(3);
});

test('Get intersection of 2 collections', function ()
{
    $col1 = new Collection(['a', 'b', 'c', 'd', 'e']);
    $col2 = new Collection(['a', 'b', 'f', 'g', 'h', 'i']);
    $diff = $col1->intersect($col2);

    expect($diff->length)->toBe(2);
});

test('Merge 2 collections non-recursively', function ()
{
    $col1 = new Collection(['a', 'b', 'c']);
    $col2 = new Collection(['d', 'e', 'f']);
    $arr = $col1->merge($col2);

    expect($arr->length)->toBe(6);
});

test('Merge 2 collections recursively (only works on associative arrays/collections)', function ()
{
    $col1 = new Collection(['super' => ['test' => 1], 'subtest' => 1]);
    $col2 = new Collection(['super' => ['test' => 2], 'subtest' => 2]);
    $arr = $col1->merge($col2, true);
    expect($arr->length)->toBe(2);
});

test('Pull element out of collection', function ()
{
    $col = new Collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $pulled = $col->pull(4);

    expect($pulled)->toBe(5);
});

test('Splice a piece out of the collection', function ()
{
    $col = new Collection([1, 2, 3, 4, 5, 6, 7]);
    $new = $col->splice(0, 5);

    expect($new->length)->toBe(5);
});

test("where with array", function ()
{
    $col = new Collection([
        ['mykey' => 'super'],
        ['mykey' => 'hello'],
        ['mykey' => 'world'],
        ['mykey' => 'hello']
    ]);

    $new = $col->where('mykey', 'hello');
    expect($new->length)->toBe(2);
});

test("where not with array", function ()
{
    $col = new Collection([
        ['mykey' => 'super'],
        ['mykey' => 'hello'],
        ['mykey' => 'world'],
        ['mykey' => 'hello']
    ]);

    $new = $col->whereNot('mykey', 'hello');
    expect($new->length)->toBe(2);
});

test("where with Object", function ()
{
    $obj1 = (object)['mykey' => 'world'];
    $obj2 = (object)['mykey' => 'hello'];

    $col = new Collection([
        $obj1,
        $obj2,
    ]);

    $new = $col->where('mykey', 'hello');

    expect($new->length)->toBe(1)->and($new->get('0')->mykey)->toBe('hello');
});

test('whereIn', function ()
{
    $col = new Collection([
        ['key' => 'hello'],
        ['key' => 'world'],
        ['key' => 'not in'],
        ['key' => 'nope not it']
    ]);

    $new = $col->whereIn('key', ['hello', 'world']);

    expect($new->length)->toBe(2);
});

test('whereIn with objects', function ()
{
    $col = new Collection([
        (object)['key' => 'hello'],
        (object)['key' => 'world'],
        (object)['key' => 'not in'],
        (object)['key' => 'nope not it']
    ]);

    $new = $col->whereIn('key', ['hello', 'world']);

    expect($new->length)->toBe(2);
});

test('whereNotIn', function ()
{
    $col = new Collection([
        ['key' => 'hello'],
        ['key' => 'world'],
        ['key' => 'not in'],
        ['key' => 'nope not it']
    ]);

    $new = $col->whereNotIn('key', ['not in', 'nope not it']);

    expect($new->length)->toBe(2);
});

test('whereBetween', function ()
{
    $col = new Collection([
        ['key' => 1],
        ['key' => 2],
        ['key' => 3],
        ['key' => 4]
    ]);

    $new = $col->whereBetween('key', 1, 3);

    expect($new->length)->toBe(3);
});

test('whereNotBetween', function ()
{
    $col = new Collection([
        ['key' => 1],
        ['key' => 2],
        ['key' => 3],
        ['key' => 4]
    ]);

    $new = $col->whereBetween('key', 3, 6);

    expect($new->length)->toBe(2);
});

test('whereNull', function ()
{
    $col = new Collection([
        ['key' => 1],
        ['key' => null],
        ['key' => 3],
        ['key' => 4]
    ]);

    $new = $col->whereNull('key');

    expect($new->length)->toBe(1);
});

test('whereNotNull', function ()
{
    $col = new Collection([
        ['key' => 1],
        ['key' => null],
        ['key' => 3],
        ['key' => 4]
    ]);

    $new = $col->whereNotNull('key');

    expect($new->length)->toBe(3);
});

test('whereInstanceOf', function ()
{
    $col = new Collection([
        ['key' => (object)['test' => 1]],
        ['key' => new DateTime()],
        ['key' => null],
        ['key' => 4]
    ]);

    $new = $col->whereInstanceOf('key', \stdClass::class);

    expect($new->length)->toBe(1);
});

test('whereNotInstanceOf', function ()
{
    $col = new Collection([
        ['key' => (object)['test' => 1]],
        ['key' => new DateTime()],
        ['key' => null],
        ['key' => 4]
    ]);

    $new = $col->whereNotInstanceOf('key', \stdClass::class);

    expect($new->length)->toBe(3);
});

test('Get highest value of array', function ()
{
    $array = new Collection([1, 2, 10, 20, 3, 0, 1456, 521, 6721, 2456]);
    $high = $array->max();

    expect($high)->toBe(6721);
});

test('Get highest value of array by key - Collection', function ()
{
    $array = new Collection([
        new Collection(['key' => 100, 'title' => 'test1']),
        new Collection(['key' => 120, 'title' => 'test1']),
        new Collection(['key' => 40, 'title' => 'test1']),
        new Collection(['key' => 509, 'title' => 'test1']),
        new Collection(['key' => 1640, 'title' => 'test1']),
        new Collection(['key' => 12, 'title' => 'test1']),
    ]);

    $high = $array->maxBy('key');
    expect($high)->toBe(1640);
});

test('Get highest value of array by key - Array', function ()
{
    $array = new Collection([
        ['key' => 100, 'title' => 'test1'],
        ['key' => 120, 'title' => 'test1'],
        ['key' => 40, 'title' => 'test1'],
        ['key' => 509, 'title' => 'test1'],
        ['key' => 1640, 'title' => 'test1'],
        ['key' => 12, 'title' => 'test1'],
    ]);

    $high = $array->maxBy('key');
    expect($high)->toBe(1640);
});

test('Get highest value of array by key - Object', function ()
{
    $array = new Collection([
        (object)['key' => 100, 'title' => 'test1'],
        (object)['key' => 120, 'title' => 'test1'],
        (object)['key' => 40, 'title' => 'test1'],
        (object)['key' => 509, 'title' => 'test1'],
        (object)['key' => 1640, 'title' => 'test1'],
        (object)['key' => 12, 'title' => 'test1'],
    ]);

    $high = $array->maxBy('key');
    expect($high)->toBe(1640);
});