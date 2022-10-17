<?php

namespace SailCMS;

use \GraphQL\GraphQL as GQL;
use \GraphQL\Type\Definition\ObjectType;
use \GraphQL\Type\Schema;
use \GraphQL\Error\InvariantViolation;
use JsonException;
use \SailCMS\GraphQL\Context;
use \SailCMS\GraphQL\Query;
use \SailCMS\Middleware\Data;
use \SailCMS\Middleware\GraphQL as MGQL;
use \SailCMS\Types\MiddlewareType;

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
     * Initialize and run queries
     *
     * @return string
     * @throws JsonException
     *
     */
    public static function init(): string
    {
        $fields = [];

        foreach (static::$queries as $query) {
            $fields[$query->name] = [
                'type' => $query->returns,
                'args' => $query->args,
                'resolve' => fn($rootValue, array $args, $context) => call_user_func($query->resolver, $rootValue, $args, $context)
            ];
        }

        // Assemble
        $queries = new ObjectType(['name' => 'Query', 'fields' => $fields]);

        static::$schema = new Schema(['query' => $queries]);

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

            return json_encode($mresult->data, JSON_THROW_ON_ERROR);
        } catch (InvariantViolation $e) {
            echo $e->getMessage();
            return json_encode([], JSON_THROW_ON_ERROR);
        }
    }
}