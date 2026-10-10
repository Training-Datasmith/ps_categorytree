<?php

class PsCategoryTreeRenderWidgetTest extends PsCategoryTreeTestCase
{
    public function testAssignsVariablesAndReturnsTemplateFetch()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        Context::getInstance()->controller = new IndexControllerStub();
        $out = $this->newModule()->renderWidget();
        $this->assertSame(10, SmartyDouble::$assigned['categories']['id']);
        $this->assertSame(2, SmartyDouble::$assigned['currentCategory']);
        $this->assertStringContainsString('module:ps_categorytree/views/templates/hook/ps_categorytree.tpl', $out);
    }

    public function testAssignsEmptyCategoryList()
    {
        ConfigurationStore::$values['BLOCK_CATEG_ROOT_CATEGORY'] = '0';
        CategoryRegistry::$useDefaultNested = false;
        CategoryRegistry::$nestedCategoriesReturn = [];
        $this->newModule()->renderWidget();
        $this->assertSame([], SmartyDouble::$assigned['categories']);
    }
}
