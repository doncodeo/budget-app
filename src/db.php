<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            currency_symbol TEXT NOT NULL DEFAULT '₦',
            active_budget_id INTEGER,
            theme TEXT NOT NULL DEFAULT 'light',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            currency_code TEXT NOT NULL DEFAULT 'NGN',
            currency_symbol TEXT NOT NULL DEFAULT '₦',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budget_members (
            budget_id INTEGER NOT NULL REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            role TEXT NOT NULL DEFAULT 'member',
            PRIMARY KEY(budget_id, user_id)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            group_name TEXT NOT NULL DEFAULT 'General',
            basis TEXT NOT NULL CHECK (basis IN ('fixed','percent')),
            fixed_amount REAL,
            percent REAL,
            notes TEXT DEFAULT '',
            is_other INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            archived INTEGER NOT NULL DEFAULT 0
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS years (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            year INTEGER NOT NULL
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            salary REAL NOT NULL DEFAULT 0
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS other_income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            entry_date TEXT,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT '',
            amount REAL NOT NULL DEFAULT 0,
            notes TEXT DEFAULT ''
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS other_expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            entry_date TEXT,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            amount REAL NOT NULL DEFAULT 0,
            notes TEXT DEFAULT ''
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tracker_actuals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            actual REAL NOT NULL DEFAULT 0
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transfers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            entry_date TEXT,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            from_category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            to_category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            amount REAL NOT NULL DEFAULT 0,
            reason TEXT DEFAULT '',
            approved TEXT NOT NULL DEFAULT 'Pending'
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transfer_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER NOT NULL REFERENCES budgets(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            from_category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            to_category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            amount REAL NOT NULL DEFAULT 0,
            reason TEXT DEFAULT ''
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS income_allocations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            amount REAL NOT NULL DEFAULT 0,
            source_type TEXT NOT NULL DEFAULT 'Other Income',
            notes TEXT DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    migrate_schema($pdo);
}

function migrate_schema(PDO $pdo): void
{
    // 1. Ensure columns exist if schema was older version
    $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasActiveBudgetId = false;
    $hasTheme = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'active_budget_id') $hasActiveBudgetId = true;
        if ($col['name'] === 'theme') $hasTheme = true;
    }
    if (!$hasActiveBudgetId) {
        $pdo->exec("ALTER TABLE users ADD COLUMN active_budget_id INTEGER;");
    }
    if (!$hasTheme) {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'light';");
    }

    // Ensure budget_id on data tables
    $dataTables = ['categories', 'years', 'income', 'other_income', 'other_expenses', 'tracker_actuals', 'transfers', 'income_allocations'];
    foreach ($dataTables as $table) {
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $hasBudgetId = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'budget_id') $hasBudgetId = true;
        }
        if (!$hasBudgetId) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN budget_id INTEGER REFERENCES budgets(id) ON DELETE CASCADE;");
        }
    }

    // 2. Migrate existing users without a budget
    $users = $pdo->query("SELECT id, username, currency_symbol, active_budget_id FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $userId = (int)$u['id'];
        $budgetStmt = $pdo->prepare("SELECT budget_id FROM budget_members WHERE user_id = ?");
        $budgetStmt->execute([$userId]);
        $budgetId = $budgetStmt->fetchColumn();

        if (!$budgetId) {
            $bName = $u['username'] . "'s Budget";
            $sym = $u['currency_symbol'] ?: '₦';
            $code = $sym === '₦' ? 'NGN' : ($sym === '$' ? 'USD' : ($sym === '€' ? 'EUR' : ($sym === '£' ? 'GBP' : 'USD')));
            $insB = $pdo->prepare("INSERT INTO budgets (name, owner_id, currency_code, currency_symbol) VALUES (?, ?, ?, ?)");
            $insB->execute([$bName, $userId, $code, $sym]);
            $budgetId = (int)$pdo->lastInsertId();

            $insM = $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'owner')");
            $insM->execute([$budgetId, $userId]);
        }

        if (!$u['active_budget_id']) {
            $updU = $pdo->prepare("UPDATE users SET active_budget_id = ? WHERE id = ?");
            $updU->execute([$budgetId, $userId]);
        }

        // Backfill data tables with budget_id where user_id matches and budget_id IS NULL
        foreach ($dataTables as $table) {
            $updD = $pdo->prepare("UPDATE $table SET budget_id = ? WHERE user_id = ? AND budget_id IS NULL");
            $updD->execute([$budgetId, $userId]);
        }
    }

    // Auto-repair date vs year/month mismatch for logs
    repair_mismatched_dates($pdo);
}

function repair_mismatched_dates(PDO $pdo): void
{
    $logTables = ['other_income', 'other_expenses', 'transfers'];
    foreach ($logTables as $table) {
        $rows = $pdo->query("SELECT id, entry_date, year, month FROM $table WHERE entry_date IS NOT NULL AND entry_date != ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $ts = strtotime($r['entry_date']);
            if ($ts !== false) {
                $correctYear = (int)date('Y', $ts);
                $correctMonth = MONTHS[(int)date('n', $ts) - 1];
                if ($r['year'] !== $correctYear || $r['month'] !== $correctMonth) {
                    $stmt = $pdo->prepare("UPDATE $table SET year = ?, month = ? WHERE id = ?");
                    $stmt->execute([$correctYear, $correctMonth, $r['id']]);
                }
            }
        }
    }
}

/** Create a brand new user's default budget + categories + current year. */
function seed_new_user(PDO $pdo, int $userId, int $year): void
{
    $uStmt = $pdo->prepare("SELECT username, currency_symbol FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    $budgetName = ($u['username'] ?? 'My') . "'s Budget";
    $sym = $u['currency_symbol'] ?? '₦';
    $code = $sym === '₦' ? 'NGN' : ($sym === '$' ? 'USD' : ($sym === '€' ? 'EUR' : ($sym === '£' ? 'GBP' : 'USD')));

    $insB = $pdo->prepare("INSERT INTO budgets (name, owner_id, currency_code, currency_symbol) VALUES (?, ?, ?, ?)");
    $insB->execute([$budgetName, $userId, $code, $sym]);
    $budgetId = (int)$pdo->lastInsertId();

    $insM = $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'owner')");
    $insM->execute([$budgetId, $userId]);

    $updU = $pdo->prepare("UPDATE users SET active_budget_id = ? WHERE id = ?");
    $updU->execute([$budgetId, $userId]);

    $stmt = $pdo->prepare(
        'INSERT INTO categories (budget_id, user_id, name, group_name, basis, fixed_amount, percent, notes, is_other, sort_order)
         VALUES (:budget_id, :user_id, :name, :group_name, :basis, :fixed_amount, :percent, :notes, :is_other, :sort_order)'
    );
    foreach (DEFAULT_CATEGORIES as $i => $cat) {
        [$name, $group, $basis, $fixed, $percent, $notes, $isOther] = $cat;
        $stmt->execute([
            ':budget_id' => $budgetId,
            ':user_id' => $userId,
            ':name' => $name,
            ':group_name' => $group,
            ':basis' => $basis,
            ':fixed_amount' => $fixed,
            ':percent' => $percent,
            ':notes' => $notes,
            ':is_other' => $isOther,
            ':sort_order' => $i,
        ]);
    }

    ensure_year($pdo, $userId, $year, $budgetId);
}

/** Make sure a year exists for a budget: a years row + 12 income rows. */
function ensure_year(PDO $pdo, int $userId, int $year, ?int $budgetId = null): void
{
    if ($budgetId === null) {
        $uStmt = $pdo->prepare("SELECT active_budget_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $budgetId = (int)$uStmt->fetchColumn();
    }

    $stmt = $pdo->prepare('SELECT id FROM years WHERE budget_id = ? AND year = ?');
    $stmt->execute([$budgetId, $year]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO years (budget_id, user_id, year) VALUES (?, ?, ?)')->execute([$budgetId, $userId, $year]);
    }

    $stmt = $pdo->prepare('SELECT id FROM income WHERE budget_id = ? AND year = ? AND month = ?');
    foreach (MONTHS as $m) {
        $stmt->execute([$budgetId, $year, $m]);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO income (budget_id, user_id, year, month, salary) VALUES (?, ?, ?, ?, 0)')->execute([$budgetId, $userId, $year, $m]);
        }
    }
}
