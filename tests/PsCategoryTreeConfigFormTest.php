<?php

class PsCategoryTreeConfigFormTest extends PsCategoryTreeTestCase
{
    /** @return array<string, mixed> */
    private function configUpdatesByKey()
    {
        $byKey = [];
        foreach (ConfigurationStore::$updateLog as $entry) {
            $byKey[$entry[0]] = $entry[1];
        }

        return $byKey;
    }

    public function testGetConfigFieldsValuesReadsStoredConfig()
    {
        ConfigurationStore::$values['BLOCK_CATEG_MAX_DEPTH'] = '4';
        $module = $this->newModule();
        $values = $module->getConfigFieldsValues();
        $this->assertSame('4', $values['BLOCK_CATEG_MAX_DEPTH']);
        $this->assertSame('0', $values['BLOCK_CATEG_SORT_WAY']);
    }

    public function testPostedValueOverridesOnlyThatKey()
    {
        ToolsStore::$posted['BLOCK_CATEG_MAX_DEPTH'] = '9';
        $module = $this->newModule();
        $values = $module->getConfigFieldsValues();
        $this->assertSame('9', $values['BLOCK_CATEG_MAX_DEPTH']);
        $this->assertSame('0', $values['BLOCK_CATEG_SORT']);
    }

    public function testRenderFormWiresHelperAndFourControls()
    {
        $module = $this->newModule();
        $this->assertSame('FORM_HTML', $module->renderForm());
        $helper = HelperFormStore::$instances[0];
        $this->assertFalse($helper->show_toolbar);
        $this->assertSame('submitBlockCategories', $helper->submit_action);
        $names = array_column($helper->lastGenerateFormArgs[0]['form']['input'], 'name');
        $this->assertSame(
            ['BLOCK_CATEG_ROOT_CATEGORY', 'BLOCK_CATEG_MAX_DEPTH', 'BLOCK_CATEG_SORT', 'BLOCK_CATEG_SORT_WAY'],
            $names
        );
        $call = LinkDouble::$adminLinkLog[0];
        $this->assertSame(
            ['configure' => 'ps_categorytree', 'tab_module' => 'front_office_features', 'module_name' => 'ps_categorytree'],
            $call[3]
        );
    }

    public function testGetContentWithoutSubmitDoesNotWrite()
    {
        $module = $this->newModule();
        $this->assertSame('FORM_HTML', $module->getContent());
        $this->assertSame([], ConfigurationStore::$updateLog);
    }

    public function testNegativeDepthIsRejected()
    {
        ToolsStore::$submitBlockCategories = true;
        ToolsStore::$posted = [
            'BLOCK_CATEG_MAX_DEPTH' => '-1',
            'BLOCK_CATEG_SORT_WAY' => '0',
            'BLOCK_CATEG_SORT' => '0',
            'BLOCK_CATEG_ROOT_CATEGORY' => '0',
        ];
        $out = $this->newModule()->getContent();
        $this->assertStringContainsString('Maximum depth: Invalid number.', $out);
        $this->assertContains(['Maximum depth: Invalid number.', 'Admin.Notifications.Error'], TransLog::$calls);
    }

    public function testDepthZeroIsSaved()
    {
        ToolsStore::$submitBlockCategories = true;
        ToolsStore::$posted = [
            'BLOCK_CATEG_MAX_DEPTH' => '0',
            'BLOCK_CATEG_SORT_WAY' => '0',
            'BLOCK_CATEG_SORT' => '0',
            'BLOCK_CATEG_ROOT_CATEGORY' => '0',
        ];
        try {
            $this->newModule()->getContent();
            $this->fail('Expected redirect');
        } catch (RuntimeException $e) {
            $this->assertSame('redirectAdmin', $e->getMessage());
        }
        $updates = $this->configUpdatesByKey();
        $this->assertSame(0, $updates['BLOCK_CATEG_MAX_DEPTH']);
        $this->assertEquals(0, $updates['BLOCK_CATEG_SORT_WAY']);
        $this->assertEquals(0, $updates['BLOCK_CATEG_SORT']);
        $this->assertEquals(0, $updates['BLOCK_CATEG_ROOT_CATEGORY']);
    }

    public function testPositiveDepthSortAndRootAreSavedBeforeRedirect()
    {
        ToolsStore::$submitBlockCategories = true;
        ToolsStore::$posted = [
            'BLOCK_CATEG_MAX_DEPTH' => '3',
            'BLOCK_CATEG_SORT_WAY' => '1',
            'BLOCK_CATEG_SORT' => '1',
            'BLOCK_CATEG_ROOT_CATEGORY' => '2',
        ];
        try {
            $this->newModule()->getContent();
            $this->fail('Expected redirect');
        } catch (RuntimeException $e) {
            $this->assertSame('redirectAdmin', $e->getMessage());
        }
        $updates = $this->configUpdatesByKey();
        $this->assertSame(3, $updates['BLOCK_CATEG_MAX_DEPTH']);
        $this->assertEquals(1, $updates['BLOCK_CATEG_SORT_WAY']);
        $this->assertEquals(1, $updates['BLOCK_CATEG_SORT']);
        $this->assertEquals(2, $updates['BLOCK_CATEG_ROOT_CATEGORY']);
    }
}
