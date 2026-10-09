<?php

class PsCategoryTreeModuleMetadataTest extends PsCategoryTreeTestCase
{
    public function testConstructorSetsIdentity()
    {
        $module = $this->newModule();
        $this->assertSame('ps_categorytree', $module->name);
        $this->assertSame('front_office_features', $module->tab);
        $this->assertSame('3.0.2', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertTrue($module->bootstrap);
        $this->assertSame('Category tree links', $module->displayName);
    }

    public function testCompliancyUsesMinimumAndRuntimeVersion()
    {
        $module = $this->newModule();
        $this->assertSame('1.7.7.0', $module->ps_versions_compliancy['min']);
        $this->assertSame('1.7.7.0', $module->ps_versions_compliancy['max']);
    }

    public function testRootConstants()
    {
        $this->assertSame(0, Ps_CategoryTree::CATEGORY_ROOT_HOME);
        $this->assertSame(1, Ps_CategoryTree::CATEGORY_ROOT_CURRENT);
        $this->assertSame(2, Ps_CategoryTree::CATEGORY_ROOT_PARENT);
        $this->assertSame(3, Ps_CategoryTree::CATEGORY_ROOT_CURRENT_PARENT);
    }

    public function testConfigXmlMatchesClass()
    {
        $module = $this->newModule();
        $xml = simplexml_load_file(dirname(__DIR__) . '/config.xml');
        $this->assertNotFalse($xml);
        $this->assertSame('ps_categorytree', (string) $xml->name);
        $this->assertSame($module->version, (string) $xml->version);
    }
}
