<?php

namespace SailCMS\GraphQL;

use GraphQL\Type\Definition\Type;

class Types
{
    /**
     *
     * ID / Optional ID
     *
     * @param bool $optional
     * @return Type
     *
     */
    public static function ID(bool $optional = false): Type
    {
        if (!$optional) {
            return Type::nonNull(Type::id());
        }

        return Type::id();
    }

    /**
     *
     * String / Optional String
     *
     * @param bool $optional
     * @return Type
     *
     */
    public static function string(bool $optional = false): Type
    {
        if (!$optional) {
            return Type::nonNull(Type::string());
        }

        return Type::string();
    }

    /**
     *
     * Integer / Optional Integer
     *
     * @param bool $optional
     * @return Type
     *
     */
    public static function int(bool $optional = false): Type
    {
        if (!$optional) {
            return Type::nonNull(Type::int());
        }

        return Type::int();
    }

    /**
     *
     * Float / Optional Float
     *
     * @param bool $optional
     * @return Type
     *
     */
    public static function float(bool $optional = false): Type
    {
        if (!$optional) {
            return Type::nonNull(Type::float());
        }

        return Type::float();
    }

    /**
     *
     * Boolean / Optional Boolean
     *
     * @param bool $optional
     * @return Type
     *
     */
    public static function boolean(bool $optional = false): Type
    {
        if (!$optional) {
            return Type::nonNull(Type::boolean());
        }

        return Type::boolean();
    }
}