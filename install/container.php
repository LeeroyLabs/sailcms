<?php

namespace [NAME];

use SailCMS\Types\ContainerInformation;
use SailCMS\Contracts\AppContainer;

class Container extends AppContainer
{
    public function info(): ContainerInformation
    {
        return new ContainerInformation(name: '[NAME]', sites: ['*'], version: 1.0, semver: '1.0.0');
    }

    public function routes(): void
    {
        // Your routes
    }

    public function configureSearch(): void
    {
        // Anything to configure search
    }

    public function graphql(): void
    {
        // GraphQL additions
    }

    public function middleware(): void
    {
        // Register your middlewares
    }

    public function cli(): array
    {
        return [];
    }
}