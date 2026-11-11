
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $claimId = filter_input(INPUT_POST, "claim_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please try again.";
        $messageType = "error";

    } elseif (!$claimId || !in_array($action, ["approve", "reject"], true)) {
        $message = "Invalid claim or action.";
        $messageType = "error";

    } else {
        try {
            $pdo->beginTransaction();

            // Get and lock the claim
            $stmt = $pdo->prepare("
                SELECT
                    c.claim_id,
                    c.item_id,
                    c.lost_item_id,
                    c.user_id,
                    c.status AS claim_status,
                    i.status AS item_status,
                    i.item_type,
                    i.user_id AS item_owner
                FROM claims c
                INNER JOIN items i ON c.item_id = i.item_id
                WHERE c.claim_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$claimId]);
            $claim = $stmt->fetch();

            if (!$claim || $claim["claim_status"] !== "Pending") {
                throw new Exception(
                    "This claim is no longer pending or does not exist."
                );
            }

            if ($action === "approve") {

                if (
                    $claim["item_status"] !== "Approved" ||
                    $claim["item_type"] !== "Found"
                ) {
                    throw new Exception(
                        "This Found Item is no longer available for claiming."
                    );
                }

                // Validate connected Lost Report, if one exists
                if (!empty($claim["lost_item_id"])) {

                    $stmt = $pdo->prepare("
                        SELECT item_id
                        FROM items
                        WHERE item_id = ?
                        AND user_id = ?
                        AND item_type = 'Lost'
                        AND status = 'Approved'
                        FOR UPDATE
                    ");

                    $stmt->execute([
                        $claim["lost_item_id"],
                        $claim["user_id"]
                    ]);

                    if (!$stmt->fetch()) {
                        throw new Exception(
                            "The connected Lost Report is no longer available or valid. Please review the claim."
                        );
                    }
                }

                // Approve the selected claim
                $stmt = $pdo->prepare("
                    UPDATE claims
                    SET status = 'Approved'
                    WHERE claim_id = ?
                ");

                $stmt->execute([$claimId]);

                // Resolve the Found Report
                $stmt = $pdo->prepare("
                    UPDATE items
                    SET status = 'Resolved'
                    WHERE item_id = ?
                    AND status = 'Approved'
                ");

                $stmt->execute([$claim["item_id"]]);

                // Resolve the connected Lost Report
                if (!empty($claim["lost_item_id"])) {

                    $stmt = $pdo->prepare("
                        UPDATE items
                        SET status = 'Resolved'
                        WHERE item_id = ?
                        AND user_id = ?
                        AND item_type = 'Lost'
                        AND status = 'Approved'
                    ");

                    $stmt->execute([
                        $claim["lost_item_id"],
                        $claim["user_id"]
                    ]);
                }

                // Reject other pending claims for the same Found Item
                $stmt = $pdo->prepare("
                    UPDATE claims
                    SET status = 'Rejected'
                    WHERE item_id = ?
                    AND claim_id != ?
                    AND status = 'Pending'
                ");

                $stmt->execute([
                    $claim["item_id"],
                    $claimId
                ]);

                $message = !empty($claim["lost_item_id"])
                    ? "Claim approved! Both the Found Report and connected Lost Report are now Resolved."
                    : "Claim approved! The Found Report is now Resolved.";

            } else {

                // Reject the selected claim
                $stmt = $pdo->prepare("
                    UPDATE claims
                    SET status = 'Rejected'
                    WHERE claim_id = ?
                ");

                $stmt->execute([$claimId]);

                // Do not resolve either report when a claim is rejected
                $message = "Claim successfully rejected.";
            }

            $pdo->commit();
            $messageType = "success";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("FindIt Manage Claims Error: " . $e->getMessage());

            $message = $e instanceof PDOException
                ? "Something went wrong while processing the claim."
                : $e->getMessage();

            $messageType = "error";
        }
    }
}

// Filter claims
$filter = $_GET["status"] ?? "All";

$allowedFilters = ["All", "Pending", "Approved", "Rejected"];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = "All";
}

// Retrieve claims
$sql = "
    SELECT
        c.claim_id,
        c.item_id,
        c.lost_item_id,
        c.user_id,
        c.claim_message,
        c.status,
        c.created_at,

        i.item_name,
        i.item_type,
        i.location,
        i.status AS item_status,

        lost.item_name AS lost_item_name,
        lost.status AS lost_item_status,

        u.full_name,
        u.email

    FROM claims c

    INNER JOIN items i
        ON c.item_id = i.item_id

    LEFT JOIN items lost
        ON c.lost_item_id = lost.item_id

    INNER JOIN users u
        ON c.user_id = u.user_id
";

$params = [];

if ($filter !== "All") {
    $sql .= " WHERE c.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Statistics
$countStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Rejected') AS rejected
    FROM claims
");

$counts = $countStmt->fetch();

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Claims | FindIt Admin</title>

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

    --card-shadow: 0 3px 12px rgba(0,0,0,.04);
    --table-hover: #F4FAF6;
}

html[data-theme="dark"] {
    --celadon: #315B43;
    --light-celadon: #263D30;
    --emerald: #78D5A0;
    --deep-emerald: #A5E8BC;
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
    grid-template-columns: repeat(4, 1fr);
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
    vertical-align: top;
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

.claim-message {
    max-width: 250px;
    line-height: 1.5;
    color: var(--muted);
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

html[data-theme="dark"] .Pending {
    background: #493D25;
    color: #FFD98A;
}

html[data-theme="dark"] .Approved,
html[data-theme="dark"] .Resolved {
    background: #263D30;
    color: #A5E8BC;
}

html[data-theme="dark"] .Rejected {
    background: #482B2B;
    color: #FFB7B7;
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

html[data-theme="dark"] .reject {
    background: #482B2B;
    color: #FFB7B7;
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

.connected-report {
    margin-top: 7px;
    padding: 8px;
    background: var(--soft-green);
    border: 1px solid var(--border);
    border-radius: 7px;
    line-height: 1.6;
}

@media (max-width: 800px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
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
        <a href="manage-items.php">Manage Items</a>
        <a href="manage-claims.php" class="active">Manage Claims</a>
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
        <h1>Manage Claims</h1>
        <p>Review claim requests submitted by users for reported items.</p>
    </div>

    <section class="stats">

        <div class="stat-card">
            <p>Total Claims</p>
            <h2><?= (int) ($counts["total"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Pending Claims</p>
            <h2><?= (int) ($counts["pending"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Approved Claims</p>
            <h2><?= (int) ($counts["approved"] ?? 0) ?></h2>
        </div>

        <div class="stat-card">
            <p>Rejected Claims</p>
            <h2><?= (int) ($counts["rejected"] ?? 0) ?></h2>
        </div>

    </section>

    <section class="content-card">

        <?php if ($message): ?>
            <div class="alert <?= e($messageType) ?>" role="alert">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="toolbar">
            <h2>Claim Requests</h2>

            <div class="filters">
                <?php foreach ($allowedFilters as $status): ?>
                    <a
                        href="manage-claims.php?status=<?= urlencode($status) ?>"
                        class="<?= $filter === $status ? "active" : "" ?>"
                    >
                        <?= e($status) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($claims) > 0): ?>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Found Item</th>
                        <th>Connected Lost Report</th>
                        <th>Item Status</th>
                        <th>Claimant</th>
                        <th>Claim Message</th>
                        <th>Date Submitted</th>
                        <th>Claim Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($claims as $claim): ?>

                    <tr>
                        <td>#<?= (int) $claim["claim_id"] ?></td>

                        <td>
                            <strong><?= e($claim["item_name"]) ?></strong><br>
                            <small>
                                <?= e($claim["item_type"]) ?> ·
                                <?= e($claim["location"]) ?>
                            </small>
                        </td>

                        <td>
                            <?php if (!empty($claim["lost_item_id"])): ?>
                                <div class="connected-report">
                                    <strong><?= e($claim["lost_item_name"]) ?></strong><br>
                                    <small>
                                        Lost Report #<?= (int) $claim["lost_item_id"] ?>
                                    </small><br>
                                    <span class="status <?= e($claim["lost_item_status"] ?? "") ?>">
                                        <?= e($claim["lost_item_status"] ?? "Unavailable") ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="waiting">No connected report</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status <?= e($claim["item_status"]) ?>">
                                <?= e($claim["item_status"]) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($claim["full_name"]) ?></strong><br>
                            <small><?= e($claim["email"]) ?></small>
                        </td>

                        <td>
                            <div class="claim-message">
                                <?= nl2br(e($claim["claim_message"])) ?>
                            </div>
                        </td>

                        <td><?= e($claim["created_at"]) ?></td>

                        <td>
                            <span class="status <?= e($claim["status"]) ?>">
                                <?= e($claim["status"]) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (
                                $claim["status"] === "Pending" &&
                                $claim["item_status"] === "Approved"
                            ): ?>

                                <div class="actions">

                                    <form method="POST"
                                        onsubmit="return confirm('Approve this claim? The Found Report and its connected Lost Report will be marked as Resolved.');">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= e($_SESSION["csrf_token"]) ?>">

                                        <input type="hidden" name="claim_id"
                                            value="<?= (int) $claim["claim_id"] ?>">

                                        <input type="hidden" name="action"
                                            value="approve">

                                        <button type="submit" class="action-btn approve">
                                            Approve
                                        </button>
                                    </form>

                                    <form method="POST"
                                        onsubmit="return confirm('Reject this claim?');">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= e($_SESSION["csrf_token"]) ?>">

                                        <input type="hidden" name="claim_id"
                                            value="<?= (int) $claim["claim_id"] ?>">

                                        <input type="hidden" name="action"
                                            value="reject">

                                        <button type="submit" class="action-btn reject">
                                            Reject
                                        </button>
                                    </form>

                                </div>

                            <?php else: ?>

                                <span class="waiting">No action needed</span>

                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <?php else: ?>

            <div class="empty">
                <h3>No claims found</h3>
                <p>There are no claim requests under this status yet.</p>
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
