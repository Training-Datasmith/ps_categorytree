<?php

class PsCategoryTreeWidgetRootTest extends PsCategoryTreeTestCase
{
    public function testStringZeroSelectsHome()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $vars = $this->newModule()->getWidgetVariables();
        $this->assertSame(2, $vars['currentCategory']);
    }

    public function testUnknownRootSelectsHome()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '9';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testStringOneSelectsControllerCategoryOnCategoryController()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(10, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testStringOneSelectsControllerCategoryOnProductController()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $c = new ProductControllerWithCategory();
        $c->category = $this->clothesCategory();
        Context::getInstance()->controller = $c;
        $this->assertSame(10, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentModeFallsBackWhenMethodMissing()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        Context::getInstance()->controller = new IndexControllerStub();
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentModeFallsBackForOtherController()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $c = new GenericControllerWithCategory();
        $c->category = $this->clothesCategory();
        Context::getInstance()->controller = $c;
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentModeFallsBackWhenObjectNotLoaded()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        ValidateStore::$loadedByCategoryId[10] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeReturnsUsableParent()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(12, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeStaysOnRootFlag()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['is_root_category'] = true;
        $this->setCategoryControllerCategory(new Category(10, 2));
        $this->assertSame(10, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeStaysWhenParentIdEmpty()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 0;
        $this->setCategoryControllerCategory(new Category(10, 2));
        $this->assertSame(10, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeStaysWhenCategoryIsHome()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[2]['is_root_category'] = false;
        CategoryRegistry::$definitions[2]['id_parent'] = 1;
        CategoryRegistry::$definitions[1] = [
            'id' => 1, 'id_parent' => 10, 'level_depth' => 2, 'link_rewrite' => 'alt',
            'name' => 'Alt', 'description' => 'Alt desc', 'is_root_category' => false, 'active' => true,
        ];
        $this->setCategoryControllerCategory(new Category(2, 2));
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
        $this->assertNotContains([1, 2], CategoryRegistry::$constructorLog);
    }

    public function testParentModeReturnsHomeWhenParentNotLoaded()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 99;
        unset(CategoryRegistry::$definitions[99]);
        ValidateStore::$loadedByCategoryId[99] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(2, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeSkipsInactiveParent()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$definitions[12]['id_parent'] = 13;
        CategoryRegistry::$activeById[12] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(13, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testParentModeSkipsParentThatDeniesAccess()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$definitions[12]['id_parent'] = 13;
        CategoryRegistry::$checkAccessById[12] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(13, $this->newModule()->getWidgetVariables()['currentCategory']);
        $this->assertContains([12, 15], CategoryRegistry::$checkAccessLog);
    }

    public function testParentModeSkipsParentMissingFromShop()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$definitions[12]['id_parent'] = 13;
        CategoryRegistry::$existsInShopById[12] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(13, $this->newModule()->getWidgetVariables()['currentCategory']);
        $this->assertContains([12, 1], CategoryRegistry::$existsInShopLog);
    }

    public function testParentModeKeepsParentWhenOnlyChildDeniesAccess()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '2';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$checkAccessById[10] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(12, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentParentKeepsCategoryThatHasChildren()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '3';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(10, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentParentWalksWhenSubcategoriesEmptyArray()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '3';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$subCategoriesById[10] = [];
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(12, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testCurrentParentWalksWhenSubcategoriesFalse()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '3';
        CategoryRegistry::$definitions[10]['id_parent'] = 12;
        CategoryRegistry::$subCategoriesReturns[10] = false;
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->assertSame(12, $this->newModule()->getWidgetVariables()['currentCategory']);
    }

    public function testGetSubCategoriesReceivesLanguageIdAndActiveTrue()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '3';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $call = CategoryRegistry::$getSubCategoriesLog[0];
        $this->assertSame(10, $call[0]);
        $this->assertSame(2, $call[1]);
        $this->assertTrue($call[2]);
    }
}
