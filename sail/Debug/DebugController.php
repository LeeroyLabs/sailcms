<?php

namespace SailCMS\Debug;

use JsonException;
use SailCMS\Contracts\AppController;
use SailCMS\Sail;

class DebugController extends AppController
{
    /**
     *
     * @throws JsonException
     *
     */
    public function handleClockWork($request): void
    {
        echo json_encode(Sail::getClockWork()?->getMetadata($request), JSON_THROW_ON_ERROR);
        die();
    }
}