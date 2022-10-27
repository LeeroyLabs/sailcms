<?php

namespace SailCMS\Types;

// TODO do we add delete ?
enum ACLType: string
{
    case WRITE = 'write';
    case READ = 'read';
    case READ_WRITE = 'readwrite';
}