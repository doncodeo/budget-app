<?php
// Core app configuration. Included first by every entry-point page.

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . '/data/budget.sqlite');
define('APP_NAME', 'Personal Finance Budget');

// Default category template seeded for every new user (mirrors the
// original spreadsheet: name, group, basis, fixed_amount, percent, notes, is_other)
const DEFAULT_CATEGORIES = [
    ['Tithe', 'Giving', 'percent', null, 0.10, 'Adjusts automatically with salary', 0],
    ['Offering', 'Giving', 'fixed', 4500, null, '', 0],
    ['Transport', 'Transport & Feeding', 'fixed', 80000, null, '₦4,000 × 20 workdays', 0],
    ['Feeding/Lunch', 'Transport & Feeding', 'fixed', 40000, null, '₦2,000 × 20 workdays', 0],
    ['Foodstuff', 'Household & Family', 'fixed', 45000, null, 'Reduced to preserve a monthly buffer', 0],
    ["Parents' Allowance", 'Household & Family', 'fixed', 10000, null, '', 0],
    ['Baby Stuff', 'Household & Family', 'fixed', 25000, null, '', 0],
    ['Groceries', 'Household & Family', 'fixed', 10000, null, 'Reduced to preserve a monthly buffer', 0],
    ['Data', 'Communication & Utilities', 'fixed', 15000, null, 'Your share; wife contributes ₦10,000', 0],
    ['Electricity', 'Communication & Utilities', 'fixed', 10000, null, 'Monthly provision; unused amount can roll over', 0],
    ['Gas', 'Communication & Utilities', 'fixed', 10000, null, 'Monthly provision; unused amount can be transferred to savings', 0],
    ['Rent Savings', 'Future & Savings', 'fixed', 45000, null, '₦45,000 × 12 = ₦540,000 provision', 0],
    ['Emergency Fund', 'Future & Savings', 'percent', null, 0.065789, 'Adjusts automatically with salary', 0],
    ['Investment', 'Future & Savings', 'percent', null, 0.039474, 'Adjusts automatically with salary', 0],
    ['Monthly Buffer', 'Flexible', 'percent', null, 0.019737, 'Adjusts automatically with salary', 0],
    ['Other / Unplanned Expense', 'Other', 'fixed', 0, null, 'Actual pulls automatically from the Other Expense Log', 1],
];

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// PSR-4 fallback autoloader for App\ namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

// Minimal Twig PSR-4 autoloader for standalone environments without Composer autoloader
spl_autoload_register(function (string $class): void {
    if (strncmp('Twig\\', $class, 5) !== 0) {
        return;
    }
    $relativeClass = substr($class, 5);
    $file = APP_ROOT . '/vendor/twig/twig/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
