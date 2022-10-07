<?php

namespace SailCMS\Containers\Users;

use SailCMS\Http\Response;
use SailCMS\Contracts\AppController;

class UserRestController extends AppController
{
    private Provider $provider;

    public function __construct()
    {
        parent::__construct();
        $this->provider = new Provider();
    }

    /**
     *
     * Get list of users with given
     *
     */
    public function getList(): void
    {
        $this->response->setType('json');
        $this->response->set('users', []);
    }

    public function getUser(string $param): void
    {
        $this->response->setType('json');
        $this->response->set('user', $this->provider->getUser($param));
    }
}