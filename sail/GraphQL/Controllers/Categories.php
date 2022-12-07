<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Category;

class Categories
{
    /**
     * Get a single category by id
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Category|null
     * @throws DatabaseException
     *
     */
    public function category(mixed $obj, Collection $args, Context $context): ?Category
    {
        return Category::getById($args->get('id'));
    }

    /**
     *
     * Get a category by slug
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Category|null
     * @throws DatabaseException
     *
     */
    public function categoryBySlug(mixed $obj, Collection $args, Context $context): ?array
    {
        return Category::getBySlug($args->get('slug'), $args->get('site_id'))->toGraphQL();
    }

    /**
     *
     * Get a full tree of categories
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function categoryFullTree(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Category())->getList($args->get('parent_id'), $args->get('site_id'));
    }

    /**
     *
     * Create a category
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function createCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->create($args->get('name'), $args->get('parent_id'), $args->get('site_id'));
    }

    /**
     *
     * Update a category
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->update($args->get('id'), $args->get('name'), $args->get('parent_id'));
    }

    /**
     *
     * Update category orders for given root parent
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateCategoryOrders(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->updateOrder($args->get('parent_id'), $args->get('site_id'));
    }

    /**
     *
     * Delete a category by id
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->delete($args->get('id'));
    }

    /**
     *
     * Delete a category by slug
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteCategoryBySlug(mixed $obj, Collection $args, Context $context): bool
    {
        return Category::deleteBySlug($args->get('slug'), $args->get('site_id'));
    }
}