<?php

use SailCMS\Collection;
use SailCMS\Models\User;
use SailCMS\Models\UserGroup;
use SailCMS\Sail;
use SailCMS\Types\Username;

class groupTestClass
{
    public static string $id = '';
}

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Create a group', function ()
{
    groupTestClass::$id = UserGroup::create('test');
    expect(groupTestClass::$id)->not->toBeEmpty();
})->group('groups');

test('Add user to group', function ()
{
    $user = User::getBy('email', 'marc@leeroy.ca');

    if ($user) {
        $user->setGroup(groupTestClass::$id);
    }

    $user = User::getBy('email', 'marc@leeroy.ca');
    expect($user)->not->toBeNull()->and($user->group)->not->toBeEmpty();
})->group('groups');

test('Remove user from group', function ()
{
    $user = User::getBy('email', 'marc@leeroy.ca');

    if ($user) {
        $user->removeGroup();
    }

    $user = User::getBy('email', 'marc@leeroy.ca');
    expect($user)->not->toBeNull()->and($user->group)->toBeEmpty();
})->group('groups');

test('Delete a group', function ()
{
    try {
        $r = UserGroup::delete(groupTestClass::$id);
        expect($r)->toBeTrue();
    } catch (\SailCMS\Errors\DatabaseException $e) {
        expect(false)->toBeTrue();
    }
})->group('groups');