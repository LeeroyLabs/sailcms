<?php

namespace SailCMS\Test\Response;

class GraphQLResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?object $data = null
    ) {
    }
}