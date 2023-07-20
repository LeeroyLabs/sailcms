<?php

namespace SailCMS\Types;

enum LogType: string
{
    case ERROR = 'Error';
    case INFO = 'Info';
    case WARNING = 'Warning';
}