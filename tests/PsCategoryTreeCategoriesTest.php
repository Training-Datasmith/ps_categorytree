<?php

class PsCategoryTreeCategoriesTest extends PsCategoryTreeTestCase
{
    public function testEmptyNestedResultNull()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = null;
        $vars = $this->newModule()->getWidgetVariables();
        $this->assertSame([], $vars['categories']);
        $this->assertSame(2, $vars['currentCategory']);
    }

    public function testEmptyNestedResultFalse()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = false;
        $vars = $this->newModule()->getWidgetVariables();
        $this->assertSame([], $vars['categories']);
    }

    public function testEmptyNestedResultEmptyArray()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = [];
        $this->assertSame([], $this->newModule()->getWidgetVariables()['categories']);
    }

    public function testDepthZeroOmitsLevelFilter()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        ConfigurationStore::$values['BLOCK_CATEG_MAX_DEPTH'] = '0';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $this->assertSame('', CategoryRegistry::$lastNestedArgs[5]);
    }

    public function testPositiveDepthAddsLevel()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        ConfigurationStore::$values['BLOCK_CATEG_MAX_DEPTH'] = '2';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $this->assertSame('AND c.`level_depth` <= 4', CategoryRegistry::$lastNestedArgs[5]);
    }

    public function testSortNameDescending()
    {
        ConfigurationStore::$values['BLOCK_CATEG_SORT'] = '1';
        ConfigurationStore::$values['BLOCK_CATEG_SORT_WAY'] = '1';
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $this->assertSame(' ORDER BY c.`level_depth` ASC, cl.`name` DESC', CategoryRegistry::$lastNestedArgs[6]);
    }

    public function testSortPositionAscending()
    {
        ConfigurationStore::$values['BLOCK_CATEG_SORT'] = '0';
        ConfigurationStore::$values['BLOCK_CATEG_SORT_WAY'] = '0';
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $this->assertSame(
            ' ORDER BY c.`level_depth` ASC, category_shop.`position` ASC',
            CategoryRegistry::$lastNestedArgs[6]
        );
    }

    public function testNestedCallArguments()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $this->newModule()->getWidgetVariables();
        $args = CategoryRegistry::$lastNestedArgs;
        $this->assertSame(10, $args[0]);
        $this->assertSame(2, $args[1]);
        $this->assertTrue($args[2]);
        $this->assertSame([3, 4], $args[3]);
        $this->assertTrue($args[4]);
    }

    public function testFormatsNodeFields()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $node = $this->newModule()->getWidgetVariables()['categories'];
        $this->assertSame(10, $node['id']);
        $this->assertSame('https://shop.test/10-clothes', $node['link']);
        $this->assertSame([10, 'clothes'], LinkDouble::$lastCategoryLinkArgs);
    }

    public function testInPathFollowsBrowsedCategoryUnderHomeRoot()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = [
            2 => [
                'id_category' => 2,
                'link_rewrite' => 'home',
                'name' => 'Home',
                'description' => 'Home desc',
                'children' => [
                    [
                        'id_category' => 10,
                        'link_rewrite' => 'clothes',
                        'name' => 'Clothes',
                        'description' => 'Clothes desc',
                        'children' => [],
                    ],
                    [
                        'id_category' => 99,
                        'link_rewrite' => 'other',
                        'name' => 'Other',
                        'description' => 'Other desc',
                        'children' => [],
                    ],
                ],
            ],
        ];
        $this->setCategoryControllerCategory(new Category(11, 2));
        $cats = $this->newModule()->getWidgetVariables()['categories'];
        $this->assertTrue($cats['in_path']);
        $this->assertTrue($cats['children'][0]['in_path']);
        $this->assertFalse($cats['children'][1]['in_path']);
    }

    public function testChildrenRecurseInOrder()
    {
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = [
            10 => [
                'id_category' => 10,
                'link_rewrite' => 'clothes',
                'name' => 'Clothes',
                'description' => 'Clothes desc',
                'children' => [
                    ['id_category' => 11, 'link_rewrite' => 'shirts', 'name' => 'Shirts', 'description' => '', 'children' => []],
                    ['id_category' => 20, 'link_rewrite' => 'pants', 'name' => 'Pants', 'description' => '', 'children' => []],
                ],
            ],
        ];
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '1';
        $this->setCategoryControllerCategory($this->clothesCategory());
        $children = $this->newModule()->getWidgetVariables()['categories']['children'];
        $this->assertSame('Shirts', $children[0]['name']);
        $this->assertSame('Pants', $children[1]['name']);
    }

    public function testCurrentCategoryIsSelectedRootWhilePathIsBrowsed()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        $this->setCategoryControllerCategory(new Category(11, 2));
        $vars = $this->newModule()->getWidgetVariables();
        $this->assertSame(2, $vars['currentCategory']);
        $this->assertTrue($vars['categories']['children'][0]['in_path']);
    }
}
