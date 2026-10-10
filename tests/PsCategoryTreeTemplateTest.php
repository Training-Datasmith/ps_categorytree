<?php

class PsCategoryTreeTemplateTest extends PsCategoryTreeTestCase
{
    /** @var Smarty */
    private $smarty;

    protected function setUp(): void
    {
        parent::setUp();
        $compileDir = sys_get_temp_dir() . '/ps-categorytree-smarty-compile-' . getmypid();
        if (is_dir($compileDir)) {
            array_map('unlink', glob($compileDir . '/*') ?: []);
            rmdir($compileDir);
        }
        mkdir($compileDir, 0777, true);
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(dirname(__DIR__) . '/views/templates/hook');
        $this->smarty->setCompileDir($compileDir);
        $this->smarty->escape_html = true;
    }

    public function testRootLinkIsPresentAndNameAndChildLinkAreEscaped()
    {
        $html = $this->render([
            'categories' => [
                'link' => 'https://shop.test/2-home?x=1&y=2',
                'name' => 'Tom & Jerry',
                'children' => [
                    ['link' => 'https://shop.test/10-clothes?x=1&y=2', 'name' => 'Shirts', 'children' => []],
                ],
            ],
            'currentCategory' => 2,
        ]);
        $this->assertStringContainsString('href="https://shop.test/2-home', $html);
        $this->assertStringContainsString('Tom &amp; Jerry', $html);
        $this->assertStringContainsString('10-clothes?x=1&amp;y=2', $html);
    }

    public function testRendersGrandchildAndSkipsEmptyChildList()
    {
        $html = $this->render([
            'categories' => [
                'link' => 'https://shop.test/2-home',
                'name' => 'Home',
                'children' => [
                    [
                        'link' => 'https://shop.test/11-shirts',
                        'name' => 'Shirts',
                        'children' => [
                            ['link' => 'https://shop.test/30-cotton', 'name' => 'Cotton', 'children' => []],
                        ],
                    ],
                    ['link' => 'https://shop.test/20-pants', 'name' => 'Pants', 'children' => []],
                ],
            ],
            'currentCategory' => 2,
        ]);
        $this->assertStringContainsString('Cotton', $html);
        $this->assertStringContainsString('Pants', $html);
    }

    public function testRootWithNoChildrenOmitsNestedList()
    {
        $html = $this->render([
            'categories' => ['link' => 'https://shop.test/2-home', 'name' => 'Home', 'children' => []],
            'currentCategory' => 2,
        ]);
        $this->assertStringContainsString('2-home', $html);
        $this->assertSame(1, substr_count($html, '<ul>'));
        $this->assertSame(1, substr_count($html, '<li>'));
    }

    private function render(array $assign)
    {
        foreach ($assign as $k => $v) {
            $this->smarty->assign($k, $v);
        }

        return $this->smarty->fetch('ps_categorytree.tpl');
    }
}
