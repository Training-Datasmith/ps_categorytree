<?php
/**
 * Minimal PrestaShop runtime doubles for ps_categorytree unit tests.
 */

namespace PrestaShop\PrestaShop\Core\Module {
    interface WidgetInterface
    {
        public function renderWidget($hookName = null, array $configuration = []);

        public function getWidgetVariables($hookName = null, array $configuration = []);
    }
}

namespace {
class TransLog
{
    /** @var array<int, array{0: string, 1: string}> */
    public static $calls = [];

    public static function log($id, $domain)
    {
        self::$calls[] = [$id, $domain];
    }

    public static function reset()
    {
        self::$calls = [];
    }
}

class RuntimeState
{
    public static function resetAll()
    {
        ConfigurationStore::reset();
        ToolsStore::reset();
        CategoryRegistry::reset();
        ValidateStore::reset();
        CustomerStore::reset();
        ModuleRuntime::reset();
        HelperFormStore::reset();
        LinkDouble::reset();
        Context::reset();
        SmartyDouble::reset();
        TransLog::reset();
    }
}

class ConfigurationStore
{
    /** @var array<string, mixed> */
    public static $values = [];

    /** @var array<int, array{0: string, 1: mixed}> */
    public static $updateLog = [];

    /** @var array<string, bool> */
    public static $deleteResults = [];

    /** @var array<int, string> */
    public static $deleteLog = [];

    public static function reset()
    {
        self::$values = [
            'BLOCK_CATEG_MAX_DEPTH' => '4',
            'BLOCK_CATEG_SORT_WAY' => '0',
            'BLOCK_CATEG_SORT' => '0',
            'BLOCK_CATEG_ROOT_CATEGORY' => '1',
            'PS_HOME_CATEGORY' => '2',
        ];
    }

    public static function resetDeleteResults()
    {
        self::$deleteResults = [];
        self::$deleteLog = [];
        self::$updateLog = [];
    }

    public static function get($key)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : null;
    }

    public static function updateValue($key, $value)
    {
        self::$values[$key] = $value;
        self::$updateLog[] = [$key, $value];
    }

    public static function deleteByName($key)
    {
        self::$deleteLog[] = $key;
        if (!array_key_exists($key, self::$deleteResults)) {
            return true;
        }

        return self::$deleteResults[$key];
    }
}

class Configuration
{
    public static function get($key)
    {
        return ConfigurationStore::get($key);
    }

    public static function updateValue($key, $value)
    {
        ConfigurationStore::updateValue($key, $value);
    }

    public static function deleteByName($key)
    {
        return ConfigurationStore::deleteByName($key);
    }
}

class ToolsStore
{
    /** @var array<string, mixed> */
    public static $posted = [];

    public static $submitBlockCategories = false;

    /** @var array<int, string> */
    public static $redirectLog = [];

    public static function reset()
    {
        self::$posted = [];
        self::$submitBlockCategories = false;
        self::$redirectLog = [];
    }
}

class Tools
{
    public static function isSubmit($name)
    {
        return 'submitBlockCategories' === $name && ToolsStore::$submitBlockCategories;
    }

    public static function getValue($key, $default = null)
    {
        if (array_key_exists($key, ToolsStore::$posted)) {
            return ToolsStore::$posted[$key];
        }

        return $default;
    }

    public static function redirectAdmin($url)
    {
        ToolsStore::$redirectLog[] = $url;
        throw new RuntimeException('redirectAdmin');
    }

    public static function getAdminTokenLite($controller)
    {
        return 'token-for-' . $controller;
    }
}

class LinkDouble
{
    public static $categoryLinks = [];

    /** @var array<int, array> */
    public static $adminLinkLog = [];

    /** @var array{0: int, 1: string}|null */
    public static $lastCategoryLinkArgs;

    public static function reset()
    {
        self::$categoryLinks = [
            10 => 'https://shop.test/10-clothes',
            11 => 'https://shop.test/11-shirts',
            2 => 'https://shop.test/2-home',
            12 => 'https://shop.test/12-dresses',
            13 => 'https://shop.test/13-outer',
        ];
        self::$adminLinkLog = [];
        self::$lastCategoryLinkArgs = null;
    }

    public function getCategoryLink($id, $rewrite)
    {
        LinkDouble::$lastCategoryLinkArgs = [(int) $id, $rewrite];

        return isset(self::$categoryLinks[(int) $id])
            ? self::$categoryLinks[(int) $id]
            : 'https://shop.test/' . (int) $id . '-' . $rewrite;
    }

    public function getAdminLink($controller, $withToken = true, $params = [], $extra = [])
    {
        self::$adminLinkLog[] = [$controller, $withToken, $params, $extra];

        return 'https://admin.test/' . $controller;
    }
}

class LanguageContext
{
    /** @var int */
    public $id = 2;
}

class CustomerContext
{
    /** @var int */
    public $id = 15;
}

class ShopContext
{
    /** @var int */
    public $id = 1;
}

class Context
{
    /** @var Context|null */
    private static $instance;

    /** @var LinkDouble */
    public $link;

    /** @var LanguageContext */
    public $language;

    /** @var CustomerContext */
    public $customer;

    /** @var ShopContext */
    public $shop;

    /** @var object|null */
    public $controller;

    public function __construct()
    {
        $this->link = new LinkDouble();
        $this->language = new LanguageContext();
        $this->customer = new CustomerContext();
        $this->shop = new ShopContext();
        $this->controller = null;
    }

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = new self();
    }
}

class SmartyDouble
{
    /** @var array<string, mixed> */
    public static $assigned = [];

    public static $fetchReturn = 'FETCHED';

    public static function reset()
    {
        self::$assigned = [];
        self::$fetchReturn = 'FETCHED';
    }

    public function assign($vars)
    {
        if (is_array($vars)) {
            foreach ($vars as $k => $v) {
                self::$assigned[$k] = $v;
            }
        }
    }
}

class ModuleRuntime
{
    public static $parentInstallResult = true;

    public static $parentUninstallResult = true;

    public static $hookRegistrationResult = true;

    /** @var array<int, string> */
    public static $registeredHooks = [];

    public static function reset()
    {
        self::$parentInstallResult = true;
        self::$parentUninstallResult = true;
        self::$hookRegistrationResult = true;
        self::$registeredHooks = [];
    }
}

class Module
{
    public $name;

    public $tab;

    public $version;

    public $author;

    public $bootstrap;

    public $displayName;

    public $description;

    /** @var array<string, string> */
    public $ps_versions_compliancy;

    public $table = 'module';

    /** @var Context */
    public $context;

    /** @var SmartyDouble */
    public $smarty;

    public function __construct()
    {
        $this->context = Context::getInstance();
        $this->smarty = new SmartyDouble();
    }

    public function install()
    {
        return ModuleRuntime::$parentInstallResult;
    }

    public function uninstall()
    {
        return ModuleRuntime::$parentUninstallResult;
    }

    public function registerHook($hook)
    {
        if (!ModuleRuntime::$hookRegistrationResult) {
            return false;
        }
        ModuleRuntime::$registeredHooks[] = $hook;

        return true;
    }

    public function displayError($msg)
    {
        return '<div class="error">' . $msg . '</div>';
    }

    public function trans($id, $params, $domain)
    {
        TransLog::log($id, $domain);
        $known = [
            'Category tree links' => 'Category tree links',
            'Help navigation on your store, show your visitors current category and subcategories.' => 'Help navigation on your store, show your visitors current category and subcategories.',
            'Maximum depth: Invalid number.' => 'Maximum depth: Invalid number.',
        ];

        return isset($known[$id]) ? $known[$id] : $id;
    }

    public function fetch($template)
    {
        return SmartyDouble::$fetchReturn . '|' . $template;
    }
}

class CustomerStore
{
    /** @var array<int, int> */
    public static $groupsByCustomer = [];

    public static function reset()
    {
        self::$groupsByCustomer = [15 => [3, 4]];
    }
}

class Customer
{
    public static function getGroupsStatic($customerId)
    {
        $id = (int) $customerId;

        return isset(CustomerStore::$groupsByCustomer[$id])
            ? CustomerStore::$groupsByCustomer[$id]
            : [];
    }
}

class ValidateStore
{
    /** @var array<int, bool> */
    public static $loadedByCategoryId = [];

    public static function reset()
    {
        self::$loadedByCategoryId = [];
    }
}

class Validate
{
    public static function isLoadedObject($object)
    {
        if (!is_object($object) || !$object instanceof Category) {
            return is_object($object);
        }
        $id = (int) $object->id;
        if (array_key_exists($id, ValidateStore::$loadedByCategoryId)) {
            return ValidateStore::$loadedByCategoryId[$id];
        }

        return $id > 0 && isset(CategoryRegistry::$definitions[$id]);
    }
}

class CategoryRegistry
{
    /** @var array<int, array<string, mixed>> */
    public static $definitions = [];

    /** @var mixed */
    public static $nestedCategoriesReturn;

    public static $useDefaultNested = true;

    /** @var array<int, array> */
    public static $constructorLog = [];

    /** @var array<int, array> */
    public static $getSubCategoriesLog = [];

    /** @var array<int, array<int, array>> */
    public static $subCategoriesById = [];

    /** @var array<int, mixed> */
    public static $subCategoriesReturns = [];

    /** @var array<int, array<int, array>> */
    public static $parentsById = [];

    /** @var array|null */
    public static $lastNestedArgs;

    /** @var array<int, array{0: int, 1: int}> */
    public static $checkAccessLog = [];

    /** @var array<int, array{0: int, 1: int}> */
    public static $existsInShopLog = [];

    /** @var array<int, bool> */
    public static $checkAccessById = [];

    /** @var array<int, bool> */
    public static $existsInShopById = [];

    /** @var array<int, bool> */
    public static $activeById = [];

    public static function reset()
    {
        self::$definitions = [
            2 => [
                'id' => 2, 'id_parent' => 0, 'level_depth' => 1, 'link_rewrite' => 'home',
                'name' => 'Home', 'description' => 'Home desc', 'is_root_category' => true, 'active' => true,
            ],
            10 => [
                'id' => 10, 'id_parent' => 2, 'level_depth' => 2, 'link_rewrite' => 'clothes',
                'name' => 'Clothes', 'description' => 'Clothes desc', 'is_root_category' => false, 'active' => true,
            ],
            11 => [
                'id' => 11, 'id_parent' => 10, 'level_depth' => 3, 'link_rewrite' => 'shirts',
                'name' => 'Shirts', 'description' => 'Shirts desc', 'is_root_category' => false, 'active' => true,
            ],
            12 => [
                'id' => 12, 'id_parent' => 2, 'level_depth' => 2, 'link_rewrite' => 'dresses',
                'name' => 'Dresses', 'description' => 'Dresses desc', 'is_root_category' => false, 'active' => true,
            ],
            13 => [
                'id' => 13, 'id_parent' => 12, 'level_depth' => 3, 'link_rewrite' => 'outer',
                'name' => 'Outer', 'description' => 'Outer desc', 'is_root_category' => false, 'active' => true,
            ],
        ];
        self::$nestedCategoriesReturn = null;
        self::$useDefaultNested = true;
        self::$constructorLog = [];
        self::$getSubCategoriesLog = [];
        self::$subCategoriesById = [10 => [['id_category' => 11]], 11 => []];
        self::$subCategoriesReturns = [];
        self::$parentsById = [
            11 => [['id_category' => 2], ['id_category' => 10], ['id_category' => 11]],
            10 => [['id_category' => 2], ['id_category' => 10]],
        ];
        self::$lastNestedArgs = null;
        self::$checkAccessLog = [];
        self::$existsInShopLog = [];
        self::$checkAccessById = [];
        self::$existsInShopById = [];
        self::$activeById = [];
    }

    public static function defaultNestedTree()
    {
        return [
            10 => [
                'id_category' => 10,
                'link_rewrite' => 'clothes',
                'name' => 'Clothes',
                'description' => 'Clothes desc',
                'children' => [
                    [
                        'id_category' => 11,
                        'link_rewrite' => 'shirts',
                        'name' => 'Shirts',
                        'description' => 'Shirts desc',
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
    }
}

class Category
{
    public $id;

    public $id_parent;

    public $level_depth;

    public $link_rewrite;

    public $name;

    public $description;

    public $is_root_category;

    public $active;

    public function __construct($id, $langId = null)
    {
        $this->id = (int) $id;
        $langId = null === $langId ? 2 : (int) $langId;
        CategoryRegistry::$constructorLog[] = [$this->id, $langId];
        if (!isset(CategoryRegistry::$definitions[$this->id])) {
            return;
        }
        $def = CategoryRegistry::$definitions[$this->id];
        $this->id_parent = $def['id_parent'];
        $this->level_depth = $def['level_depth'];
        $this->link_rewrite = $def['link_rewrite'];
        $this->name = $def['name'];
        $this->description = $def['description'];
        $this->is_root_category = $def['is_root_category'];
        $this->active = isset(CategoryRegistry::$activeById[$this->id])
            ? CategoryRegistry::$activeById[$this->id]
            : $def['active'];
    }

    public static function getNestedCategories(
        $idRootCategory,
        $idLang,
        $active,
        $groups,
        $useShopRestriction,
        $sqlFilter,
        $orderBy
    ) {
        CategoryRegistry::$lastNestedArgs = [
            $idRootCategory, $idLang, $active, $groups, $useShopRestriction, $sqlFilter, $orderBy,
        ];

        if (CategoryRegistry::$useDefaultNested) {
            return CategoryRegistry::defaultNestedTree();
        }

        return CategoryRegistry::$nestedCategoriesReturn;
    }

    public function getSubCategories($idLang, $active = true)
    {
        CategoryRegistry::$getSubCategoriesLog[] = [$this->id, $idLang, $active];
        if (array_key_exists($this->id, CategoryRegistry::$subCategoriesReturns)) {
            return CategoryRegistry::$subCategoriesReturns[$this->id];
        }
        if (!isset(CategoryRegistry::$subCategoriesById[$this->id])) {
            return [];
        }

        return CategoryRegistry::$subCategoriesById[$this->id];
    }

    public function getParentsCategories()
    {
        return isset(CategoryRegistry::$parentsById[$this->id])
            ? CategoryRegistry::$parentsById[$this->id]
            : [['id_category' => $this->id]];
    }

    public function checkAccess($customerId)
    {
        CategoryRegistry::$checkAccessLog[] = [$this->id, (int) $customerId];
        if (isset(CategoryRegistry::$checkAccessById[$this->id])) {
            return CategoryRegistry::$checkAccessById[$this->id];
        }

        return true;
    }

    public function existsInShop($shopId)
    {
        CategoryRegistry::$existsInShopLog[] = [$this->id, (int) $shopId];
        if (isset(CategoryRegistry::$existsInShopById[$this->id])) {
            return CategoryRegistry::$existsInShopById[$this->id];
        }

        return true;
    }
}

class CategoryController
{
}

class ProductController
{
}

class HelperFormStore
{
    /** @var array<int, HelperForm> */
    public static $instances = [];

    public static function reset()
    {
        self::$instances = [];
    }
}

class HelperForm
{
    public $show_toolbar;

    public $table;

    public $submit_action;

    public $currentIndex;

    public $token;

    /** @var array<string, mixed> */
    public $tpl_vars = [];

    /** @var array */
    public $lastGenerateFormArgs;

    public static $generateReturn = 'FORM_HTML';

    public function __construct()
    {
        HelperFormStore::$instances[] = $this;
    }

    public function generateForm($forms)
    {
        $this->lastGenerateFormArgs = $forms;

        return self::$generateReturn;
    }
}

} // namespace
