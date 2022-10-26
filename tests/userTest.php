<?php

use SailCMS\Collection;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\Username;

beforeEach(function ()
{
    Sail::setAppState(Sail::STATE_CLI);
});

test('Create a user', function ()
{
    $model = new User();

    $name = new Username('John', 'Doe', 'John Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $id = $model->create($name, 'johndoe@leeroy.ca', 'Hell0W0rld!', $roles, '', $meta);
        expect($id)->not()->toBe(null);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

test('Fetch a user by email', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');

    expect($user)->not()->toBe(null);
});

test('Fetch a user by email and his permissions', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');

    expect($user)->not()->toBe(null)->and($user->permissions()->length)->not()->toBe(0);
});

test('Check if user has flag "test2"', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe+test@leeroy.ca');

    if ($user) {
        expect($user->has('test2'))->toBe(false);
    }
});

test('Set the flag "test1" to true', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe+test@leeroy.ca');

    if ($user) {
        $user->flag('test1');
        expect($user->has('test1'))->toBe(true);
    }
})->group('db');

test('Get list of user with flag "test1"', function ()
{
    $users = User::flagged('test1');
    expect($users->length)->toBeGreaterThanOrEqual(1);
});

test('Get list of user without flag "test1"', function ()
{
    $users = User::notFlagged('test1');
    expect($users->length)->toBeGreaterThanOrEqual(0);
});

test('Delete a user', function ()
{
    $model = new User();

    try {
        $result = $model->removeByEmail('johndoe@leeroy.ca');
        expect($result)->toBe(true);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

test('Create a user and delete it by the instance', function ()
{
    $model = new User();

    $name = new Username('John', 'Doe', 'John Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $id = $model->create($name, 'johndoe@leeroy.ca', 'Hell0W0rld!', $roles, '', $meta);

        $user = $model->getById($id);

        if ($user) {
            $user->remove();
            $user = $model->getById($id);

            expect($user)->toBe(null);
        } else {
            expect(true)->toBe(false);
        }
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});