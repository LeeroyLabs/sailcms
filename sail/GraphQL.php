<?php

namespace SailCMS;

use ArrayAccess;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL as GQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Contracts\AppContainer;
use SailCMS\Errors\GraphqlException;
use SailCMS\GraphQL\Context;
use SailCMS\GraphQL\Controllers\Assets;
use SailCMS\GraphQL\Controllers\Basics;
use SailCMS\GraphQL\Controllers\Categories;
use SailCMS\GraphQL\Controllers\Emails;
use SailCMS\GraphQL\Controllers\Entries;
use SailCMS\GraphQL\Controllers\Registers;
use SailCMS\GraphQL\Controllers\Roles;
use SailCMS\GraphQL\Controllers\Users;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\GraphQL as MGQL;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\UserMeta;

class GraphQL
{
    private static array $queries = [];
    private static array $mutations = [];
    private static array $resolvers = [];

    public static array $querySchemaParts = [];
    public static array $mutationSchemaParts = [];
    public static array $typeSchemaParts = [];

    /**
     *
     * Add a Query Resolver
     *
     * @param string $operationName
     * @param string $className
     * @param string $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addQueryResolver(string $operationName, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== static::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a query from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLQuery($operationName, $className, $method, $class);
        static::$queries[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Mutation Resolver to the Schema
     *
     * @param string $operationName
     * @param string $className
     * @param string $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addMutationResolver(string $operationName, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== static::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a mutation from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLMutation($operationName, $className, $method, $class);
        static::$mutations[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Resolver to the Schema
     *
     * @param string $type
     * @param string $className
     * @param string $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addResolver(string $type, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== static::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a resolver from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLResolver($type, $className, $method, $class);
        static::$resolvers[$type] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add parts of the schema for queries
     *
     * @param string $content
     * @return void
     *
     */
    public static function addQuerySchema(string $content): void
    {
        static::$querySchemaParts[] = $content;
    }

    /**
     *
     * Add parts of the schema for mutation
     *
     * @param string $content
     * @return void
     *
     */
    public static function addMutationSchema(string $content): void
    {
        static::$mutationSchemaParts[] = $content;
    }

    /**
     *
     * Add parts of the schema for custom types
     *
     * @param string $content
     * @return void
     *
     */
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

            if (env('environment', 'dev') === 'dev') {
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
            } else {
                $ast = require Sail::getWorkingDirectory() . '/storage/cache/graphql.ast';
                $document = AST::fromArray($ast);
            }

            $schema = BuildSchema::build($document);

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true, setting('graphql.depthLimit', 5), JSON_THROW_ON_ERROR); // N+1 protection
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
                $mresult->data->errors = [];

                foreach ($errors as $error) {
                    $mresult->data->errors = [
                        [
                            'message' => $error->getMessage(),
                            'extensions' => ['category' => 'internal'],
                            'locations' => [['line' => $error->getLine(), 'column' => 1]],
                            'file' => $error->getFile(),
                            'stack' => debug_backtrace(),
                            'path' => ['']
                        ]
                    ];
                }
            }

            return $mresult->data;
        } catch (InvariantViolation $e) {
            echo $e->getMessage();
            return null;
        }
    }

    /**
     *
     * Setup all system graphQL calls
     *
     * @throws GraphqlException
     *
     */
    private static function initSystem(): void
    {
        // General
        static::addQueryResolver('version', Basics::class, 'version');

        // User
        static::addQueryResolver('user', Users::class, 'user');
        static::addQueryResolver('users', Users::class, 'users');
        static::addQueryResolver('resendValidationEmail', Users::class, 'resendValidationEmail');
        static::addMutationResolver('createUser', Users::class, 'createUser');
        static::addMutationResolver('createAdminUser', Users::class, 'createAdminUser');
        static::addMutationResolver('updateUser', Users::class, 'updateUser');
        static::addMutationResolver('deleteUser', Users::class, 'deleteUser');
        static::addMutationResolver('validateAccount', Users::class, 'validateAccount');

        // Authentication
        static::addQueryResolver('authenticate', Users::class, 'authenticate');
        static::addQueryResolver('verifyAuthenticationToken', Users::class, 'verifyAuthenticationToken');
        static::addQueryResolver('verifyTFA', Users::class, 'verifyTFA');
        static::addQueryResolver('forgotPassword', Users::class, 'forgotPassword');
        static::addQueryResolver('userWithToken', Users::class, 'userWithToken');
        static::addMutationResolver('changePassword', Users::class, 'changePassword');

        // Roles & ACL
        static::addQueryResolver('role', Roles::class, 'role');
        static::addQueryResolver('roles', Roles::class, 'roles');
        static::addQueryResolver('acls', Roles::class, 'acls');
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

        // Entries
        static::addQueryResolver('homepageEntry', Entries::class, 'homepageEntry');

        static::addQueryResolver('entryTypes', Entries::class, 'entryTypes');
        static::addQueryResolver('entryType', Entries::class, 'entryType');
        static::addMutationResolver('createEntryType', Entries::class, 'createEntryType');
        static::addMutationResolver('updateEntryType', Entries::class, 'updateEntryType');
        static::addMutationResolver('deleteEntryType', Entries::class, 'deleteEntryType');

        static::addQueryResolver('entries', Entries::class, 'entries');
        static::addQueryResolver('entry', Entries::class, 'entry');
        static::addMutationResolver('createEntry', Entries::class, 'createEntry');
        static::addMutationResolver('updateEntry', Entries::class, 'updateEntry');
        static::addMutationResolver('deleteEntry', Entries::class, 'deleteEntry');

        static::addQueryResolver('entryLayout', Entries::class, 'entryLayout');
        static::addQueryResolver('entryLayouts', Entries::class, 'entryLayouts');
        static::addMutationResolver('createEntryLayout', Entries::class, 'createEntryLayout');
        static::addMutationResolver('updateEntryLayoutSchema', Entries::class, 'updateEntryLayoutSchema');
        static::addMutationResolver('updateEntryLayoutSchemaKey', Entries::class, 'updateEntryLayoutSchemaKey');
        static::addMutationResolver('deleteEntryLayout', Entries::class, 'deleteEntryLayout');

        // Register
        static::addQueryResolver('registeredExtensions', Registers::class, 'registeredExtensions');

        // Categories
        static::addQueryResolver('category', Categories::class, 'category');
        static::addQueryResolver('categoryBySlug', Categories::class, 'categoryBySlug');
        static::addQueryResolver('categoryFullTree', Categories::class, 'categoryFullTree');
        static::addQueryResolver('categoryEntries', Categories::class, 'categoryEntries');
        static::addMutationResolver('createCategory', Categories::class, 'createCategory');
        static::addMutationResolver('updateCategory', Categories::class, 'updateCategory');
        static::addMutationResolver('updateCategoryOrders', Categories::class, 'updateCategoryOrders');
        static::addMutationResolver('deleteCategory', Categories::class, 'deleteCategory');
        static::addMutationResolver('deleteCategoryBySlug', Categories::class, 'deleteCategoryBySlug');

        // Misc calls
        // TODO: GET LOGS (from file or db)

        // Types and Resolvers
        static::addResolver('User', Users::class, 'resolver');
    }

    /**
     *
     * Resolve everything
     *
     * @param               $objectValue
     * @param array $args
     * @param               $contextValue
     * @param ResolveInfo $info
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