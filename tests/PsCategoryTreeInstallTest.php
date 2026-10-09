<?php

class PsCategoryTreeInstallTest extends PsCategoryTreeTestCase
{
    public function testInstallRegistersLeftColumnWhenParentInstallSucceeds()
    {
        $module = $this->newModule();
        $this->assertTrue($module->install());
        $this->assertSame(['displayLeftColumn'], ModuleRuntime::$registeredHooks);
    }

    public function testInstallDoesNotRegisterHookWhenParentInstallFails()
    {
        ModuleRuntime::$parentInstallResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame([], ModuleRuntime::$registeredHooks);
    }

    public function testInstallReturnsFalseWhenHookRegistrationFails()
    {
        ModuleRuntime::$hookRegistrationResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->install());
    }

    public function testUninstallReturnsFalseWithoutDeletingWhenParentFails()
    {
        ModuleRuntime::$parentUninstallResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->uninstall());
        $this->assertSame([], ConfigurationStore::$deleteLog);
    }

    public function testUninstallDeletesDepthAndRoot()
    {
        $module = $this->newModule();
        $this->assertTrue($module->uninstall());
        $this->assertContains('BLOCK_CATEG_MAX_DEPTH', ConfigurationStore::$deleteLog);
        $this->assertContains('BLOCK_CATEG_ROOT_CATEGORY', ConfigurationStore::$deleteLog);
    }

    public function testUninstallReturnsFalseWhenDepthDeleteFails()
    {
        ConfigurationStore::$deleteResults = ['BLOCK_CATEG_MAX_DEPTH' => false];
        $module = $this->newModule();
        $this->assertFalse($module->uninstall());
    }

    public function testUninstallReturnsFalseWhenRootDeleteFails()
    {
        ConfigurationStore::$deleteResults = [
            'BLOCK_CATEG_MAX_DEPTH' => true,
            'BLOCK_CATEG_ROOT_CATEGORY' => false,
        ];
        $module = $this->newModule();
        $this->assertFalse($module->uninstall());
    }
}
