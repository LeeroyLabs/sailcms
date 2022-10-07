<?php

namespace [NAME];

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Middleware\Http;
use SailCMS\Types\MiddlewareType;
use SailCMS\Middleware\Data;

class Middleware implements AppMiddleware
{
    public function type(): MiddlewareType
    {
        return MiddlewareType::HTTP;
    }

    public function process(Data $data): Data
    {
        switch ($data->event)
        {
            case Http::BeforeRender:
                // Before a round gets rendered
                break;

            case Http::AfterRender:
                // After a route was rendered
                break;

            default:
            case Http::BeforeRoute:
                // Before a route is executed
                break;

            // More when CMS is completed
        }

        return $data;
    }
}