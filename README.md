# Personal Finance Budget — Web App

A PHP + SQLite rebuild of the budget spreadsheet, with multi-user login. Every
feature from the workbook is here:

1. **Variable monthly income** — enter salary month by month on the Income
   page; any category set to "% of Salary" recalculates automatically.
2. **Other Income & unplanned expenses** — dedicated logs that roll up into
   the Income page and the Tracker's "Other / Unplanned Expense" line.
3. **Multiple years** — add a new year from the Dashboard at any time; older
   years are never touched.
4. **Bucket transfers/rollovers** — log a transfer once and both buckets'
   In/Out and Closing balances update automatically, no double entry.
5. **Dynamic allocations** — the Settings page defines each category as
   Fixed (same ₦ amount every month) or % of Salary (recalculates with
   income).
6. **Authentication** — each person registers their own account; all data
   (categories, income, tracker actuals, logs, transfers) is scoped to that
   user.

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension (on Debian/Ubuntu:
  `sudo apt install php-cli php-sqlite3`, plus `php-fpm` or `libapache2-mod-php`
  for a production web server)
- No other dependencies — the database is a single SQLite file created
  automatically on first run

## Running it locally

```bash
cd budget-app
php -S localhost:8000 -t public
```

Then open http://localhost:8000 in your browser, click **Create one**, and
register your first account. The database file is created automatically at
`data/budget.sqlite` the first time the app runs.

## Deploying to a real server (Apache/Nginx + PHP-FPM)

1. Point the web server's document root at the `public/` folder — `src/` and
   `data/` should **not** be web-accessible.
2. Make sure the `data/` folder is writable by the PHP process
   (`chown www-data:www-data data && chmod 770 data` on most setups).
3. Serve the site over HTTPS in production — login/session cookies are sent
   over plain HTTP otherwise.
4. Nothing else to configure: the SQLite database and its tables are created
   automatically on first request.

## Project structure

```
budget-app/
├── public/            # web root — every file here is a page
│   ├── index.php
│   ├── register.php / login.php / logout.php
│   ├── dashboard.php
│   ├── settings.php       (category rules: Fixed vs % of Salary)
│   ├── income.php         (monthly salary per year)
│   ├── tracker.php        (budget vs actual, per month)
│   ├── transfers.php      (bucket rollover log)
│   ├── other_income.php   (income log)
│   └── other_expenses.php (unplanned-expense log)
├── src/                # not web-accessible — shared PHP
│   ├── config.php     (constants, default category template, session start)
│   ├── db.php          (PDO connection + schema + seeding)
│   ├── auth.php         (register/login/logout/CSRF)
│   ├── helpers.php      (all budget math)
│   └── layout_header.php / layout_footer.php
└── data/
    └── budget.sqlite   (created automatically; back this file up)
```

## How the numbers are calculated

For a given category, year, and month:

- **Budget** = fixed amount, or `percent × that month's salary` if the
  category's basis is "% of Salary".
- **Actual** = what you typed on the Tracker, or — for the built-in
  "Other / Unplanned Expense" category — the sum of that month's rows in
  the Other Expense Log.
- **In** = sum of Transfers where this category is the "To" bucket for that
  year/month.
- **Out** = sum of Transfers where this category is the "From" bucket for
  that year/month.
- **Closing** = `Budget − Actual + In − Out`.

## Security notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- All database access uses parameterised queries (PDO prepared statements).
- Every POST form includes a CSRF token checked on submit.
- All data is scoped by `user_id` at the query level, so one account can
  never see or modify another's budget.

This is a solid starting point for personal or small-family use. Before
exposing it on the open internet, consider adding rate-limiting on login,
a "forgot password" flow (currently there isn't one), and HTTPS termination
at your web server or load balancer.
