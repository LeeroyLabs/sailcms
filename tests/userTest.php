<?php

use SailCMS\Collection;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\Username;

beforeAll(function () {
    Sail::setupForTests(__DIR__);
});

test('Test Fetch global context setting', function () {
    $setting = setting('emails.globalContext.locales.fr');

    expect($setting)->not->toBeNull()->and($setting->get('defaultWho', null))->not->toBeNull();
})->group('setting');

test('Create a user', function () {
    $model = new User();

    $name = new Username('John', 'Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $id = $model->create($name, 'marc+johndoe@leeroy.ca', 'Hell0W0rld!', $roles, 'en', '', $meta);
        expect($id)->not->toBeEmpty();
    } catch (Exception $e) {
        //print_r($e->getMessage());
        expect(true)->toBeFalse();
    }
})->group('users');

test('Fail at creating a user with email already in use', function () {
    $model = new User();

    $name = new Username('John', 'Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'marc+johndoe@leeroy.ca', 'Hell0W0rld!', $roles, 'en', '', $meta);
        expect(true)->toBeFalse();
    } catch (Exception $e) {
        expect(true)->toBeTrue();
    }
})->group('users');

test('Fail at creating a user with an invalid email', function () {
    $model = new User();

    $name = new Username('John', 'Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'marc+johndoe@leeroy', 'Hell0W0rld!', $roles, 'en', '', $meta);
        expect(true)->toBe(false);
    } catch (Exception $e) {
        expect(true)->toBe(true);
    }
})->group('users');

test('Fail at creating a user with an unsecure password', function () {
    $model = new User();

    $name = new Username('John', 'Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $model->create($name, 'marc+johndoe@leeroy.ca', 'hello123', $roles, 'en', '', $meta);
        expect(true)->toBe(false);
    } catch (Exception $e) {
        expect(true)->toBe(true);
    }
})->group('users');

test('Update user johndoe@leeroy.ca', function () {
    $model = new User();
    $user = $model->getByEmail('marc+johndoe@leeroy.ca');
    $name = new Username('John', 'DoeDoe');

    try {
        $result = $model->update($user->_id, $name);
        expect($result)->toBe(true);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
})->group('users');

test('Fetch a user by email', function () {
    $model = new User();
    $user = $model->getByEmail('marc+johndoe@leeroy.ca');
    expect($user)->not->toBe(null);
})->group('users');

test('Fetch a user by email and his permissions', function () {
    $model = new User();
    $user = $model->getByEmail('marc+johndoe@leeroy.ca');

    expect($user)->not->toBe(null)->and($user->permissions()->length)->not->toBe(0);
})->group('users');

test('Check if user has flag "use2fa"', function () {
    $model = new User();
    $user = $model->getByEmail('marc+johndoe@leeroy.ca');

    if ($user) {
        expect($user->has('use2fa'))->toBe(false);
    }
})->group('users');

test('Set the flag "us2fa" to true', function () {
    $model = new User();
    $user = $model->getByEmail('marc+johndoe@leeroy.ca');

    if ($user) {
        $user->flag('use2fa');
        expect($user->has('use2fa'))->toBe(true);
    }
})->group('users');

test('Get list of user with flag "use2fa"', function () {
    $users = User::flagged('use2fa');
    expect($users->length)->toBeGreaterThanOrEqual(1);
})->group('users');

test('Get list of user without flag "use2fa"', function () {
    $users = User::notFlagged('use2fa');
    expect($users->length)->toBeGreaterThanOrEqual(0);
})->group('users');

test('Delete a user', function () {
    $model = new User();

    try {
        $result = $model->removeByEmail('marc+johndoe@leeroy.ca');
        expect($result)->toBe(true);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
})->group('users');

test('Create a user and delete it by the instance', function () {
    $model = new User();

    $name = new Username('John', 'Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    try {
        $id = $model->create($name, 'marc+johndoe@leeroy.ca', 'Hell0W0rld!', $roles, 'en', '', $meta);

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
})->group('users');