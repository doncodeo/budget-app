<?php
declare(strict_types=1);

use App\View;

function render_template(string $template, array $data = []): void
{
    $user = current_user();
    $pdo = get_db();

    $globalYears = [];
    $globalSelectedYear = (int)date('Y');
    $globalSelectedMonth = MONTHS[(int)date('n') - 1];

    if ($user) {
        $userId = (int)$user['id'];
        $globalYears = get_years($pdo, $userId);
        if (empty($globalYears)) {
            ensure_year($pdo, $userId, (int)date('Y'));
            $globalYears = get_years($pdo, $userId);
        }
        $globalSelectedYear = (int)($_GET['year'] ?? $data['selectedYear'] ?? $globalYears[0]);
        if (!in_array($globalSelectedYear, $globalYears, true)) {
            $globalSelectedYear = $globalYears[0];
        }
        $globalSelectedMonth = $_GET['month'] ?? $data['selectedMonth'] ?? MONTHS[(int)date('n') - 1];
        if (!in_array($globalSelectedMonth, MONTHS, true)) {
            $globalSelectedMonth = MONTHS[0];
        }
    }

    $context = array_merge([
        'user' => $user,
        'APP_NAME' => APP_NAME,
        'MONTHS' => MONTHS,
        'globalYears' => $globalYears,
        'globalMonths' => MONTHS,
        'globalSelectedYear' => $globalSelectedYear,
        'globalSelectedMonth' => $globalSelectedMonth,
        'currentQueryParams' => $_GET,
    ], $data);

    View::render($template, $context);
}
