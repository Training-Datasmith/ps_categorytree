# Architecture: ps_categorytree

## Purpose

A PrestaShop module that renders a navigable category tree widget in the front office,
allowing customers to browse products by category hierarchy.

## Directory Structure

```
ps_categorytree.php   # Main module class (WidgetInterface)
views/templates/       # Smarty/Twig templates for tree rendering
translations/          # Translation files
tests/                 # PHPStan and unit tests
```

## Key Design Decisions

Retrieves the full category tree from PrestaShop's `Category::getNestedCategories()`,
filtered by the current language and active status. Highlights the current category
path for navigation context. Renders a nested `<ul>` structure with CSS hooks for
accordion or full-tree display.

## Extension Points

Place in any sidebar or widget-compatible position. Override template in theme for
custom styling.
