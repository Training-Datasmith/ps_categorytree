<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_categorytree PrestaShop module.
 *
 * ps_categorytree renders a collapsible/expandable category navigation tree
 * in the sidebar. It integrates via PrestaShop's widget system and the
 * Category ObjectModel hierarchy.
 *
 * This file documents common usage patterns.
 */

// --- Widget invocation in Smarty/Twig template ---
// {widget name="ps_categorytree" hook="displayLeftColumn"}

// --- Querying the category tree programmatically ---
// PrestaShop's Category class provides tree traversal methods:
//
// $rootCategory = Category::getRootCategory();
//
// // Get all active top-level categories
// $topLevel = Category::getChildren(
//     $rootCategory->id,
//     Context::getContext()->language->id,
//     active: true,
// );
//
// foreach ($topLevel as $cat) {
//     echo $cat['name'] . ' (ID: ' . $cat['id_category'] . ")\n";
//
//     $children = Category::getChildren(
//         $cat['id_category'],
//         Context::getContext()->language->id,
//     );
//
//     foreach ($children as $child) {
//         echo "  -- " . $child['name'] . "\n";
//     }
// }

// --- Getting category link ---
// $link = Context::getContext()->link;
// $categoryUrl = $link->getCategoryLink($categoryId, $categoryRewrite);

// --- Back Office configuration ---
// Modules > Category Tree:
//   - Maximum depth to display
//   - Show product count per category
//   - Expand all / collapse all by default

// --- Template override ---
// themes/{theme}/modules/ps_categorytree/views/templates/hook/ps_categorytree.tpl
