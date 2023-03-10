<?php

namespace SailCMS\Rest\Controllers;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Contracts\AppController;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Tfa;
use SailCMS\Models\User;
use SailCMS\Security\TwoFactorAuthentication;
use SailCMS\Types\MetaSearch;
use SailCMS\Types\UserSorting;
use SailCMS\Types\UserTypeSearch;
use SodiumException;

class Users extends AppController
{
    private User $user;

    public function __construct(User $user)
    {
        parent::__construct();
        $this->response->setType('json');
        $this->user = $user;
    }

    /**
     *
     * Get single user by id
     *
     * @param  string  $id
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     * @throws JsonException
     *
     */
    public function user(string $id): void
    {
        $user = $this->user->getById($id);

        if ($user) {
            $user->meta = $user->meta->simplify();
            $user->permissions = $user->permissions();
        }

        foreach ($user->toJSON(true) as $key => $value) {
            $this->response->set($key, $value);
        }
    }

    /**
     *
     * User listing
     *
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function users(): void
    {
        $page = $this->request->get('page', false, 1);
        $limit = $this->request->get('limit', false, 25);
        $search = $this->request->get('search', false, '');
        $sorting = $this->request->get('sort', false, null);
        $order = $this->request->get('order', false, 'asc');
        $type = $this->request->get('usertype', false, null);
        $typeExcept = $this->request->get('usertype_except', false, '');
        $status = $this->request->get('status', false, null);
        $validated = $this->request->get('validated', false, null);
        $meta = $this->request->get('meta_key', false, null);

        if ($sorting !== null) {
            $sorting = new UserSorting($sorting, $order);
        } else {
            $sorting = new UserSorting('name.full', 'asc');
        }

        if ($type !== null) {
            $type = new UserTypeSearch($type, $typeExcept);
        }

        if ($meta) {
            $metaSearch = new MetaSearch($meta, $this->request->get('meta_value', false, ''));
        }

        $users = $this->user->getList(
            $page, $limit, $search, $sorting, $type, $metaSearch ?? null, $status, $validated
        );

        foreach ($users->list as $user) {
            $user->meta = $user->meta->simplify();
            $user->permissions = $user->permissions();
        }

        $this->response->set('pagination', $users->pagination);
        $this->response->set('list', $users->list);
    }

    /**
     *
     * Run level 1 authentication
     *
     * @return void
     * @throws DatabaseException
     *
     */
    public function authenticate(): void
    {
        $email = $this->request->post('email', false, '');
        $pass = $this->request->post('password', false, '');

        // Any missing, fail right away
        if ($email === '' || $pass === '') {
            $this->response->set('user_id', null);
            $this->response->set('message', 'error');
            return;
        }

        $authentication = $this->user->verifyUserPass($email, $pass);
        $this->response->set('user_id', $authentication->user_id);
        $this->response->set('message', $authentication->message);
    }

    /**
     *
     * Authenticate with Token
     *
     * @return void
     * @throws DatabaseException
     * @throws JsonException
     *
     */
    public function authenticateWithToken(): void
    {
        $user = User::$currentUser ?? null;

        if ($user) {
            $user->meta = $user->meta->simplify();
            $user->permissions = $user->permissions();
        }

        foreach ($user->toJSON(true) as $key => $value) {
            $this->response->set($key, $value);
        }
    }

    /**
     *
     * Verify authentication key
     *
     * @param  string  $token
     * @return void
     * @throws DatabaseException
     * @throws JsonException
     *
     */
    public function verifyAuthentication(string $token): void
    {
        $user = $this->user->verifyTemporaryToken($token);

        if ($user) {
            $user->meta = $user->meta->simplify();
            $user->permissions = $user->permissions();
        }

        foreach ($user->toJSON(true) as $key => $value) {
            $this->response->set($key, $value);
        }
    }

    /**
     *
     * Verify TFA code
     *
     * @param  string  $userId
     * @param  string  $code
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function verifyTFA(string $userId, string $code): void
    {
        $model = new Tfa();
        $tfa = new TwoFactorAuthentication();
        $setup = $model->getForUser($userId);

        if ($setup) {
            $this->response->set('tfa', $tfa->validate($setup->secret, $code));
            return;
        }

        $this->response->set('tfa', false);
    }

    /**
     *
     * Forgot password
     *
     * @return void
     * @throws DatabaseException
     * @throws EmailException
     * @throws FileException
     *
     */
    public function forgotPassword(): void
    {
        $email = $this->request->post('email', false, '');

        if ($email === '') {
            $this->response->set('forgot_password', false);
            return;
        }

        $this->response->set('forgot_password', User::forgotPassword($email));
    }

    /**
     *
     * Change password
     *
     * @return void
     * @throws DatabaseException
     *
     */
    public function changePassword(): void
    {
        $code = $this->request->post('code', false, '');
        $password = $this->request->post('password', false, '');
        $result = User::changePassword($code, $password);
        $this->response->set('change_password', $result);
    }
}