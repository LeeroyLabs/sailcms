<?php

namespace SailCMS\GraphQL;

use SailCMS\Http\Request;

class Context
{
    private string $_ip;
    private string $_token = '';

    public function __construct()
    {
        $request = new Request();
        $this->_ip = $request->ipAddress();

        $headers = getallheaders();

        foreach ($headers as $key => $value) {
            if ($key === 'x-access-token') {
                $this->_token = $value;
            }
        }
    }

    /**
     *
     * Get header x-access-token
     *
     * @return string
     *
     */
    public function token(): string
    {
        return $this->_token ?? '';
    }

    /**
     *
     * Get IP
     *
     * @return string
     *
     */
    public function ipAddress(): string
    {
        return $this->_ip;
    }
}