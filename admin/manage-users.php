
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

// Search
$search = trim($_GET["search"] ?? "");

// Filter by role
$roleFilter = $_GET["role"] ?? "All";
$allowedRoles = ["All", "Student", "Faculty", "Staff", "Admin"];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = "All";
}

// Retrieve users
$sql = "
    SELECT user_id, full_name, email, role, created_at
    FROM users
    WHERE 1 = 1
";

$params = [];

if ($search !== "") {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== "All") {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// User statistics
$countStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(role = 'Student') AS students,
        SUM(role = 'Faculty') AS faculty,
        SUM(role = 'Staff') AS staff,
        SUM(role = 'Admin') AS admins
    FROM users
");

$counts = $countStmt->fetch();

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Users | FindIt Admin</title>

<script>
(function () {
    const savedTheme = localStorage.getItem("findit-theme") || "light";
    document.documentElement.setAttribute("data-theme", savedTheme);
})();
</script>

<style>
:root {
    --celadon: #ACE1C0;
    --light-celadon: #E4F3E9;
    --emerald: #046A38;
    --deep-emerald: #024D2B;
    --soft-green: #F4FAF6;
    --white: #FFFFFF;
    --text: #183B2A;
    --muted: #698273;
    --border: #D3E8DA;
}

[data-theme="dark"] {
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
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: var(--soft-green);
    color: var(--text);
    transition: background .25s ease, color .25s ease;
}

.navbar {
    background: var(--white);
    padding: 18px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
}

.logo {
    color: var(--emerald);
    font-size: 25px;
    font-weight: 800;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-links a {
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
}

.nav-links a.active {
    color: var(--emerald);
}

.logout {
    background: var(--emerald);
    color: white !important;
    padding: 10px 17px;
    border-radius: 8px;
}

.theme-toggle {
    width: 40px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: var(--light-celadon);
    color: var(--emerald);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    cursor: pointer;
    transition: .2s ease;
}

.theme-toggle:hover {
    transform: translateY(-2px);
    background: var(--celadon);
}

.container {
    width: 90%;
    max-width: 1250px;
    margin: 35px auto;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.page-heading p {
    color: var(--muted);
}

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--white);
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.stat-card p {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 10px;
}

.stat-card h2 {
    color: var(--emerald);
    font-size: 27px;
}

.content-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 23px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.toolbar h2 {
    font-size: 20px;
}

.search-form {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.search-form input,
.search-form select {
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    font-size: 13px;
    outline: none;
    background: var(--white);
    color: var(--text);
}

.search-form input:focus,
.search-form select:focus {
    border-color: var(--emerald);
}

.search-form input::placeholder {
    color: var(--muted);
}

.search-form button {
    padding: 10px 16px;
    background: var(--emerald);
    color: white;
    border: none;
    border-radius: 7px;
    font-weight: 700;
    cursor: pointer;
}

.search-form .reset {
    background: var(--light-celadon);
    color: var(--emerald);
    text-decoration: none;
    padding: 10px 13px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    text-align: left;
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

th {
    color: var(--muted);
    background: var(--soft-green);
    font-size: 12px;
    text-transform: uppercase;
}

tbody tr:hover {
    background: var(--soft-green);
}

.user-name {
    font-weight: 700;
    margin-bottom: 4px;
}

.role {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.Student {
    background: #E4F3E9;
    color: #046A38;
}

.Faculty {
    background: #E8EAF6;
    color: #3949AB;
}

.Staff {
    background: #FFF3D6;
    color: #946200;
}

.Admin {
    background: #F1E5FA;
    color: #7137A3;
}

[data-theme="dark"] .Student {
    background: #234B35;
    color: #A5E8BC;
}

[data-theme="dark"] .Faculty {
    background: #30385D;
    color: #C5CBFF;
}

[data-theme="dark"] .Staff {
    background: #59471F;
    color: #FFE09A;
}

[data-theme="dark"] .Admin {
    background: #49315D;
    color: #E2C1FF;
}

.empty {
    text-align: center;
    padding: 45px 15px;
    color: var(--muted);
}

.empty h3 {
    color: var(--text);
    margin-bottom: 8px;
}

.result-count {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 15px;
}

@media (max-width: 950px) {
    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 600px) {
    .navbar {
        align-items: flex-start;
        gap: 15px;
        flex-direction: column;
    }

    .nav-links {
        flex-wrap: wrap;
        gap: 13px;
    }

    .container {
        width: 94%;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .content-card {
        padding: 15px;
    }

    .search-form {
        width: 100%;
    }

    .search-form input {
        flex: 1;
        min-width: 150px;
    }
}
</style>
</head>

<body>

<nav class="navbar">
    <a href="dashboard.php" class="logo">FindIt Admin</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage-items.php">Manage Items</a>
        <a href="manage-claims.php">Manage Claims</a>
        <a href="manage-users.php" class="active">Manage Users</a>

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Switch to dark mode"
        >🌙</button>

        <a href="../logout.php" class="logout">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="page-heading">
        <h1>Manage Users</h1>
        <p>View and monitor all registered FindIt accounts.</p>
    </div>

    <section class="stats">

        <div class="stat-card">
            <p>Total Users</p>
            <h2><?= (int)($counts["total"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Students</p>
            <h2><?= (int)($counts["students"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Faculty</p>
            <h2><?= (int)($counts["faculty"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Staff</p>
            <h2><?= (int)($counts["staff"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Admins</p>
            <h2><?= (int)($counts["admins"] ?? 0) ?></h2>
        </div>

    </section>

    <section class="content-card">

        <div class="toolbar">
            <h2>Registered Users</h2>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search name or email..."
                    value="<?= e($search) ?>"
                >

                <select name="role">
                    <?php foreach ($allowedRoles as $role): ?>
                        <option
                            value="<?= e($role) ?>"
                            <?= $roleFilter === $role ? "selected" : "" ?>
                        >
                            <?= e($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Search</button>

                <a href="manage-users.php" class="reset">Reset</a>

            </form>
        </div>

        <p class="result-count">
            Showing <?= count($users) ?> user(s)
        </p>

        <?php if (count($users) > 0): ?>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Date Registered</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $user): ?>

                    <tr>
                        <td>#<?= (int)$user["user_id"] ?></td>

                        <td>
                            <div class="user-name">
                                <?= e($user["full_name"]) ?>
                            </div>
                        </td>

                        <td><?= e($user["email"]) ?></td>

                        <td>
                            <span class="role <?= e($user["role"]) ?>">
                                <?= e($user["role"]) ?>
                            </span>
                        </td>

                        <td><?= e($user["created_at"]) ?></td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

        <?php else: ?>

            <div class="empty">
                <h3>No users found</h3>
                <p>Try another search term or role filter.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

<script>
const themeToggle = document.getElementById("themeToggle");

function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    localStorage.setItem("findit-theme", theme);

    const isDark = theme === "dark";

    themeToggle.textContent = isDark ? "☀️" : "🌙";
    themeToggle.setAttribute(
        "aria-label",
        isDark ? "Switch to light mode" : "Switch to dark mode"
    );
    themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
    themeToggle.setAttribute(
        "title",
        isDark ? "Switch to light mode" : "Switch to dark mode"
    );
}

applyTheme(localStorage.getItem("findit-theme") || "light");

themeToggle.addEventListener("click", function () {
    const currentTheme = document.documentElement.getAttribute("data-theme");
    applyTheme(currentTheme === "dark" ? "light" : "dark");
});
</script>

</body>
</html>
