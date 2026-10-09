<?php

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.7.0');
}

require __DIR__ . '/Double/PrestaShopRuntime.php';

$smartyAutoload = getenv('PS_CATEGORYTREE_SMARTY_AUTOLOAD');
if (false === $smartyAutoload || '' === $smartyAutoload) {
    throw new RuntimeException('PS_CATEGORYTREE_SMARTY_AUTOLOAD must point to Smarty vendor autoload outside the repo');
}
if (!is_readable($smartyAutoload)) {
    throw new RuntimeException('Smarty autoload not readable: ' . $smartyAutoload);
}
require $smartyAutoload;

require dirname(__DIR__) . '/ps_categorytree.php';

require __DIR__ . '/TestCase.php';
