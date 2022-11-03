<?php

use SailCMS\Collection;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\Username;

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'http://localhost:8888';
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
        expect($id)->not->toBe('');
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

test('Fail at creating a user with email already in use', function ()
{
    $model = new User();

    $name = new Username('John', 'Doe', 'John Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'johndoe@leeroy.ca', 'Hell0W0rld!', $roles, '', $meta);
        expect(true)->toBe(false);
    } catch (Exception $e) {
        expect(true)->toBe(true);
    }
});

test('Fail at creating a user with an invalid email', function ()
{
    $model = new User();

    $name = new Username('John', 'Doe', 'John Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'johndoe@leeroy', 'Hell0W0rld!', $roles, '', $meta);
        expect(true)->toBe(false);
    } catch (Exception $e) {
        expect(true)->toBe(true);
    }
});

test('Fail at creating a user with an unsecure password', function ()
{
    $model = new User();

    $name = new Username('John', 'Doe', 'John Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'johndoe@leeroy.ca', 'hello123', $roles, '', $meta);
        expect(true)->toBe(false);
    } catch (Exception $e) {
        expect(true)->toBe(true);
    }
});

test('Update user johndoe@leeroy.ca', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');
    $name = new Username('John', 'DoeDoe', 'John DoeDoe');

    try {
        $result = $model->update($user->_id, $name);
        expect($result)->toBe(true);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

test('Fetch a user by email', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');
    expect($user)->not->toBe(null);
});

test('Fetch a user by email and his permissions', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');

    expect($user)->not->toBe(null)->and($user->permissions()->length)->not->toBe(0);
});

test('Check if user has flag "use2fa"', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');

    if ($user) {
        expect($user->has('use2fa'))->toBe(false);
    }
});

test('Set the flag "us2fa" to true', function ()
{
    $model = new User();
    $user = $model->getByEmail('johndoe@leeroy.ca');

    if ($user) {
        $user->flag('use2fa');
        expect($user->has('use2fa'))->toBe(true);
    }
})->group('db');

test('Get list of user with flag "use2fa"', function ()
{
    $users = User::flagged('use2fa');
    expect($users->length)->toBeGreaterThanOrEqual(1);
});

test('Get list of user without flag "use2fa"', function ()
{
    $users = User::notFlagged('use2fa');
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