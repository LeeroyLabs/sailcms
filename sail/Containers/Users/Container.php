<?php

namespace SailCMS\Containers\Users;

use SailCMS\Contracts\AppContainer;
use SailCMS\GraphQL\Query;
use SailCMS\GraphQL;
use SailCMS\Containers\Users\GraphQL\Types;
use SailCMS\Types\ContainerInformation;
use SailCMS\GraphQL\Types as GTypes;

class Container extends AppContainer
{
    public function info(): ContainerInformation
    {
        return new ContainerInformation(name: 'User Core Container', sites: ['*'], version: 3.0, semver: '3.0.0');
    }

    public function routes(): void
    {
        // Read
        $this->router->redirect($this->currentApiURL . '/users', $this->currentApiURL . '/users/1');
        $this->router->get($this->currentApiURL . '/users/:num', 'en', UserRestController::class, 'getList');
        $this->router->get($this->currentApiURL . '/users/:any', 'en', UserRestController::class, 'getUser');
        // Write
    }

    public function graphql(): void
    {
        GraphQL::addQuery(
            Query::init('user', [UserGraphqlController::class, 'getUser'], ['id' => GTypes::string()], Types::user())
        );
    }

    public function configureSearch(): void
    {
        // TODO: Implement configureSearch() method.
    }

    public function middleware(): void
    {
        // TODO: Implement middleware() method.
    }

    public function cli(): array
    {
        return [];
    }
}