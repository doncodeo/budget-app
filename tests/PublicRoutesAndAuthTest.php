<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

class PublicRoutesAndAuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        init_schema($this->pdo);
    }

    public function testCurrentUserReturnsNullWhenLoggedOut(): void
    {
        $_SESSION = [];
        $this->assertNull(current_user());
    }

    public function testPublicPagesExecuteWithoutSession(): void
    {
        $_SESSION = [];
        $user = current_user();
        $this->assertNull($user);

        // Verify public/about.php script can be required without throwing authentication redirects or errors
        ob_start();
        require __DIR__ . '/../public/about.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('About & System Tutorial', $output);
        $this->assertStringContainsString('Ready To Assign', $output);
        $this->assertStringContainsString('Log In', $output);
    }

    public function testHouseholdAuthorizationIsolation(): void
    {
        // Seed Household A and Household B
        $this->pdo->prepare("INSERT INTO users (id, username, password_hash) VALUES (1, 'userA', 'hash')")->execute();
        seed_new_user($this->pdo, 1, 2026);
        $budgetA = get_active_budget_id($this->pdo, 1);

        $this->pdo->prepare("INSERT INTO users (id, username, password_hash) VALUES (2, 'userB', 'hash')")->execute();
        seed_new_user($this->pdo, 2, 2026);
        $budgetB = get_active_budget_id($this->pdo, 2);

        $this->assertNotEquals($budgetA, $budgetB);

        // User A sets salary = 500,000 and logs 100,000 extra income
        $this->pdo->prepare('UPDATE income SET salary = 500000 WHERE budget_id = ? AND year = 2026 AND month = "Sep"')->execute([$budgetA]);
        $this->pdo->prepare('INSERT INTO other_income (budget_id, user_id, entry_date, year, month, source, amount) VALUES (?, 1, "2026-09-01", 2026, "Sep", "Bonus", 100000)')->execute([$budgetA]);

        // User B calculates summary for Sep 2026
        $summaryB = calculate_budget_summary($this->pdo, 2, 2026, 'Sep', $budgetB);

        // User B must see 0 income and 0 extra income from User A's budget
        $this->assertEquals(0.0, $summaryB['total_income']);
        $this->assertEquals(0.0, $summaryB['other_income']);
        $this->assertEquals(0.0, $summaryB['total_allocations']);
    }
}
