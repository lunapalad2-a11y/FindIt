
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Handle admin actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $itemId = filter_input(INPUT_POST, "item_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please try again.";
        $messageType = "error";

    } elseif (!$itemId || !in_array($action, ["approve", "reject", "delete"], true)) {
        $message = "Invalid item or action.";
        $messageType = "error";

    } else {

        try {

            if ($action === "delete") {

                $stmt = $pdo->prepare("
                    SELECT photo_url
                    FROM items
                    WHERE item_id = ?
                ");
                $stmt->execute([$itemId]);

                $item = $stmt->fetch();

                if (!$item) {
                    $message = "Report does not exist.";
                    $messageType = "error";

                } else {

                    $pdo->beginTransaction();

                    $deleteStmt = $pdo->prepare("
                        DELETE FROM items
                        WHERE item_id = ?
                    ");

                    $deleteStmt->execute([$itemId]);

                    if ($deleteStmt->rowCount() > 0) {
                        $pdo->commit();

                        // Safely remove uploaded photo
                        $photo = $item["photo_url"] ?? "";

                        if (
                            preg_match(
                                '/^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i',
                                $photo
                            )
                        ) {
                            $uploadsDirectory = realpath(__DIR__ . "/../uploads");
                            $photoPath = realpath(__DIR__ . "/../" . $photo);

                            if (
                                $uploadsDirectory !== false &&
                                $photoPath !== false &&
                                strpos(
                                    $photoPath,
                                    $uploadsDirectory . DIRECTORY_SEPARATOR
                                ) === 0 &&
                                is_file($photoPath)
                            ) {
                                unlink($photoPath);
                            }
                        }

                        $message = "Report successfully deleted.";
                        $messageType = "success";

                    } else {
                        $pdo->rollBack();
                        $message = "Unable to delete this report.";
                        $messageType = "error";
                    }
                }

            } else {

                // Approve or reject report
                $newStatus = $action === "approve" ? "Approved" : "Rejected";

                $stmt = $pdo->prepare("
                    UPDATE items
                    SET status = ?
                    WHERE item_id = ?
                    AND status = 'Pending'
                ");

                $stmt->execute([$newStatus, $itemId]);

                if ($stmt->rowCount() > 0) {
                    $message = "Report successfully " . strtolower($newStatus) . ".";
                    $messageType = "success";
                } else {
                    $message = "This report is no longer pending or does not exist.";
                    $messageType = "error";
                }
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Manage Items Error: " . $e->getMessage());

            $message = $action === "delete"
                ? "Something went wrong while deleting the report."
                : "Something went wrong while updating the report.";

            $messageType = "error";
        }
    }
}

// Filter
$filter = $_GET["status"] ?? "All";

$allowedFilters = [
    "All",
    "Pending",
    "Approved",
    "Rejected",
    "Resolved"
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = "All";
}

// Get reports
$sql = "
    SELECT
        items.*,
        users.full_name,
        users.email
    FROM items
    INNER JOIN users
        ON items.user_id = users.user_id
";

$params = [];

if ($filter !== "All") {
    $sql .= " WHERE items.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY items.date_posted DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Counts
$countStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Rejected') AS rejected,
        SUM(status = 'Resolved') AS resolved
    FROM items
");

$counts = $countStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Items | FindIt Admin</title>

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
            --dark-emerald: #013B22;
            --soft-green: #F4FAF6;
            --white: #FFFFFF;
            --text: #183B2A;
            --muted: #698273;
            --border: #D3E8DA;

            --card-shadow: 0 3px 12px rgba(0,0,0,.04);
            --table-hover: #F4FAF6;
        }

        html[data-theme="dark"] {
            --celadon: #315B43;
            --light-celadon: #263D30;
            --emerald: #78D5A0;
            --deep-emerald: #A5E8BC;
            --dark-emerald: #B9EFC9;
            --soft-green: #101A15;
            --white: #18251D;
            --text: #E5F1E8;
            --muted: #A7B8AC;
            --border: #30463A;

            --card-shadow: 0 3px 12px rgba(0,0,0,.18);
            --table-hover: #202F26;
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
            transition: background .2s ease, color .2s ease;
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
            background: var(--soft-green);
            color: var(--text);
            font-size: 19px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s ease;
        }

        .theme-toggle:hover {
            background: var(--light-celadon);
            transform: scale(1.05);
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
            box-shadow: var(--card-shadow);
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
            box-shadow: var(--card-shadow);
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

        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filters a {
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .filters a.active,
        .filters a:hover {
            background: var(--emerald);
            color: white;
            border-color: var(--emerald);
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .alert.success {
            background: #E4F3E9;
            color: #046A38;
        }

        .alert.error {
            background: #FCE8E8;
            color: #A12626;
        }

        html[data-theme="dark"] .alert.success {
            background: #263D30;
            color: #A5E8BC;
        }

        html[data-theme="dark"] .alert.error {
            background: #482B2B;
            color: #FFB7B7;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 13px;
        }

        tbody tr:hover {
            background: var(--table-hover);
        }

        th {
            color: var(--muted);
            background: var(--soft-green);
            font-size: 12px;
            text-transform: uppercase;
        }

        .item-photo {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .no-photo {
            width: 65px;
            height: 65px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 8px;
            background: var(--light-celadon);
            color: var(--muted);
            font-size: 11px;
        }

        .item-name {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .item-description {
            color: var(--muted);
            max-width: 220px;
            line-height: 1.5;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .Pending {
            background: #FFF3D6;
            color: #946200;
        }

        .Approved {
            background: #E4F3E9;
            color: #046A38;
        }

        .Rejected {
            background: #FCE8E8;
            color: #A12626;
        }

        .Resolved {
            background: #E8EAF6;
            color: #3949AB;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-btn {
            border: none;
            padding: 8px 11px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
        }

        .approve {
            background: var(--emerald);
            color: white;
        }

        .reject {
            background: #FCE8E8;
            color: #A12626;
        }

        .delete {
            background: #A12626;
            color: white;
        }

        .action-btn:hover {
            opacity: .82;
        }

        .waiting {
            color: var(--muted);
            font-size: 12px;
        }

        .empty {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
        }

        @media (max-width: 900px) {
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

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .content-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <a href="dashboard.php" class="logo">FindIt Admin</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage-items.php" class="active">Manage Items</a>
        <a href="manage-claims.php">Manage Claims</a>
        <a href="manage-users.php">Manage Users</a>

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Switch to Dark Mode"
        >🌙</button>

        <a href="../logout.php" class="logout">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="page-heading">
        <h1>Manage Items</h1>
        <p>Review and manage Lost and Found reports submitted by campus users.</p>
    </div>

    <section class="stats">

        <div class="stat-card">
            <p>Total Reports</p>
            <h2><?= (int)($counts["total"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Pending</p>
            <h2><?= (int)($counts["pending"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Approved</p>
            <h2><?= (int)($counts["approved"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Rejected</p>
            <h2><?= (int)($counts["rejected"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Resolved</p>
            <h2><?= (int)($counts["resolved"] ?? 0) ?></h2>
        </div>

    </section>

    <section class="content-card">

        <?php if ($message): ?>
            <div class="alert <?= e($messageType) ?>" role="alert">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="toolbar">
            <h2>Submitted Reports</h2>

            <div class="filters">
                <?php foreach ($allowedFilters as $status): ?>
                    <a
                        href="manage-items.php?status=<?= urlencode($status) ?>"
                        class="<?= $filter === $status ? "active" : "" ?>"
                    >
                        <?= e($status) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($items) > 0): ?>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Item Details</th>
                            <th>Type</th>
                            <th>Reporter</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr>

                            <td>
                                <?php
                                $photo = $item["photo_url"] ?? "";

                                $validPhoto = preg_match(
                                    '/^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i',
                                    $photo
                                );
                                ?>

                                <?php if ($photo && $validPhoto): ?>
                                    <img
                                        src="../<?= e($photo) ?>"
                                        alt="Photo of <?= e($item["item_name"]) ?>"
                                        class="item-photo"
                                    >
                                <?php else: ?>
                                    <div class="no-photo">No Photo</div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="item-name">
                                    <?= e($item["item_name"]) ?>
                                </div>

                                <div class="item-description">
                                    <?= e($item["description"]) ?>
                                </div>

                                <small><?= e($item["category"]) ?></small>
                            </td>

                            <td><?= e($item["item_type"]) ?></td>

                            <td>
                                <strong><?= e($item["full_name"]) ?></strong><br>
                                <small><?= e($item["email"]) ?></small>
                            </td>

                            <td><?= e($item["location"]) ?></td>

                            <td><?= e($item["date_lost_found"]) ?></td>

                            <td>
                                <span class="status <?= e($item["status"]) ?>">
                                    <?= e($item["status"]) ?>
                                </span>
                            </td>

                            <td>

                                <div class="actions">

                                    <?php if ($item["status"] === "Pending"): ?>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_token"]) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int)$item["item_id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve"
                                            >

                                            <button type="submit" class="action-btn approve">
                                                Approve
                                            </button>

                                        </form>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to reject this report?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_token"]) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int)$item["item_id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <button type="submit" class="action-btn reject">
                                                Reject
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this report? Associated claims may also be deleted. This action cannot be undone.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION["csrf_token"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="item_id"
                                            value="<?= (int)$item["item_id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <button type="submit" class="action-btn delete">
                                            Delete
                                        </button>

                                    </form>

                                    <?php if ($item["status"] !== "Pending"): ?>
                                        <span class="waiting"> </span>
                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <h3>No reports found</h3>
                <p>There are no reports under this status yet.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const themeToggle = document.getElementById("themeToggle");

    function applyTheme(theme) {

        document.documentElement.setAttribute("data-theme", theme);
        localStorage.setItem("findit-theme", theme);

        if (theme === "dark") {
            themeToggle.textContent = "☀️";
            themeToggle.setAttribute("aria-label", "Switch to light mode");
            themeToggle.setAttribute("aria-pressed", "true");
            themeToggle.title = "Switch to Light Mode";
        } else {
            themeToggle.textContent = "🌙";
            themeToggle.setAttribute("aria-label", "Switch to dark mode");
            themeToggle.setAttribute("aria-pressed", "false");
            themeToggle.title = "Switch to Dark Mode";
        }
    }

    const savedTheme = localStorage.getItem("findit-theme") || "light";
    applyTheme(savedTheme);

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
