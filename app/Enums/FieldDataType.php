<?php

namespace App\Enums;

enum FieldDataType: string
{
    case String = 'string';
    case Number = 'number';
    case Object = 'object';
    case ArrayString = 'array_string';
    case ArrayObject = 'array_object';
}
