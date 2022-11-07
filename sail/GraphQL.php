<?php

namespace SailCMS;

use ArrayAccess;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL as GQL;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\BuildSchema;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\GraphQL\Context;
use SailCMS\GraphQL\Controllers\Assets;
use SailCMS\GraphQL\Controllers\Basics;
use SailCMS\GraphQL\Controllers\Emails;
use SailCMS\GraphQL\Controllers\Roles;
use SailCMS\GraphQL\Controllers\Users;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\GraphQL as MGQL;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\UserMeta;
use GraphQL\Utils\AST;
use GraphQL\Language\Parser;

class GraphQL
{
    private static array $queries = [];
    private static array $mutations = [];
    private static array $resolvers = [];

    private static array $querySchemaParts = [];
    private static array $mutationSchemaParts = [];
    private static array $typeSchemaParts = [];

    /**
     *
     * Add a Query Resolver
     *
     * @param  string  $operationName
     * @param  string  $className
     * @param  string  $method
     * @return void
     */
    public static function addQueryResolver(string $operationName, string $className, string $method): void
    {
        static::$queries[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Mutation Resolver to the Schema
     *
     * @param  string  $operationName
     * @param  string  $className
     * @param  string  $method
     * @return void
     */
    public static function addMutationResolver(string $operationName, string $className, string $method): void
    {
        static::$mutations[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Resolver to the Schema
     *
     * @param  string  $type
     * @param  string  $className
     * @param  string  $method
     * @return void
     *
     */
    public static function addResolver(string $type, string $className, string $method): void
    {
        static::$resolvers[$type] = (object)['class' => $className, 'method' => $method];
    }

    public static function addQuerySchema(string $content): void
    {
        static::$querySchemaParts[] = $content;
    }

    public static function addMutationSchema(string $content): void
    {
        static::$mutationSchemaParts[] = $content;
    }

    public static function addTypeSchema(string $content): void
    {
        static::$typeSchemaParts[] = $content;
    }

    /**
     *
     * Initialize and run queries
     *
     * @return mixed
     * @throws JsonException
     * @throws SyntaxError
     * @throws FilesystemException
     *
     */
    public static function init(): mixed
    {
        static::initSystem();

        try {
            $pathAST = 'cache://graphql.ast';

            if ($_ENV['ENVIRONMENT'] === 'dev' || !Filesystem::manager()->fileExists($pathAST)) {
                // Load all files for the schema
                $queries = [];
                $mutations = [];
                $types = [];

                foreach (static::$querySchemaParts as $file) {
                    $queries[] = file_get_contents($file);
                }

                foreach (static::$mutationSchemaParts as $file) {
                    $mutations[] = file_get_contents($file);
                }

                foreach (static::$typeSchemaParts as $file) {
                    $types[] = file_get_contents($file);
                }

                $locales = Locale::getAvailableLocales();
                $localeString = '';

                foreach ($locales as $locale) {
                    $localeString .= "{$locale}: String\n";
                }

                $schemaContent = file_get_contents(__DIR__ . '/GraphQL/schema.graphql');
                $schemaContent = str_replace(
                    [
                        '#{CUSTOM_QUERIES}#',
                        '#{CUSTOM_MUTATIONS}#',
                        '#{CUSTOM_TYPES}#',
                        '#{CUSTOM_FLAGS}#',
                        '#{CUSTOM_META}#',
                        '#{CUSTOM_META_INPUT}#',
                        '#{LOCALE_FIELDS}#'
                    ],
                    [
                        implode("\n", $queries),
                        implode("\n", $mutations),
                        implode("\n", $types),
                        UserMeta::getAvailableFlags(),
                        UserMeta::getAvailableMeta(),
                        UserMeta::getAvailableMeta(true),
                        $localeString
                    ],
                    $schemaContent
                );

                // Parse schema
                $document = Parser::parse($schemaContent);

                // Save AST format
                Filesystem::manager()->write($pathAST, "<?php\nreturn " . var_export(AST::toArray($document), true) . ";\n");
            } else {
                $ast = require Sail::getWorkingDirectory() . '/storage/cache/graphql.ast';
                $document = AST::fromArray($ast);
            }

            $schema = BuildSchema::build($document);

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

            $result = GQL::executeQuery($schema, $data['query'], null, new Context(), $data['variables'], null, [static::class, 'resolvers']);
            $errors = $result->errors;
            $serializableResult = (object)$result->toArray();

            if (!empty($input['query'])) {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterQuery, data: $serializableResult));
            } else {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterMutation, data: $serializableResult));
            }

            if ($errors) {
                foreach ($errors as $error) {
                    $mresult->data->errors = [
                        'message' => $error->getMessage(),
                        'extensions' => ['category' => 'internal'],
                        'locations' => [['line' => $error->getLine(), 'column' => 1]],
                        'file' => $error->getFile(),
                        'stack' => debug_backtrace(),
                        'path' => ['']
                    ];
                }
            }

            return $mresult->data;
        } catch (InvariantViolation $e) {
            echo $e->getMessage();
            return null;
        }
    }

    private static function initSystem(): void
    {
        // General
        static::addQueryResolver('version', Basics::class, 'version');

        // User
        static::addQueryResolver('user', Users::class, 'user');
        static::addQueryResolver('users', Users::class, 'users');
        static::addMutationResolver('createUser', Users::class, 'createUser');
        static::addMutationResolver('createAdminUser', Users::class, 'createAdminUser');
        static::addMutationResolver('updateUser', Users::class, 'updateUser');
        static::addMutationResolver('deleteUser', Users::class, 'deleteUser');
        static::addMutationResolver('validateAccount', Users::class, 'validateAccount');

        // Authentication
        static::addQueryResolver('authenticate', Users::class, 'authenticate');
        static::addQueryResolver('verifyAuthenticationToken', Users::class, 'verifyAuthenticationToken');
        static::addQueryResolver('verifyTFA', Users::class, 'verifyTFA');

        // Roles & ACL
        static::addQueryResolver('role', Roles::class, 'role');
        static::addQueryResolver('roles', Roles::class, 'roles');
        static::addMutationResolver('deleteRole', Roles::class, 'delete');

        // Assets
        static::addQueryResolver('asset', Assets::class, 'asset');
        static::addQueryResolver('assets', Assets::class, 'assets');
        static::addMutationResolver('uploadAsset', Assets::class, 'createAsset');
        static::addMutationResolver('updateAssetTitle', Assets::class, 'updateAssetTitle');
        static::addMutationResolver('deleteAsset', Assets::class, 'deleteAsset');
        static::addMutationResolver('transformAsset', Assets::class, 'transformAsset');

        // Emails
        static::addQueryResolver('email', Emails::class, 'email');
        static::addQueryResolver('emails', Emails::class, 'emails');
        static::addMutationResolver('createEmail', Emails::class, 'createEmail');
        static::addMutationResolver('updateEmail', Emails::class, 'updateEmail');
        static::addMutationResolver('deleteEmail', Emails::class, 'deleteEmail');
        static::addMutationResolver('deleteEmailBySlug', Emails::class, 'deleteEmailBySlug');

        // Types and Resolvers
        static::addResolver('User', Users::class, 'resolver');
    }

    /**
     *
     * Resolve everything
     *
     * @param               $objectValue
     * @param  array        $args
     * @param               $contextValue
     * @param  ResolveInfo  $info
     * @return ArrayAccess|mixed
     *
     */
    public static function resolvers($objectValue, array $args, $contextValue, ResolveInfo $info): mixed
    {
        $fieldName = $info->fieldName;
        $type = $info->parentType->name;
        $property = null;

        if (is_array($objectValue) || $objectValue instanceof ArrayAccess) {
            if (isset($objectValue[$fieldName])) {
                $property = $objectValue[$fieldName];
            }
        } elseif (is_object($objectValue)) {
            if (isset($objectValue->{$fieldName})) {
                if (is_object($objectValue->{$fieldName}) && get_class($objectValue->{$fieldName}) === Collection::class) {
                    $property = $objectValue->{$fieldName}->unwrap();
                } else {
                    $property = $objectValue->{$fieldName};
                }
            }
        }

        if (isset($property)) {
            foreach (static::$resolvers as $name => $resolver) {
                if ($type === $name) {
                    return (new $resolver->class())->{$resolver->method}($objectValue, new Collection($args), $contextValue, $info);
                }
            }

            return $property;
        }

        if ($type === 'Query') {
            foreach (static::$queries as $name => $query) {
                if ($fieldName === $name) {
                    return (new $query->class())->{$query->method}($objectValue, new Collection($args), $contextValue);
                }
            }

            throw new \RuntimeException("Cannot find resolver for Query '{$fieldName}'");
        }

        if ($type === 'Mutation') {
            foreach (static::$mutations as $name => $mutation) {
                if ($fieldName === $name) {
                    return (new $mutation->class())->{$mutation->method}($objectValue, new Collection($args), $contextValue);
                }
            }

            throw new \RuntimeException("Cannot find resolver for Mutation '{$fieldName}'");
        }

        // One last try on the resolvers
        foreach (static::$resolvers as $name => $resolver) {
            if ($type === $name) {
                return (new $resolver->class())->{$resolver->method}($objectValue, new Collection($args), $contextValue, $info);
            }
        }

        return $objectValue;
    }
}