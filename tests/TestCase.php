<?php

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class PsCategoryTreeTestCase extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        RuntimeState::resetAll();
        ConfigurationStore::resetDeleteResults();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        RuntimeState::resetAll();
        ConfigurationStore::resetDeleteResults();
        parent::tearDown();
    }

    /** @return Ps_CategoryTree */
    protected function newModule()
    {
        return new Ps_CategoryTree();
    }

    protected function clothesCategory()
    {
        return new Category(10, 2);
    }

    protected function setCategoryControllerCategory($category)
    {
        $controller = new CategoryControllerWithCategory();
        $controller->category = $category;
        Context::getInstance()->controller = $controller;
    }
}

class CategoryControllerWithCategory extends CategoryController
{
    /** @var Category */
    public $category;

    public function getCategory()
    {
        return $this->category;
    }
}

class ProductControllerWithCategory extends ProductController
{
    /** @var Category */
    public $category;

    public function getCategory()
    {
        return $this->category;
    }
}

class GenericControllerWithCategory
{
    /** @var Category */
    public $category;

    public function getCategory()
    {
        return $this->category;
    }
}

class IndexControllerStub
{
}
