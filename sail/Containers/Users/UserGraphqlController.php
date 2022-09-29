<?php

namespace SailCMS\Containers\Users;

use SailCMS\Contracts\AppController;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;

class UserGraphqlController extends AppController
{
    private Provider $provider;

    public function __construct()
    {
        parent::__construct();

        $this->provider = new Provider();
    }

    public function getUser(mixed $obj, array $args, Context $context): ?User
    {
        return $this->provider->getUser($args['id']);
    }
}