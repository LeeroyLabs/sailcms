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
        $this->value = Text::from($type->value)->concat(
            Text::from($name)->deburr()->snake()->value(),
            '_'
        )->value();

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
        $this->providedName = Text::from($name)->deburr()->lower()->value();
        $this->category = $shortType;
        $this->name = Text::from($name)->deburr()->upper()->concat($shortType, '_')->value();
    }
}