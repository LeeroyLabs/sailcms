<?php

namespace SailCMS\Types;

enum MiddlewareType: string
{
    case HTTP = 'http';
    case GRAPHQL = 'graphql';
}