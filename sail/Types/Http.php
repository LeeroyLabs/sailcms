<?php

namespace SailCMS\Types;

enum Http: string
{
    case GET = 'get';
    case POST = 'post';
    case PUT = 'put';
    case DELETE = 'delete';
}