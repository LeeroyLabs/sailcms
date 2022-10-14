<?php

namespace SailCMS\Types;

enum ACLType: string
{
    case WRITE = 'write';
    case READ = 'read';
    case READ_WRITE = 'readwrite';
}