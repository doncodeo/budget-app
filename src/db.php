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
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
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
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            UNIQUE(user_id, year)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            salary REAL NOT NULL DEFAULT 0,
            UNIQUE(user_id, year, month)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS other_income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
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
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
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
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            year INTEGER NOT NULL,
            month TEXT NOT NULL,
            actual REAL NOT NULL DEFAULT 0,
            UNIQUE(category_id, year, month)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transfers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
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
}

/** Create a brand new user's default categories + current year. */
function seed_new_user(PDO $pdo, int $userId, int $year): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO categories (user_id, name, group_name, basis, fixed_amount, percent, notes, is_other, sort_order)
         VALUES (:user_id, :name, :group_name, :basis, :fixed_amount, :percent, :notes, :is_other, :sort_order)'
    );
    foreach (DEFAULT_CATEGORIES as $i => $cat) {
        [$name, $group, $basis, $fixed, $percent, $notes, $isOther] = $cat;
        $stmt->execute([
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

    ensure_year($pdo, $userId, $year);
}

/** Make sure a year exists for a user: a years row + 12 income rows. */
function ensure_year(PDO $pdo, int $userId, int $year): void
{
    $stmt = $pdo->prepare('SELECT id FROM years WHERE user_id = ? AND year = ?');
    $stmt->execute([$userId, $year]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO years (user_id, year) VALUES (?, ?)')->execute([$userId, $year]);
    }

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO income (user_id, year, month, salary) VALUES (?, ?, ?, 0)');
    foreach (MONTHS as $m) {
        $stmt->execute([$userId, $year, $m]);
    }
}
