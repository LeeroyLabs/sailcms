<?php

namespace SailCMS\Types;

use SailCMS\Collection;

class ContainerInformation
{
    public float $version = 0.0;
    public string $semver = '0.0.0';
    public string $name = '';
    public Collection $sites;

    public function __construct(string $name, array|Collection $sites, float $version, string $semver = '')
    {
        $this->name = $name;
        $this->version = $version;
        $this->semver = $semver;

        if ($sites instanceof Collection) {
            $this->sites = $sites;
        } else {
            $this->sites = new Collection($sites);
        }

        if (empty($semver)) {
            $this->semver = (string)$version . '.0';
        }
    }
}