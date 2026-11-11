
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: ../dashboard.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

try {
    $totalUsers = $pdo->query("
        SELECT COUNT(*) FROM users
    ")->fetchColumn();

    $totalItems = $pdo->query("
        SELECT COUNT(*) FROM items
    ")->fetchColumn();

    $pendingItems = $pdo->query("
        SELECT COUNT(*) FROM items WHERE status = 'Pending'
    ")->fetchColumn();

    $approvedItems = $pdo->query("
        SELECT COUNT(*) FROM items WHERE status = 'Approved'
    ")->fetchColumn();

    $rejectedItems = $pdo->query("
        SELECT COUNT(*) FROM items WHERE status = 'Rejected'
    ")->fetchColumn();

    $pendingClaims = $pdo->query("
        SELECT COUNT(*) FROM claims WHERE status = 'Pending'
    ")->fetchColumn();

    $recentItems = $pdo->query("
        SELECT i.item_name, i.item_type, i.status,
               i.date_posted, u.full_name
        FROM items i
        INNER JOIN users u ON i.user_id = u.user_id
        ORDER BY i.date_posted DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    die("Unable to load the admin dashboard.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | FindIt</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <script>
        (function () {
            const savedTheme = localStorage.getItem("findit-theme");
            const theme = savedTheme === "dark" ? "dark" : "light";

            document.documentElement.setAttribute("data-theme", theme);
        })();
    </script>

    <style>
        :root {
            color-scheme: light;
        }

        :root[data-theme="dark"] {
            color-scheme: dark;

            --celadon: #315B43;
            --light-celadon: #263D30;
            --emerald: #78D5A0;
            --deep-emerald: #A5E8BC;
            --soft-green: #101A15;
            --white: #18251D;
            --text: #E5F1E8;
            --muted: #A7B8AC;
            --border: #30463A;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--soft-green);
            color: var(--text);
            margin: 0;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .admin-layout {
            min-height: 100vh;
        }

        /* HEADER */

        .admin-header {
            background: var(--white);
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            gap: 15px;
            flex-wrap: wrap;
            transition: background 0.25s ease;
        }

        .admin-header .logo {
            text-decoration: none;
            font-size: 27px;
            font-weight: bold;
            color: var(--deep-emerald);
        }

        .admin-header .logo span {
            color: var(--emerald);
        }

        .admin-nav {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            align-items: center;
        }

        .admin-nav a {
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            color: var(--emerald);
        }

        .admin-logout {
            padding: 9px 16px;
            border: 1px solid var(--emerald);
            border-radius: 8px;
            color: var(--emerald) !important;
        }

        /* ICON-ONLY THEME BUTTON */

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--light-celadon);
            color: var(--deep-emerald);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .theme-toggle:hover {
            border-color: var(--emerald);
            transform: translateY(-1px);
        }

        .theme-toggle span {
            line-height: 1;
        }

        /* MAIN */

        .admin-main {
            padding: 45px 7%;
        }

        .admin-welcome {
            margin-bottom: 30px;
        }

        .admin-welcome h1 {
            color: var(--deep-emerald);
            margin-bottom: 8px;
        }

        .admin-welcome p {
            color: var(--muted);
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .stat-card p {
            color: var(--muted);
            margin: 0 0 12px;
            font-size: 14px;
        }

        .stat-card h2 {
            color: var(--deep-emerald);
            font-size: 32px;
            margin: 0;
        }

        .stat-card a {
            display: inline-block;
            margin-top: 15px;
            color: var(--emerald);
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
        }

        .stat-card a:hover {
            text-decoration: underline;
        }

        /* PANELS */

        .admin-panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 25px;
            overflow-x: auto;
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .admin-panel h2 {
            color: var(--deep-emerald);
            margin-top: 0;
            margin-bottom: 20px;
        }

        /* TABLE */

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .admin-table th,
        .admin-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }

        .admin-table th {
            color: var(--deep-emerald);
            background: var(--light-celadon);
        }

        .admin-table tbody tr:hover {
            background: var(--soft-green);
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.Pending {
            background: #FFF2D6;
            color: #936000;
        }

        .status.Approved {
            background: #DDF4E5;
            color: #176B3A;
        }

        .status.Rejected {
            background: #FCE5E5;
            color: #A52D2D;
        }

        :root[data-theme="dark"] .status.Pending {
            background: #514321;
            color: #FFD98A;
        }

        :root[data-theme="dark"] .status.Approved {
            background: #204632;
            color: #A5E8BC;
        }

        :root[data-theme="dark"] .status.Rejected {
            background: #512D32;
            color: #FFB5B5;
        }

        /* QUICK LINKS */

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .quick-link {
            display: block;
            padding: 20px;
            background: var(--light-celadon);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--deep-emerald);
            font-weight: bold;
            transition: all 0.2s ease;
        }

        .quick-link:hover {
            border-color: var(--emerald);
            transform: translateY(-2px);
        }

        .quick-link span {
            display: block;
            font-size: 13px;
            font-weight: normal;
            margin-top: 7px;
            color: var(--muted);
        }

        .empty-message {
            text-align: center;
            color: var(--muted);
            padding: 25px;
        }

        /* RESPONSIVE */

        @media (max-width: 850px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-links {
                grid-template-columns: 1fr;
            }

            .admin-header {
                padding: 18px 4%;
            }

            .admin-nav {
                gap: 13px;
            }
        }

        @media (max-width: 550px) {
            .admin-main {
                padding: 30px 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .admin-nav {
                width: 100%;
                gap: 12px;
            }

            .theme-toggle {
                margin-top: 5px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <header class="admin-header">

        <a href="dashboard.php" class="logo">
            Find<span>It.</span>
        </a>

        <nav class="admin-nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="manage-items.php">Manage Items</a>
            <a href="manage-claims.php">Manage Claims</a>
            <a href="manage-users.php">Manage Users</a>

            <button
                type="button"
                class="theme-toggle"
                id="themeToggle"
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Toggle Dark Mode"
            >
                <span id="themeIcon">🌙</span>
            </button>

            <a href="../logout.php" class="admin-logout">Log Out</a>
        </nav>

    </header>

    <main class="admin-main">

        <section class="admin-welcome">
            <p class="eyebrow">FINDIT ADMINISTRATION</p>

            <h1>Welcome, <?= e($_SESSION["full_name"] ?? "Admin") ?>!</h1>

            <p>Manage reports, claims, and registered FindIt users.</p>
        </section>

        <section class="stats-grid">

            <article class="stat-card">
                <p>Total Users</p>
                <h2><?= (int)$totalUsers ?></h2>
                <a href="manage-users.php">Manage Users →</a>
            </article>

            <article class="stat-card">
                <p>Total Reports</p>
                <h2><?= (int)$totalItems ?></h2>
                <a href="manage-items.php">View Reports →</a>
            </article>

            <article class="stat-card">
                <p>Pending Reports</p>
                <h2><?= (int)$pendingItems ?></h2>
                <a href="manage-items.php">Review Reports →</a>
            </article>

            <article class="stat-card">
                <p>Approved Reports</p>
                <h2><?= (int)$approvedItems ?></h2>
                <a href="manage-items.php">View Approved →</a>
            </article>

            <article class="stat-card">
                <p>Rejected Reports</p>
                <h2><?= (int)$rejectedItems ?></h2>
                <a href="manage-items.php">View Rejected →</a>
            </article>

            <article class="stat-card">
                <p>Pending Claims</p>
                <h2><?= (int)$pendingClaims ?></h2>
                <a href="manage-claims.php">Review Claims →</a>
            </article>

        </section>

        <section class="admin-panel">

            <h2>Recent Item Reports</h2>

            <?php if (count($recentItems) > 0): ?>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th>Date Posted</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentItems as $item): ?>
                            <tr>
                                <td><?= e($item["item_name"]) ?></td>
                                <td><?= e($item["item_type"]) ?></td>
                                <td><?= e($item["full_name"]) ?></td>
                                <td>
                                    <span class="status <?= e($item["status"]) ?>">
                                        <?= e($item["status"]) ?>
                                    </span>
                                </td>
                                <td><?= e($item["date_posted"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>

                <div class="empty-message">
                    No reports have been submitted yet.
                </div>

            <?php endif; ?>

        </section>

        <section class="admin-panel">

            <h2>Quick Management</h2>

            <div class="quick-links">

                <a href="manage-items.php" class="quick-link">
                    Manage Item Reports
                    <span>Approve or reject submitted reports.</span>
                </a>

                <a href="manage-claims.php" class="quick-link">
                    Manage Claims
                    <span>Review users' claim requests.</span>
                </a>

                <a href="manage-users.php" class="quick-link">
                    Manage Users
                    <span>View registered FindIt accounts.</span>
                </a>

            </div>

        </section>

    </main>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");

    function applyTheme(theme) {

        document.documentElement.setAttribute("data-theme", theme);

        localStorage.setItem("findit-theme", theme);

        if (theme === "dark") {
            themeIcon.textContent = "☀️";
            themeToggle.setAttribute("aria-label", "Switch to light mode");
            themeToggle.setAttribute("aria-pressed", "true");
            themeToggle.setAttribute("title", "Switch to Light Mode");
        } else {
            themeIcon.textContent = "🌙";
            themeToggle.setAttribute("aria-label", "Switch to dark mode");
            themeToggle.setAttribute("aria-pressed", "false");
            themeToggle.setAttribute("title", "Switch to Dark Mode");
        }
    }

    const savedTheme = localStorage.getItem("findit-theme");

    applyTheme(savedTheme === "dark" ? "dark" : "light");

    themeToggle.addEventListener("click", function () {

        const currentTheme =
            document.documentElement.getAttribute("data-theme");

        const newTheme = currentTheme === "dark" ? "light" : "dark";

        applyTheme(newTheme);

    });

});
</script>

</body>
</html>
