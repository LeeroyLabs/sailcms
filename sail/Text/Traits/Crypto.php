<?php

namespace SailCMS\Text\Traits;

use League\Flysystem\FilesystemException;
use SailCMS\Security;
use SailCMS\Text;

trait Crypto
{
    /**
     *
     * Hash string with custom algo
     *
     * @param  string  $algo
     * @return string
     *
     */
    public function hash(string $algo = 'sha256'): string
    {
        return hash($algo, $this->internalString);
    }

    /**
     *
     * Hash string with sha1
     *
     * @return string
     *
     */
    public function sha1(): string
    {
        return hash('sha1', $this->internalString);
    }

    /**
     *
     * Hash string with sha256
     *
     * @return string
     *
     */
    public function sha256(): string
    {
        return hash('sha256', $this->internalString);
    }

    /**
     *
     * Hash string with sha512
     *
     * @return string
     *
     */
    public function sha512(): string
    {
        return hash('sha512', $this->internalString);
    }

    /**
     *
     * calculate md5 checksum
     *
     * @return string
     *
     */
    public function md5(): string
    {
        return md5($this->internalString);
    }

    /**
     *
     * Encrypt the string
     *
     * @return string
     * @throws FilesystemException
     *
     */
    public function encrypt(): string
    {
        return Security::encrypt($this->internalString);
    }

    /**
     *
     * Decrypt the string
     *
     * @return string
     * @throws FilesystemException
     * @throws \SodiumException
     *
     */
    public function decrypt(): string
    {
        return Security::decrypt($this->internalString);
    }

    /**
     *
     * Encode string to base64
     *
     * @return string
     *
     */
    public function encode(): string
    {
        return base64_encode($this->internalString);
    }

    /**
     *
     * Decode string from base64
     *
     * @return string
     */
    public function decode(): string
    {
        return base64_decode($this->internalString);
    }

    /**
     *
     * Calculate crc32 polynomial of a string
     *
     * @return string
     *
     */
    public function crc32(): string
    {
        return crc32($this->internalString);
    }
}