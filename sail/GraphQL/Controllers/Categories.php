<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Category;

class Categories
{
    public function category(mixed $obj, Collection $args, Context $context): ?Category
    {
        return Category::getById($args->get('id'));
    }

    public function categoryBySlug(mixed $obj, Collection $args, Context $context): ?Category
    {
        return Category::getBySlug($args->get('slug'));
    }

    public function categoryFullTree(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Category())->getList($args->get('parent_id'));
    }

    public function createCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->create($args->get('name'), $args->get('site_id'));
    }

    public function updateCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->update($args->get('id'), $args->get('name'), $args->get('site_id'));
    }

    public function updateCategoryOrders(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->updateOrder($args->get('parent_id'));
    }

    public function deleteCategory(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Category())->delete($args->get('id'));
    }

    public function deleteCategoryBySlug(mixed $obj, Collection $args, Context $context): bool
    {
        return Category::deleteBySlug($args->get('slug'));
    }
}