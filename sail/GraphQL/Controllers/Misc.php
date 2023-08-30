<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Role;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\UI;

class Misc
{
    /**
     *
     * Get navigation for use on the frontend
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function navigationElements(mixed $obj, Collection $args, Context $context): Collection
    {
        return UI::getNavigationElements();
    }

    /**
     *
     * Get settings element for use on the frontend
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function settingsElements(mixed $obj, Collection $args, Context $context): Collection
    {
        return UI::getSettingsElements();
    }

    /**
     *
     * Get the handshake key that enables secure 3rd party UI extensions
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws DatabaseException
     *
     */
    public function handshakeKey(mixed $obj, Collection $args, Context $context): string
    {
        if (User::$currentUser) {
            // Signed in... good sign
            $role = Role::getHighestLevel(User::$currentUser->roles);

            if ($role >= env('EXTENSION_MINIMUM_LEVEL_REQUIRED', 100)) {
                return env('EXTENSION_HANDSHAKE_KEY', '');
            }
        }

        return '';
    }

    /**
     *
     * List all available templates with name and filename
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function availableTemplates(mixed $obj, Collection $args, Context $context): Collection
    {
        $files = glob(Sail::getWorkingDirectory() . '/templates/' . Sail::siteId() . '/*.twig');
        $list = [];

        foreach ($files as $file) {
            $fileObject = new \SplFileObject($file);
            $line = $fileObject->current();
            $fileObject = null; // close

            $re = '/{#(.*)#}/m';
            preg_match_all($re, $line, $matches, PREG_SET_ORDER, 0);
            $file = str_replace('.twig', '', basename($file));

            if (empty($matches[0])) {
                $name = ucfirst($file);
            } else {
                $name = $matches[0][1];
            }

            $list[] = [
                'name' => trim($name),
                'filename' => $file
            ];
        }

        return new Collection($list);
    }
}