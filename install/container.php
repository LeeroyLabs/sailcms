<?php

namespace [NAME];

use SailCMS\Collection;
use SailCMS\GraphQL;
use SailCMS\Types\ContainerInformation;
use SailCMS\Contracts\AppContainer;

class Container extends AppContainer
{
    public function info(): ContainerInformation
    {
        return new ContainerInformation('[NAME]', 'your description', 1.0, '1.0.0');
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
        // Schema
        GraphQL::addQuerySchema(file_get_contents(__DIR__ . '/Graphql/queries.graphql'));
        GraphQL::addMutationSchema(file_get_contents(__DIR__ . '/Graphql/mutations.graphql'));
        GraphQL::addTypeSchema(file_get_contents(__DIR__ . '/Graphql/types.graphql'));

        // GraphQL additions
    }

    public function middleware(): void
    {
        // Register your middlewares
    }

    public function events(): void
    {
        // register for events
    }

    public function cli(): Collection
    {
        return new Collection([]);
    }

    public function permissions(): Collection
    {
        return new Collection([]);
    }
}