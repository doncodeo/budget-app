<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

class HelpersTest extends TestCase
{
    public function testCategoryBudgetFixed(): void
    {
        $cat = [
            'basis' => 'fixed',
            'fixed_amount' => 45000.0,
            'percent' => null
        ];
        $this->assertEquals(45000.0, category_budget($cat, 500000.0));
    }

    public function testCategoryBudgetPercent(): void
    {
        $cat = [
            'basis' => 'percent',
            'fixed_amount' => null,
            'percent' => 0.10
        ];
        $this->assertEquals(50000.0, category_budget($cat, 500000.0));
    }

    public function testFmtMoney(): void
    {
        $this->assertEquals('₦45,000', fmt_money(45000.0, '₦'));
        $this->assertEquals('$1,235', fmt_money(1234.56, '$'));
    }
}
