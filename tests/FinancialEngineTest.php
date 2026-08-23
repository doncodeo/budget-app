<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

class FinancialEngineTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $budgetId;

    protected function setUp(): void
    {
        // Use in-memory SQLite database for test isolation
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        init_schema($this->pdo);

        // Seed test user and budget
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash) VALUES ('testuser', 'hash')");
        $stmt->execute();
        $this->userId = (int)$this->pdo->lastInsertId();

        seed_new_user($this->pdo, $this->userId, 2026);
        $this->budgetId = get_active_budget_id($this->pdo, $this->userId);
    }

    public function testReadyToAssignAndBufferCalculation(): void
    {
        // Set Salary = 385,000 for Sep 2026
        $stmt = $this->pdo->prepare('UPDATE income SET salary = ? WHERE budget_id = ? AND year = ? AND month = ?');
        $stmt->execute([385000, $this->budgetId, 2026, 'Sep']);

        // Set specific category budgets
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 373450 WHERE budget_id = ? AND name = 'Feeding/Lunch'")->execute([$this->budgetId]);
        // Set all other non-Buffer categories to basis='fixed', fixed_amount = 0
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 0, percent = 0 WHERE budget_id = ? AND name NOT IN ('Feeding/Lunch', 'Monthly Buffer')")->execute([$this->budgetId]);

        $summary = calculate_budget_summary($this->pdo, $this->userId, 2026, 'Sep', $this->budgetId);

        $this->assertEquals(385000.0, $summary['salary']);
        $this->assertEquals(0.0, $summary['other_income']);
        $this->assertEquals(385000.0, $summary['total_income']);
        $this->assertEquals(373450.0, $summary['base_planned']);
        $this->assertEquals(11550.0, $summary['buffer_base']); // 385000 - 373450
        $this->assertEquals(385000.0, $summary['total_base_planned']);
        $this->assertEquals(0.0, $summary['ready_to_assign']);
        $this->assertEquals('Balanced', $summary['budget_status']);

        // Now add Other Income = 100,000 (Bonus)
        $ins = $this->pdo->prepare('INSERT INTO other_income (budget_id, user_id, entry_date, year, month, source, amount) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$this->budgetId, $this->userId, '2026-09-15', 2026, 'Sep', 'Bonus', 100000]);

        $summary2 = calculate_budget_summary($this->pdo, $this->userId, 2026, 'Sep', $this->budgetId);

        $this->assertEquals(485000.0, $summary2['total_income']);
        $this->assertEquals(100000.0, $summary2['ready_to_assign']);
        $this->assertEquals('Under-allocated', $summary2['budget_status']);
        // Monthly Buffer base remains unchanged at 11,550
        $this->assertEquals(11550.0, $summary2['buffer_base']);
    }

    public function testIncomeAllocationToCategories(): void
    {
        $this->pdo->prepare('UPDATE income SET salary = ? WHERE budget_id = ? AND year = ? AND month = ?')->execute([385000, $this->budgetId, 2026, 'Sep']);
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 373450 WHERE budget_id = ? AND name = 'Feeding/Lunch'")->execute([$this->budgetId]);
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 0, percent = 0 WHERE budget_id = ? AND name NOT IN ('Feeding/Lunch', 'Monthly Buffer')")->execute([$this->budgetId]);

        // Add 100,000 extra income
        $this->pdo->prepare('INSERT INTO other_income (budget_id, user_id, entry_date, year, month, source, amount) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$this->budgetId, $this->userId, '2026-09-15', 2026, 'Sep', 'Bonus', 100000]);

        // Find Emergency Fund category ID
        $catStmt = $this->pdo->prepare("SELECT id FROM categories WHERE budget_id = ? AND name = 'Emergency Fund'");
        $catStmt->execute([$this->budgetId]);
        $emergencyCatId = (int)$catStmt->fetchColumn();

        // Allocate 40,000 to Emergency Fund
        $err = save_income_allocations($this->pdo, $this->userId, 2026, 'Sep', [$emergencyCatId => 40000], 'Bonus Allocation', $this->budgetId);
        $this->assertNull($err);

        $summary = calculate_budget_summary($this->pdo, $this->userId, 2026, 'Sep', $this->budgetId);
        $this->assertEquals(40000.0, $summary['total_allocations']);
        $this->assertEquals(425000.0, $summary['total_allocated']); // 385000 base + 40000 alloc
        $this->assertEquals(60000.0, $summary['ready_to_assign']);

        // Verify effective category budget on Tracker grid
        $trackerData = tracker_month($this->pdo, $this->userId, 2026, 'Sep', $this->budgetId);
        $emergencyRow = null;
        foreach ($trackerData['groups'] as $rows) {
            foreach ($rows as $row) {
                if ($row['category']['name'] === 'Emergency Fund') {
                    $emergencyRow = $row;
                }
            }
        }
        $this->assertNotNull($emergencyRow);
        $this->assertEquals(40000.0, $emergencyRow['budget']);
        $this->assertEquals(40000.0, $emergencyRow['allocations']);
    }

    public function testOverAllocationRejected(): void
    {
        $this->pdo->prepare('UPDATE income SET salary = ? WHERE budget_id = ? AND year = ? AND month = ?')->execute([385000, $this->budgetId, 2026, 'Sep']);
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 385000 WHERE budget_id = ? AND name = 'Feeding/Lunch'")->execute([$this->budgetId]);
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 0, percent = 0 WHERE budget_id = ? AND name NOT IN ('Feeding/Lunch', 'Monthly Buffer')")->execute([$this->budgetId]);

        // Ready To Assign = 0
        $catStmt = $this->pdo->prepare("SELECT id FROM categories WHERE budget_id = ? AND name = 'Investment'");
        $catStmt->execute([$this->budgetId]);
        $investmentCatId = (int)$catStmt->fetchColumn();

        // Attempt to allocate 50,000
        $err = save_income_allocations($this->pdo, $this->userId, 2026, 'Sep', [$investmentCatId => 50000], 'Other Income', $this->budgetId);
        $this->assertNotNull($err);
        $this->assertStringContainsString('Cannot allocate', $err);
        $this->assertStringContainsString('Only ₦0 is available', $err);

        // Verify no allocation saved in db
        $this->assertEquals(0.0, get_total_allocations_for_month($this->pdo, $this->userId, 2026, 'Sep', $this->budgetId));
    }

    public function testCategoryTransferValidationAndInvariants(): void
    {
        $this->pdo->prepare('UPDATE income SET salary = ? WHERE budget_id = ? AND year = ? AND month = ?')->execute([100000, $this->budgetId, 2026, 'Sep']);

        $catGas = $this->pdo->prepare("SELECT id FROM categories WHERE budget_id = ? AND name = 'Gas'");
        $catGas->execute([$this->budgetId]);
        $gasId = (int)$catGas->fetchColumn();

        $catSavings = $this->pdo->prepare("SELECT id FROM categories WHERE budget_id = ? AND name = 'Emergency Fund'");
        $catSavings->execute([$this->budgetId]);
        $savingsId = (int)$catSavings->fetchColumn();

        // Set Gas budget = 10,000 and actual spending = 6,000
        $this->pdo->prepare("UPDATE categories SET basis = 'fixed', fixed_amount = 10000 WHERE id = ?")->execute([$gasId]);
        set_actual($this->pdo, $this->userId, $gasId, 2026, 'Sep', 6000, $this->budgetId);

        $gasCat = ['id' => $gasId, 'name' => 'Gas', 'basis' => 'fixed', 'fixed_amount' => 10000, 'is_other' => 0];
        $gasRow = tracker_row($this->pdo, $this->userId, $gasCat, 2026, 'Sep', 100000, $this->budgetId);

        // Gas closing/available balance = 10,000 - 6,000 = 4,000
        $this->assertEquals(4000.0, $gasRow['closing']);

        // Attempt transfer 5,000 (exceeding available 4,000)
        $this->assertTrue(5000 > $gasRow['closing']);

        // Execute valid transfer 4,000
        $ins = $this->pdo->prepare('INSERT INTO transfers (budget_id, user_id, entry_date, year, month, from_category_id, to_category_id, amount, reason, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$this->budgetId, $this->userId, '2026-09-30', 2026, 'Sep', $gasId, $savingsId, 4000, 'Sweep unused gas', 'Yes']);

        // Re-check Gas row closing balance
        $gasRowAfter = tracker_row($this->pdo, $this->userId, $gasCat, 2026, 'Sep', 100000, $this->budgetId);
        $this->assertEquals(0.0, $gasRowAfter['closing']);
        $this->assertEquals(4000.0, $gasRowAfter['out']);

        // Verify Savings receives Transfers In = 4,000
        $savingsCat = ['id' => $savingsId, 'name' => 'Emergency Fund', 'basis' => 'fixed', 'fixed_amount' => 0, 'is_other' => 0];
        $savingsRow = tracker_row($this->pdo, $this->userId, $savingsCat, 2026, 'Sep', 100000, $this->budgetId);
        $this->assertEquals(4000.0, $savingsRow['in']);
        $this->assertEquals(4000.0, $savingsRow['closing']);
    }
}
