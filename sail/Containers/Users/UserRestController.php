<?php

namespace SailCMS\Containers\Users;

use SailCMS\Http\Response;
use SailCMS\Contracts\AppController;

class UserRestController extends AppController
{
    private Response $response;
    private Provider $provider;

    public function __construct()
    {
        parent::__construct();
        $this->response = Response::json();
        $this->provider = new Provider();
    }

    /**
     *
     * Get list of users with given
     *
     * @return Response
     *
     */
    public function getList(): Response
    {
        $this->response->set('users', []);
        return $this->response;
    }

    public function getUser(string $param): Response
    {
        $this->response->set('user', $this->provider->getUser($param));
        return $this->response;
    }
}