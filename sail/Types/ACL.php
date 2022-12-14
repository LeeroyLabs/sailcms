<?php

namespace SailCMS\Types;

use SailCMS\Text;

class ACL
{
    public readonly string $name;
    public readonly string $providedName;
    public readonly string $value;
    public readonly string $category;
    public readonly ACLType $type;

    public function __construct(string $name, ACLType $type)
    {
        $this->value = $type->value . '_' . Text::snakeCase(Text::deburr($name));

        switch ($type) {
            case ACLType::WRITE:
                $shortType = 'W';
                break;

            default:
            case ACLType::READ:
                $shortType = 'R';
                break;

            case ACLType::READ_WRITE:
                $shortType = 'RW';
                break;
        }

        $this->type = $type;
        $this->providedName = strtolower(Text::deburr($name));
        $this->category = $shortType;
        $this->name = strtoupper(Text::deburr($name)) . '_' . $shortType;
    }
}