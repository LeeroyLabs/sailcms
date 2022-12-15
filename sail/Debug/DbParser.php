<?php

namespace SailCMS\Debug;

class DbParser
{
    public static function parseQuery(array $query): string
    {
        $out = '';
        $diffLimit = false;

        $isQuery = match ($query['operation']) {
            'find', 'findOne', 'count' => true,
            default => false
        };

        if ($isQuery) {
            $select = '*';

            if (!isset($query['projection']) || count($query['projection']) === 0) {
                if ($query['operation'] === 'count') {
                    $select = 'COUNT(*)';
                }
            } else {
                $select = '_id';

                // Project formatting (not necessarily valid SQL)
                foreach ($query['projection'] as $key => $get) {
                    if ($key !== '_id') {
                        if ((int)$get === 1) {
                            $select .= ", {$key}";
                        } else {
                            $select .= ", -{$key}";
                        }
                    }
                }
            }

            $out = "SELECT {$select} FROM `{$query['collection']}`";
        } else {
            $diffLimit = true;
            if (str_starts_with($query['operation'], 'update')) {
                $out = "UPDATE `{$query['collection']}` SET";
            } elseif (str_starts_with($query['operation'], 'delete')) {
                $out = "DELETE FROM `{$query['collection']}`";
            }
        }

        // Update statement
        $firstUpdate = false;

        if (str_starts_with($query['operation'], 'update')) {
            if (isset($query['update']['$set'])) {
                foreach ($query['update']['$set'] as $key => $value) {
                    if ($firstUpdate) {
                        $out .= ", ";
                    }

                    $firstUpdate = true;
                    $out .= self::buildWhere($key, '=', $value);
                }
            }

            if (isset($query['update']['$push'])) {
                foreach ($query['update']['$push'] as $key => $value) {
                    if ($firstUpdate) {
                        $out .= ", ";
                    }

                    $firstUpdate = true;
                    $out .= "{$value} PUSH ON {$key}";
                }
            }

            if (isset($query['update']['$pull'])) {
                foreach ($query['update']['$pull'] as $key => $value) {
                    if ($firstUpdate) {
                        $out .= ", ";
                    }

                    $firstUpdate = true;
                    $out .= "PULL {$value} OFF {$key}";
                }
            }

            if (isset($query['update']['$pop'])) {
                foreach ($query['update']['$pop'] as $key => $value) {
                    if ($firstUpdate) {
                        $out .= ", ";
                    }

                    $firstUpdate = true;

                    if ($value === -1) {
                        $out .= "POP LAST VALUE ON {$key}";
                    } else {
                        $out .= "POP FIRST VALUE ON {$key}";
                    }
                }
            }

            if (isset($query['update']['$addToSet'])) {
                foreach ($query['update']['$addToSet'] as $key => $value) {
                    if ($firstUpdate) {
                        $out .= ", ";
                    }

                    $firstUpdate = true;
                    $out .= "{$value} PUSH ON IF NOT EXIST {$key}";
                }
            }
        }

        // We have query fields
        if (count($query['query']) > 0) {
            $out .= " WHERE ";
        }

        $whereCount = 0;

        foreach ($query['query'] as $key => $value) {
            if ($whereCount > 0) {
                $out .= " AND ";
            }

            if (!str_starts_with($key, '$')) {
                // Regular item
                if (is_array($value)) {
                    $whereCount++;
                    $out .= self::parseComparisonOperators($key, $value);
                } else {
                    $whereCount++;
                    $out .= "`{$key}` = '{$value}'";
                }
            } else {
                // Special operator
                if ($key === '$or' || $key === '$nor' || $key === '$and') {
                    $out .= "(";
                    $orset = false;

                    $op = 'NOR';
                    $reverse = false;

                    if ($key === '$or') {
                        $op = 'OR';
                    } elseif ($key === '$and') {
                        $op = 'AND';
                    }

                    foreach ($value as $where) {
                        foreach ($where as $k => $v) {
                            if ($orset) {
                                $out .= " {$op} ";
                            }

                            $orset = true;
                            $out .= self::parseComparisonOperators($k, $v);
                        }
                    }

                    $out .= ")";
                }
            }
        }

        $limitOps = ['find', 'findOne', 'aggregate', 'deleteById', 'deleteOne', 'updateOne'];

        if (in_array($query['operation'], $limitOps, true)) {
            if ($diffLimit) {
                $out .= " LIMIT {$query['limit']}";
            } else {
                $out .= " LIMIT {$query['offset']},{$query['limit']}";
            }
        }

        return $out;
    }

    private static function parseComparisonOperators(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $firstKey = key($value);
        } else {
            $firstKey = $value;
        }

        if (str_starts_with($firstKey, '$')) {
            $op = match ($firstKey) {
                '$ne' => '!=',
                '$in' => 'IN',
                '$nin' => 'NOT IN',
                '$not' => 'NOT',
                '$gt' => '>',
                '$gte' => '>=',
                '$lt' => '<',
                '$lte' => '<=',
                '$exists' => 'EXISTS',
                '$regex' => 'LIKE',
                default => '='
            };

            if ($op === 'LIKE') {
                $value[$firstKey] = '%' . str_replace(['/^', '/'], '', $value[$firstKey]) . '%';
            }

            return self::buildWhere($key, $op, $value[$firstKey]);
        }

        return self::buildWhere($key, '=', $value);
    }

    private static function buildWhere(string $key, string $sign, mixed $value): string
    {
        if (is_bool($value) || is_numeric($value) || is_null($value)) {
            return "`{$key}` {$sign} {$value}";
        }

        if (is_array($value)) {
            $narr = [];

            foreach ($value as $v) {
                if (is_numeric($v) || is_bool($v) || is_null($v)) {
                    if (is_bool($v)) {
                        $narr[] = ($v) ? 'true' : 'false';
                    } elseif (is_null($v)) {
                        $narr[] = 'null';
                    } else {
                        $narr[] = $v;
                    }
                } else {
                    $narr[] = "'{$v}'";
                }
            }

            $v = implode(",", $narr);
            return "`{$key}` {$sign} ({$v})";
        }

        if (is_object($value)) {
            return "`{$key}` {$sign} [object]";
        }

        return "`{$key}` {$sign} '{$value}'";
    }
}