<?php

namespace SailCMS;

use ArrayAccess;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL as GQL;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryDepth;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Contracts\AppContainer;
use SailCMS\Contracts\Castable;
use SailCMS\Errors\GraphqlException;
use SailCMS\GraphQL\Context;
use SailCMS\GraphQL\Controllers\Assets;
use SailCMS\GraphQL\Controllers\Basics;
use SailCMS\GraphQL\Controllers\Categories;
use SailCMS\GraphQL\Controllers\Emails;
use SailCMS\GraphQL\Controllers\Entries;
use SailCMS\GraphQL\Controllers\Groups;
use SailCMS\GraphQL\Controllers\Misc;
use SailCMS\GraphQL\Controllers\Navigation;
use SailCMS\GraphQL\Controllers\Queue;
use SailCMS\GraphQL\Controllers\Registers;
use SailCMS\GraphQL\Controllers\Roles;
use SailCMS\GraphQL\Controllers\Users;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\GraphQL as MGQL;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\UserMeta;

final class GraphQL
{
    private static array $queries = [];
    private static array $mutations = [];
    private static array $resolvers = [];
    private static array $typeResolvers = [];

    public static array $querySchemaParts = [];
    public static array $mutationSchemaParts = [];
    public static array $typeSchemaParts = [];

    /**
     *
     * Add a Query Resolver
     *
     * @param  string  $operationName
     * @param  string  $className
     * @param  string  $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addQueryResolver(string $operationName, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== self::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a query from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLQuery($operationName, $className, $method, $class);
        self::$queries[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Mutation Resolver to the Schema
     *
     * @param  string  $operationName
     * @param  string  $className
     * @param  string  $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addMutationResolver(string $operationName, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== self::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a mutation from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLMutation($operationName, $className, $method, $class);
        self::$mutations[$operationName] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add a Resolver to the Schema
     *
     * @param  string  $type
     * @param  string  $className
     * @param  string  $method
     * @return void
     * @throws GraphqlException
     *
     */
    public static function addResolver(string $type, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== self::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a resolver from anything other than the graphql method in an AppContainer.', 0403);
        }

        Register::registerGraphQLResolver($type, $className, $method, $class);
        self::$resolvers[$type] = (object)['class' => $className, 'method' => $method];
    }

    public static function addCustomTypeResolver(string $type, string $className, string $method): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'initSystem' && $class !== self::class && $func !== 'graphql' && !is_subclass_of($class, AppContainer::class)) {
            throw new GraphqlException('Cannot add a type resolver from anything other than the graphql method in an AppContainer.', 0403);
        }

        //Register::registerGraphQLResolver($type, $className, $method, $class);
        self::$typeResolvers[$type] = (object)['class' => $className, 'method' => $method];
    }

    /**
     *
     * Add parts of the schema for queries
     *
     * @param  string  $content
     * @return void
     *
     */
    public static function addQuerySchema(string $content): void
    {
        self::$querySchemaParts[] = $content;
    }

    /**
     *
     * Add parts of the schema for mutation
     *
     * @param  string  $content
     * @return void
     *
     */
    public static function addMutationSchema(string $content): void
    {
        self::$mutationSchemaParts[] = $content;
    }

    /**
     *
     * Add parts of the schema for custom types
     *
     * @param  string  $content
     * @return void
     *
     */
    public static function addTypeSchema(string $content): void
    {
        self::$typeSchemaParts[] = $content;
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
        if (!setting('graphql.hideCMS', false)) {
            self::initSystem();
        }

        try {
            $pathAST = 'cache://graphql.ast';

            if (env('environment', 'dev') === 'dev') {
                // Load all files for the schema
                $queries = [];
                $mutations = [];
                $types = [];

                foreach (self::$querySchemaParts as $file) {
                    $queries[] = file_get_contents($file);
                }

                foreach (self::$mutationSchemaParts as $file) {
                    $mutations[] = file_get_contents($file);
                }

                foreach (self::$typeSchemaParts as $file) {
                    $types[] = file_get_contents($file);
                }

                $locales = Locale::getAvailableLocales();
                $localeString = '';

                foreach ($locales as $locale) {
                    $localeString .= "{$locale}: String\n";
                }

                if (!setting('graphql.hideCMS', false)) {
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
                } else {
                    $schemaContent = file_get_contents(__DIR__ . '/GraphQL/empty_schema.graphql');
                    $schemaContent = str_replace(
                        [
                            '#{CUSTOM_QUERIES}#',
                            '#{CUSTOM_MUTATIONS}#',
                            '#{CUSTOM_TYPES}#'
                        ],
                        [
                            implode("\n", $queries),
                            implode("\n", $mutations),
                            implode("\n", $types),
                        ],
                        $schemaContent
                    );
                }

                // Parse schema
                $document = Parser::parse($schemaContent);
            } else {
                $ast = require Sail::getWorkingDirectory() . '/storage/cache/graphql.ast';
                $document = AST::fromArray($ast);
            }

            $schema = BuildSchema::build($document, [self::class, 'handleCustomTypes']);

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true, 256, JSON_THROW_ON_ERROR);
            $query = $input['query'] ?? $input['mutation'] ?? '';
            $variableValues = $input['variables'] ?? null;

            $data = ['query' => $query, 'variables' => $variableValues];

            // Disable Introspection (if set to false)
            $introspection = setting('graphql.introspection', true);
            if (!$introspection) {
                $rule = new DisableIntrospection(DisableIntrospection::ENABLED);
                DocumentValidator::addRule($rule);
            }

            // Depth Limiting (n+1)
            $limit = setting('graphql.depthLimit', 5);

            if ($introspection) {
                $limit = 11;
            }

            $rule = new QueryDepth($limit);
            DocumentValidator::addRule($rule);

            if (!empty($input['query'])) {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::BeforeQuery, data: $data));
            } else {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::BeforeMutation, data: $data));
            }

            $data = $mresult->data;

            $result = GQL::executeQuery($schema, $data['query'], null, new Context(), $data['variables'], null, [__CLASS__, 'resolvers']);

            $serializableResult = (object)$result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
            $errors = $serializableResult->errors ?? [];

            if (!empty($input['query'])) {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterQuery, data: $serializableResult));
            } else {
                $mresult = Middleware::execute(MiddlewareType::GRAPHQL, new Data(MGQL::AfterMutation, data: $serializableResult));
            }

            if ($errors) {
                $mresult->data->errors = [];

                foreach ($errors as $error) {
                    $locations = $error['locations'] ?? '';

                    $mresult->data->errors = [
                        [
                            'message' => $error['debugMessage'] ?? $error['message'],
                            'extensions' => $error['extensions'] ?? ['category' => 'internal'],
                            'locations' => (isset($error['trace'])) ? [['line' => $error['trace'][0]['line'], 'column' => 1]] : $locations,
                            'file' => $error['trace'][0]['file'] ?? 'unknown file',
                            'stack' => $error['trace'] ?? [],
                            'path' => $error['path'] ?? ['']
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
        self::addQueryResolver('version', Basics::class, 'version');

        // User
        self::addQueryResolver('user', Users::class, 'user');
        self::addQueryResolver('users', Users::class, 'users');
        self::addQueryResolver('resendValidationEmail', Users::class, 'resendValidationEmail');
        self::addMutationResolver('createUser', Users::class, 'createUser');
        self::addMutationResolver('createUserGetId', Users::class, 'createUserGetId');
        self::addMutationResolver('createAdminUser', Users::class, 'createAdminUser');
        self::addMutationResolver('updateUser', Users::class, 'updateUser');
        self::addMutationResolver('deleteUser', Users::class, 'deleteUser');
        self::addMutationResolver('deleteUsers', Users::class, 'deleteUsers');
        self::addMutationResolver('validateAccount', Users::class, 'validateAccount');
        self::addMutationResolver('changeUserStatus', Users::class, 'changeUserStatus');

        // Authentication
        self::addQueryResolver('authenticate', Users::class, 'authenticate');
        self::addQueryResolver('verifyAuthenticationToken', Users::class, 'verifyAuthenticationToken');
        self::addQueryResolver('verifyTFA', Users::class, 'verifyTFA');
        self::addQueryResolver('forgotPassword', Users::class, 'forgotPassword');
        self::addQueryResolver('userWithToken', Users::class, 'userWithToken');
        self::addMutationResolver('changePassword', Users::class, 'changePassword');

        // Roles & ACL
        self::addQueryResolver('role', Roles::class, 'role');
        self::addQueryResolver('roles', Roles::class, 'roles');
        self::addQueryResolver('permissions', Roles::class, 'permissions');
        self::addMutationResolver('createRole', Roles::class, 'createRole');
        self::addMutationResolver('updateRole', Roles::class, 'updateRole');
        self::addMutationResolver('deleteRole', Roles::class, 'delete');
        self::addResolver('Role', Roles::class, 'resolver');
        self::addResolver('Permission', Roles::class, 'permissionResolver');

        // Assets
        self::addQueryResolver('asset', Assets::class, 'asset');
        self::addQueryResolver('assets', Assets::class, 'assets');
        self::addQueryResolver('assetFolders', Assets::class, 'assetFolders');
        self::addQueryResolver('assetConfig', Assets::class, 'assetConfig');
        self::addQueryResolver('assetTransformForId', Assets::class, 'assetTransformForId');
        self::addMutationResolver('uploadAsset', Assets::class, 'createAsset');
        self::addMutationResolver('updateAssetTitle', Assets::class, 'updateAssetTitle');
        self::addMutationResolver('removeAssets', Assets::class, 'removeAssets');
        self::addMutationResolver('transformAsset', Assets::class, 'transformAsset');
        self::addMutationResolver('customTransformAsset', Assets::class, 'customTransformAsset');
        self::addMutationResolver('moveFiles', Assets::class, 'moveFiles');
        self::addMutationResolver('addFolder', Assets::class, 'addFolder');
        self::addMutationResolver('removeFolder', Assets::class, 'removeFolder');
        self::addResolver('Asset', Assets::class, 'assetResolver');

        // Emails 1
        self::addQueryResolver('emailV1', Emails::class, 'emailV1');
        self::addQueryResolver('emailsV1', Emails::class, 'emailsV1');
        self::addMutationResolver('createEmailV1', Emails::class, 'createEmailV1');
        self::addMutationResolver('updateEmailV1', Emails::class, 'updateEmailV1');
        self::addMutationResolver('deleteEmailV1', Emails::class, 'deleteEmailV1');
        self::addMutationResolver('deleteEmailBySlugV1', Emails::class, 'deleteEmailBySlugV1');

        // Emails 2
        self::addQueryResolver('email', Emails::class, 'email');
        self::addQueryResolver('emails', Emails::class, 'emails');
        self::addQueryResolver('emailTemplates', Emails::class, 'emailTemplates');
        self::addMutationResolver('createEmail', Emails::class, 'createEmail');
        self::addMutationResolver('updateEmail', Emails::class, 'updateEmail');
        self::addMutationResolver('deleteEmail', Emails::class, 'deleteEmail');
        self::addMutationResolver('deleteEmailBySlug', Emails::class, 'deleteEmailBySlug');
        self::addMutationResolver('testEmail', Emails::class, 'testEmail');
        self::addMutationResolver('createPreviewEmail', Emails::class, 'createPreviewEmail');

        // Entries
        self::addQueryResolver('homepageEntry', Entries::class, 'homepageEntry');

        self::addQueryResolver('entryTypes', Entries::class, 'entryTypes');
        self::addQueryResolver('entryType', Entries::class, 'entryType');
        self::addMutationResolver('createEntryType', Entries::class, 'createEntryType');
        self::addMutationResolver('updateEntryType', Entries::class, 'updateEntryType');
        self::addMutationResolver('deleteEntryType', Entries::class, 'deleteEntryType');

        self::addQueryResolver('entries', Entries::class, 'entries');
        self::addQueryResolver('entry', Entries::class, 'entry');
        self::addQueryResolver('entryByUrl', Entries::class, 'entryByUrl');
        self::addMutationResolver('createEntry', Entries::class, 'createEntry');
        self::addMutationResolver('updateEntrySeo', Entries::class, 'updateEntrySeo');
        self::addMutationResolver('updateEntry', Entries::class, 'updateEntry');
        self::addMutationResolver('publishEntry', Entries::class, 'publishEntry');
        self::addMutationResolver('unpublishEntry', Entries::class, 'unpublishEntry');
        self::addMutationResolver('deleteEntry', Entries::class, 'deleteEntry');

        self::addQueryResolver('entryVersion', Entries::class, 'entryVersion');
        self::addQueryResolver('entryVersions', Entries::class, 'entryVersions');
        self::addMutationResolver('applyVersion', Entries::class, 'applyVersion');

        self::addQueryResolver('entryLayout', Entries::class, 'entryLayout');
        self::addQueryResolver('entryLayouts', Entries::class, 'entryLayouts');
        self::addMutationResolver('createEntryLayout', Entries::class, 'createEntryLayout');
        self::addMutationResolver('updateEntryLayoutSchema', Entries::class, 'updateEntryLayoutSchema');
        self::addMutationResolver('updateEntryLayoutSchemaKey', Entries::class, 'updateEntryLayoutSchemaKey');
        self::addMutationResolver('deleteEntryLayout', Entries::class, 'deleteEntryLayout');

        self::addQueryResolver('fields', Entries::class, 'fields');

        // Types and Resolvers
        self::addResolver('Entry', Entries::class, 'entryResolver');
        self::addResolver('Alternate', Entries::class, 'entryAlternateResolver');
        self::addResolver('Authors', Entries::class, 'authorsResolver');
        self::addResolver('EntryPublication', Entries::class, 'entryPublicationResolver');
        self::addResolver('EntryLayout', Entries::class, 'entryLayoutResolver');

        // Register
        self::addQueryResolver('registeredExtensions', Registers::class, 'registeredExtensions');

        // Categories
        self::addQueryResolver('category', Categories::class, 'category');
        self::addQueryResolver('categoryBySlug', Categories::class, 'categoryBySlug');
        self::addQueryResolver('categoryFullTree', Categories::class, 'categoryFullTree');
        self::addQueryResolver('categoryEntries', Categories::class, 'categoryEntries');
        self::addMutationResolver('createCategory', Categories::class, 'createCategory');
        self::addMutationResolver('updateCategory', Categories::class, 'updateCategory');
        self::addMutationResolver('updateCategoryOrders', Categories::class, 'updateCategoryOrders');
        self::addMutationResolver('deleteCategory', Categories::class, 'deleteCategory');
        self::addMutationResolver('deleteCategoryBySlug', Categories::class, 'deleteCategoryBySlug');

        // Navigation
        self::addQueryResolver('navigation', Navigation::class, 'navigation');
        self::addQueryResolver('navigationDetails', Navigation::class, 'navigationDetails');
        self::addMutationResolver('createNavigation', Navigation::class, 'createNavigation');
        self::addMutationResolver('updateNavigation', Navigation::class, 'updateNavigation');
        self::addMutationResolver('deleteNavigation', Navigation::class, 'deleteNavigation');

        // Groups
        self::addQueryResolver('group', Groups::class, 'group');
        self::addQueryResolver('groups', Groups::class, 'groups');
        self::addMutationResolver('createGroup', Groups::class, 'createGroup');
        self::addMutationResolver('updateGroup', Groups::class, 'updateGroup');
        self::addMutationResolver('deleteGroup', Groups::class, 'deleteGroup');
        self::addResolver('Group', Groups::class, 'groupResolver');

        # UI Related
        self::addQueryResolver('navigationElements', Misc::class, 'navigationElements');
        self::addQueryResolver('settingsElements', Misc::class, 'settingsElements');
        self::addQueryResolver('handshakeKey', Misc::class, 'handshakeKey');

        // Queue
        self::addQueryResolver('task', Queue::class, 'getTask');
        self::addQueryResolver('taskList', Queue::class, 'getList');
        self::addQueryResolver('taskSearch', Queue::class, 'searchTasks');
        self::addQueryResolver('taskRunningTime', Queue::class, 'getTaskRunningTime');
        self::addMutationResolver('createTask', Queue::class, 'createTask');
        self::addMutationResolver('stopTask', Queue::class, 'stopTask');
        self::addMutationResolver('stopAllTasks', Queue::class, 'stopAllTasks');
        self::addMutationResolver('changeTaskSchedule', Queue::class, 'changeTaskSchedule');
        self::addMutationResolver('cancelTask', Queue::class, 'cancelTask');
        self::addMutationResolver('retryTask', Queue::class, 'retryTask');
        self::addMutationResolver('startAllTasks', Queue::class, 'startAllTasks');

        // Misc calls
        // TODO: GET LOGS (from file or db)

        // Types and Resolvers
        self::addResolver('User', Users::class, 'resolver');
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
                    // Unwrap collections before heading out
                    $property = $objectValue->{$fieldName}->unwrap();
                } elseif (is_object($objectValue->{$fieldName})) {
                    // Simplify objects that are castable
                    $implements = class_implements($objectValue->{$fieldName});
                    $implements = array_values($implements);

                    // When simplified, return them right away because the type cannot be resolved to native json type
                    if (count($implements) > 0 && $implements[0] === Castable::class) {
                        return $objectValue->{$fieldName}->castFrom();
                    }

                    $property = $objectValue->{$fieldName};
                } else {
                    $property = $objectValue->{$fieldName};
                }
            }
        }

        if (isset($property)) {
            foreach (self::$resolvers as $name => $resolver) {
                if ($type === $name) {
                    return (new $resolver->class())->{$resolver->method}($objectValue, new Collection($args), $contextValue, $info);
                }
            }

            return $property;
        }

        if ($type === 'Query') {
            foreach (self::$queries as $name => $query) {
                if ($fieldName === $name) {
                    return DI::resolve($query->class)->{$query->method}($objectValue, new Collection($args), $contextValue);
                }
            }

            throw new \RuntimeException("Cannot find resolver for Query '{$fieldName}'");
        }

        if ($type === 'Mutation') {
            foreach (self::$mutations as $name => $mutation) {
                if ($fieldName === $name) {
                    return DI::resolve($mutation->class)->{$mutation->method}($objectValue, new Collection($args), $contextValue);
                }
            }

            throw new \RuntimeException("Cannot find resolver for Mutation '{$fieldName}'");
        }

        // One last try on the resolvers
        foreach (self::$resolvers as $name => $resolver) {
            if ($type === $name) {
                return DI::resolve($resolver->class)->{$resolver->method}($objectValue, new Collection($args), $contextValue, $info);
            }
        }

        return $objectValue;
    }

    /**
     *
     * Run custom types on possible resolver for them
     *
     * @param  array               $typeConfig
     * @param  TypeDefinitionNode  $typeDefinitionNode
     * @return array
     *
     */
    public static function handleCustomTypes(array $typeConfig, TypeDefinitionNode $typeDefinitionNode): array
    {
        $name = $typeConfig['name'];

        $resolver = self::$typeResolvers[$name] ?? null;

        if (!$resolver) {
            // Nothing to do
            return $typeConfig;
        }

        $typeConfig['resolveType'] = function ($obj) use ($resolver)
        {
            return call_user_func([$resolver->class, $resolver->method], $obj);
        };

        return $typeConfig;
    }
}