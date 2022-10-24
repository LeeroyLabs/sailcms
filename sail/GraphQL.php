<?php

namespace SailCMS;

use GraphQL\GraphQL as GQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Schema;
use GraphQL\Error\InvariantViolation;
use JsonException;
use SailCMS\GraphQL\Context;
use SailCMS\GraphQL\Controllers\Basics;
use SailCMS\GraphQL\Controllers\Users;
use SailCMS\GraphQL\Mutation;
use SailCMS\GraphQL\Query;
use SailCMS\GraphQL\Types\User;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\GraphQL as MGQL;
use SailCMS\Types\MiddlewareType;

class GraphQL
{
    private static array $queries = [];
    private static array $mutations = [];
    private static Schema $schema;

    /**
     *
     * Add a Query to the Schema
     *
     * @param  Query  $query
     * @return void
     *
     */
    public static function addQuery(Query $query): void
    {
        $q = (object)[
            'name' => $query->name,
            'args' => $query->args,
            'returns' => $query->returnValue,
            'resolver' => $query->resolver
        ];

        static::$queries[] = $q;
    }

    /**
     *
     * Add a Mutation fo the Schema
     *
     * @param  Mutation  $mutation
     * @return void
     *
     */
    public static function addMutation(Mutation $mutation): void
    {
        $m = (object)[
            'name' => $mutation->name,
            'args' => $mutation->args,
            'returns' => $mutation->returnValue,
            'resolver' => $mutation->resolver
        ];

        static::$mutations[] = $m;
    }

    /**
     *
     * Initialize and run queries
     *
     * @return string
     * @throws JsonException
     *
     */
    public static function init(): mixed
    {
        $fields = [];
        $mfields = [];

        static::initSystem();

        foreach (static::$queries as $query) {
            $fields[$query->name] = [
                'type' => $query->returns,
                'args' => $query->args,
                'resolve' => fn($rootValue, array $args, $context) => call_user_func($query->resolver, $rootValue, $args, $context)
            ];
        }

        foreach (static::$mutations as $mutation) {
            $mfields[$mutation->name] = [
                'type' => $mutation->returns,
                'args' => $mutation->args,
                'resolve' => fn($rootValue, array $args, $context) => call_user_func($mutation->resolver, $rootValue, $args, $context)
            ];
        }

        // Assemble
        $queries = new ObjectType(['name' => 'Query', 'fields' => $fields]);

        if (count($mfields) > 0) {
            $mutations = new ObjectType(['name' => 'Mutation', 'fields' => $mfields]);
            static::$schema = new Schema(['query' => $queries, 'mutation' => $mutations]);
        } else {
            static::$schema = new Schema(['query' => $queries]);
        }

        try {
            static::$schema->assertValid();

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true, $_ENV['SETTINGS']->get('graphql.depthLimit'), JSON_THROW_ON_ERROR); // N+1 protection
            $query = $input['query'] ?? $input['mutation'] ?? '';
            $variableValues = $input['variables'] ?? null;

            $data = ['query' => $query, 'variables' => $variableValues];

            if (!empty($input['query'])) {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::BeforeQuery, data: $data));
            } else {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::BeforeMutation, data: $data));
            }

            $data = $mresult->data;

            $result = GQL::executeQuery(static::$schema, $data['query'], null, new Context(), $data['variables']);
            $serializableResult = $result->toArray();

            if (!empty($input['query'])) {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterQuery, data: $result));
            } else {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterMutation, data: $result));
            }

            return $mresult->data;
        } catch (InvariantViolation $e) {
            echo $e->getMessage();
            return null;
        }
    }

    private static function initSystem(): void
    {
        // Basic/Misc things
        static::addQuery(
            Query::init('version', [Basics::class, 'version'], [], StringType::string())
        );

        // User related
        static::addQuery(
            Query::init('user', [Users::class, 'user'], ['id' => StringType::string()], User::user())
        );
    }
}