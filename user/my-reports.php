
<?php
error_reporting(E_ALL);
ini_set("display_errors", "0");

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$userId = (int) $_SESSION["user_id"];

$reports = [];
$pageError = "";
$successMessage = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

function validPhoto($path) {
    if (empty($path)) {
        return false;
    }

    if (!preg_match('~^uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~i', $path)) {
        return false;
    }

    return is_file(__DIR__ . "/../" . $path);
}

function statusClass($status) {
    switch ($status) {
        case "Pending":
            return "status-pending";
        case "Approved":
            return "status-approved";
        case "Rejected":
            return "status-rejected";
        case "Resolved":
            return "status-resolved";
        default:
            return "status-default";
    }
}

/* DELETE REPORT */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete_item") {

    $postedToken = $_POST["csrf_token"] ?? "";
    $itemIdToDelete = filter_input(INPUT_POST, "item_id", FILTER_VALIDATE_INT);

    if (
        !$postedToken ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $pageError = "Invalid security token. Please refresh the page and try again.";

    } elseif (!$itemIdToDelete || $itemIdToDelete < 1) {
        $pageError = "Invalid report selected.";

    } else {
        $photoToDelete = null;

        try {
            $pdo->beginTransaction();

            // Confirm that the report belongs to the logged-in user.
            $stmt = $pdo->prepare("
                SELECT item_id, photo_url
                FROM items
                WHERE item_id = :item_id
                  AND user_id = :user_id
                FOR UPDATE
            ");

            $stmt->execute([
                ":item_id" => $itemIdToDelete,
                ":user_id" => $userId
            ]);

            $ownedItem = $stmt->fetch();

            if (!$ownedItem) {
                throw new Exception("Report not found or you are not authorized to delete it.");
            }

            $photoToDelete = $ownedItem["photo_url"];

            // Remove claims connected to this report.
            $deleteClaims = $pdo->prepare("
                DELETE FROM claims
                WHERE item_id = :item_id
                   OR lost_item_id = :lost_item_id
            ");

            $deleteClaims->execute([
                ":item_id" => $itemIdToDelete,
                ":lost_item_id" => $itemIdToDelete
            ]);

            // Delete only the logged-in user's report.
            $deleteItem = $pdo->prepare("
                DELETE FROM items
                WHERE item_id = :item_id
                  AND user_id = :user_id
            ");

            $deleteItem->execute([
                ":item_id" => $itemIdToDelete,
                ":user_id" => $userId
            ]);

            if ($deleteItem->rowCount() !== 1) {
                throw new Exception("The report could not be deleted.");
            }

            $pdo->commit();

            // Delete the uploaded photo only after the database deletion succeeds.
            if (validPhoto($photoToDelete)) {
                $photoFile = __DIR__ . "/../" . $photoToDelete;

                if (is_file($photoFile)) {
                    @unlink($photoFile);
                }
            }

            header("Location: my-reports.php?deleted=1");
            exit;

        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("FindIt Delete Report Error: " . $ex->getMessage());

            $pageError = $ex instanceof PDOException
                ? "Unable to delete the report. Please try again later."
                : $ex->getMessage();
        }
    }
}

if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $successMessage = "Your report has been successfully deleted.";
}

/* LOAD USER REPORTS */

try {
    $stmt = $pdo->prepare("
        SELECT
            item_id,
            item_name,
            description,
            category,
            item_type,
            location,
            date_lost_found,
            status,
            date_posted,
            photo_url
        FROM items
        WHERE user_id = :user_id
        ORDER BY date_posted DESC
    ");

    $stmt->execute([
        ":user_id" => $userId
    ]);

    $reports = $stmt->fetchAll();

} catch (PDOException $ex) {
    error_log("FindIt My Reports Error: " . $ex->getMessage());
    $pageError = "Unable to load your reports. Please try again later.";
}

$totalReports = count($reports);

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$resolvedCount = 0;

foreach ($reports as $report) {
    switch ($report["status"]) {
        case "Pending":
            $pendingCount++;
            break;
        case "Approved":
            $approvedCount++;
            break;
        case "Rejected":
            $rejectedCount++;
            break;
        case "Resolved":
            $resolvedCount++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Reports | FindIt</title>

    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem("findit-theme");

                if (savedTheme === "dark") {
                    document.documentElement.setAttribute("data-theme", "dark");
                }
            } catch (error) {
                // Continue with the default theme.
            }
        })();
    </script>

    <meta name="theme-color" content="#F4FAF6">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--page-bg, var(--soft-green, #F4FAF6));
            color: var(--text, #183B2A);
            font-family: Arial, sans-serif;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .reports-page {
            width: 92%;
            max-width: 1400px;
            margin: 45px auto;
        }

        /* HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--heading-color, var(--emerald, #046A38));
            margin: 0 0 8px;
            font-size: 32px;
        }

        .page-header p {
            color: var(--muted, #698273);
            margin: 0;
            line-height: 1.5;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            display: inline-block;
            padding: 12px 20px;
            background: var(--emerald, #046A38);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: 0.2s;
        }

        .back-btn:hover {
            background: var(--deep-emerald, #024D2B);
        }

        .theme-toggle {
            width: 44px;
            height: 44px;
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 50%;
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            font-size: 20px;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: 0.2s;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            border-color: var(--emerald, #046A38);
        }

        /* MESSAGES */

        .page-error,
        .page-success {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .page-error {
            background: #F8D7DA;
            color: #721C24;
        }

        .page-success {
            background: #D4EDDA;
            color: #155724;
        }

        html[data-theme="dark"] .page-error {
            background: #43262D;
            color: #F2B5BE;
        }

        html[data-theme="dark"] .page-success {
            background: #1D3D2B;
            color: #A9E6BA;
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px var(--shadow, rgba(0,0,0,0.04));
            transition: background 0.25s ease, border 0.25s ease;
        }

        .summary-card p {
            color: var(--muted, #698273);
            font-size: 13px;
            margin: 0 0 10px;
        }

        .summary-card h2 {
            color: var(--emerald, #046A38);
            font-size: 28px;
            margin: 0;
        }

        /* FILTERS */

        .filter-container {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box,
        .filter-select {
            padding: 14px 16px;
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 9px;
            background: var(--input-bg, white);
            color: var(--text, #183B2A);
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        .search-box::placeholder {
            color: var(--muted, #698273);
        }

        .search-box {
            flex: 1;
            min-width: 220px;
        }

        .filter-select {
            min-width: 160px;
        }

        .filter-select option {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
        }

        .search-box:focus,
        .filter-select:focus {
            border-color: var(--emerald, #046A38);
        }

        /* REPORT GRID */

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .report-card {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px var(--shadow, rgba(0,0,0,0.05));
            transition: transform 0.2s, box-shadow 0.2s, background 0.25s;
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px var(--shadow, rgba(0,0,0,0.08));
        }

        .report-image,
        .no-image {
            width: 100%;
            height: 220px;
            display: block;
            background: var(--light-celadon, #E4F3E9);
        }

        .report-image {
            object-fit: cover;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted, #698273);
            font-size: 14px;
        }

        .report-content {
            padding: 20px;
        }

        .report-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .item-type,
        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .item-type {
            background: var(--light-celadon, #E4F3E9);
            color: var(--deep-emerald, #024D2B);
        }

        .status-pending {
            background: #FFF3CD;
            color: #856404;
        }

        .status-approved {
            background: #D4EDDA;
            color: #155724;
        }

        .status-resolved {
            background: #D1ECF1;
            color: #0C5460;
        }

        .status-rejected {
            background: #F8D7DA;
            color: #721C24;
        }

        .status-default {
            background: #E9ECEF;
            color: #495057;
        }

        .report-card h2 {
            color: var(--heading-color, var(--emerald, #046A38));
            font-size: 20px;
            margin: 0 0 10px;
            overflow-wrap: anywhere;
        }

        .description {
            color: var(--muted, #607568);
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 18px;
            overflow-wrap: anywhere;
        }

        .report-details {
            border-top: 1px solid var(--border, #E5EEE8);
            padding-top: 12px;
        }

        .detail {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 11px 0;
            font-size: 13px;
        }

        .detail span:first-child {
            color: var(--muted, #698273);
        }

        .detail span:last-child {
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        /* STATUS INFORMATION */

        .status-info {
            margin-top: 17px;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.5;
        }

        .info-pending {
            background: #FFF8E1;
            color: #856404;
        }

        .info-approved {
            background: #E8F5E9;
            color: #155724;
        }

        .info-rejected {
            background: #FCEBEC;
            color: #721C24;
        }

        .info-resolved {
            background: #E5F5F7;
            color: #0C5460;
        }

        /* REPORT ACTION BUTTONS */

        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .edit-report-btn,
        .delete-report-btn {
            flex: 1;
            display: block;
            padding: 12px 10px;
            color: #FFFFFF;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .edit-report-btn {
            background: var(--emerald, #046A38);
        }

        .edit-report-btn:hover {
            background: var(--deep-emerald, #024D2B);
            transform: translateY(-1px);
        }

        .delete-report-btn {
            background: #C0392B;
        }

        .delete-report-btn:hover {
            background: #962D22;
            transform: translateY(-1px);
        }

        .delete-form {
            flex: 1;
            margin: 0;
        }

        .delete-form .delete-report-btn {
            width: 100%;
            font-family: Arial, sans-serif;
        }

        /* EMPTY STATE */

        .empty-state {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 14px;
            padding: 55px 20px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .empty-state h2 {
            color: var(--heading-color, var(--emerald, #046A38));
            margin-top: 0;
        }

        .empty-state p {
            color: var(--muted, #698273);
            line-height: 1.6;
        }

        .empty-state a {
            display: inline-block;
            margin-top: 12px;
            padding: 12px 20px;
            background: var(--emerald, #046A38);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .empty-state a:hover {
            background: var(--deep-emerald, #024D2B);
        }

        .no-results {
            display: none;
        }

        /* DARK MODE STATUS COLORS */

        html[data-theme="dark"] .status-pending {
            background: #4A3B1B;
            color: #F6D98A;
        }

        html[data-theme="dark"] .status-approved {
            background: #1E4930;
            color: #A9E6BA;
        }

        html[data-theme="dark"] .status-rejected {
            background: #512B32;
            color: #F2B5BE;
        }

        html[data-theme="dark"] .status-resolved {
            background: #193E45;
            color: #A8E4EC;
        }

        html[data-theme="dark"] .status-default {
            background: #34413A;
            color: #D3DED7;
        }

        html[data-theme="dark"] .info-pending {
            background: #3B321D;
            color: #F6D98A;
        }

        html[data-theme="dark"] .info-approved {
            background: #1D3D2B;
            color: #A9E6BA;
        }

        html[data-theme="dark"] .info-rejected {
            background: #43262D;
            color: #F2B5BE;
        }

        html[data-theme="dark"] .info-resolved {
            background: #193940;
            color: #A8E4EC;
        }

        /* RESPONSIVE */

        @media (max-width: 1050px) {
            .summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .reports-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .reports-page {
                width: 90%;
                margin: 30px auto;
            }

            .page-header {
                align-items: flex-start;
            }

            .page-header h1 {
                font-size: 27px;
            }

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .filter-container {
                flex-direction: column;
            }

            .search-box,
            .filter-select {
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 380px) {
            .report-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<main class="reports-page">

    <div class="page-header">

        <div>
            <h1>My Reports</h1>
            <p>Track the items you have reported and monitor their status.</p>
        </div>

        <div class="header-actions">

            <a href="../dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

            <button
                type="button"
                class="theme-toggle"
                id="themeToggle"
                aria-label="Switch to dark mode"
                title="Toggle Dark Mode"
            >
                <span id="themeIcon">🌙</span>
            </button>

        </div>

    </div>

    <?php if ($successMessage !== ""): ?>
        <div class="page-success">
            <?php echo e($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ""): ?>
        <div class="page-error">
            <?php echo e($pageError); ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError === ""): ?>

        <!-- SUMMARY CARDS -->

        <section class="summary-grid">

            <div class="summary-card">
                <p>Total Reports</p>
                <h2><?php echo $totalReports; ?></h2>
            </div>

            <div class="summary-card">
                <p>Pending</p>
                <h2><?php echo $pendingCount; ?></h2>
            </div>

            <div class="summary-card">
                <p>Approved</p>
                <h2><?php echo $approvedCount; ?></h2>
            </div>

            <div class="summary-card">
                <p>Rejected</p>
                <h2><?php echo $rejectedCount; ?></h2>
            </div>

            <div class="summary-card">
                <p>Resolved</p>
                <h2><?php echo $resolvedCount; ?></h2>
            </div>

        </section>

        <!-- SEARCH AND FILTERS -->

        <div class="filter-container">

            <input
                type="search"
                id="reportSearch"
                class="search-box"
                placeholder="Search your reports..."
            >

            <select id="typeFilter" class="filter-select">
                <option value="All">All Types</option>
                <option value="Lost">Lost Items</option>
                <option value="Found">Found Items</option>
            </select>

            <select id="statusFilter" class="filter-select">
                <option value="All">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
                <option value="Resolved">Resolved</option>
            </select>

        </div>

        <!-- REPORTS -->

        <div class="reports-grid" id="reportsGrid">

            <?php if (count($reports) > 0): ?>

                <?php foreach ($reports as $report): ?>

                    <?php
                    $searchText = strtolower(
                        $report["item_name"] . " " .
                        $report["description"] . " " .
                        $report["category"] . " " .
                        $report["location"] . " " .
                        $report["item_type"] . " " .
                        $report["status"]
                    );

                    $currentStatus = $report["status"];
                    $statusMessage = "";

                    switch ($currentStatus) {
                        case "Pending":
                            $statusMessage = "Your report is waiting for admin review.";
                            break;

                        case "Approved":
                            $statusMessage = "Your report has been approved and is visible in Browse.";
                            break;

                        case "Rejected":
                            $statusMessage = "Your report was rejected. Please contact the administrator if you need clarification.";
                            break;

                        case "Resolved":
                            $statusMessage = "This report has been marked as resolved.";
                            break;
                    }

                    $infoClass = "info-" . strtolower($currentStatus);
                    $itemId = (int) $report["item_id"];
                    ?>

                    <article
                        class="report-card"
                        data-search="<?php echo e($searchText); ?>"
                        data-type="<?php echo e($report["item_type"]); ?>"
                        data-status="<?php echo e($currentStatus); ?>"
                    >

                        <?php if (validPhoto($report["photo_url"])): ?>

                            <img
                                src="../<?php echo e($report["photo_url"]); ?>"
                                alt="<?php echo e($report["item_name"]); ?>"
                                class="report-image"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                No Photo Available
                            </div>

                        <?php endif; ?>

                        <div class="report-content">

                            <div class="report-top">

                                <span class="item-type">
                                    <?php echo e($report["item_type"]); ?> Item
                                </span>

                                <span class="status <?php echo e(statusClass($currentStatus)); ?>">
                                    <?php echo e($currentStatus); ?>
                                </span>

                            </div>

                            <h2><?php echo e($report["item_name"]); ?></h2>

                            <p class="description">
                                <?php echo e($report["description"]); ?>
                            </p>

                            <div class="report-details">

                                <div class="detail">
                                    <span>Category</span>
                                    <span><?php echo e($report["category"]); ?></span>
                                </div>

                                <div class="detail">
                                    <span>Location</span>
                                    <span><?php echo e($report["location"]); ?></span>
                                </div>

                                <div class="detail">
                                    <span>Date Lost/Found</span>
                                    <span><?php echo e($report["date_lost_found"]); ?></span>
                                </div>

                                <div class="detail">
                                    <span>Report ID</span>
                                    <span>#<?php echo $itemId; ?></span>
                                </div>

                            </div>

                            <div class="status-info <?php echo e($infoClass); ?>">
                                <?php echo e($statusMessage); ?>
                            </div>

                            <!-- EDIT AND DELETE BUTTONS -->

                            <div class="report-actions">

                                <a
                                    href="edit-item.php?item_id=<?php echo $itemId; ?>"
                                    class="edit-report-btn"
                                    aria-label="Edit report: <?php echo e($report["item_name"]); ?>"
                                >
                                    Edit Item
                                </a>

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="return confirmDelete('<?php echo e($report["item_name"]); ?>');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($csrfToken); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?php echo $itemId; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_item"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-report-btn"
                                        aria-label="Delete report: <?php echo e($report["item_name"]); ?>"
                                    >
                                        Delete Item
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state" id="emptyReports">

                    <h2>No Reports Yet</h2>

                    <p>
                        You haven't reported any lost or found items yet.
                        Your submitted reports will appear here.
                    </p>

                    <a href="report-item.php">
                        Report an Item
                    </a>

                </div>

            <?php endif; ?>

            <div class="empty-state no-results" id="noResults">

                <h2>No Matching Reports</h2>

                <p>
                    No reports match your search or selected filters.
                    Try changing your search keywords or filters.
                </p>

            </div>

        </div>

    <?php endif; ?>

</main>

<script>
/* DELETE CONFIRMATION */

function confirmDelete(itemName) {
    return confirm(
        'Are you sure you want to delete "' +
        itemName +
        '"?\n\nThis action cannot be undone.'
    );
}

/* DARK MODE */

const themeToggle = document.getElementById("themeToggle");
const themeIcon = document.getElementById("themeIcon");
const themeMeta = document.querySelector('meta[name="theme-color"]');

function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);

    const isDark = theme === "dark";

    if (themeIcon) {
        themeIcon.textContent = isDark ? "☀️" : "🌙";
    }

    if (themeToggle) {
        themeToggle.setAttribute(
            "aria-label",
            isDark ? "Switch to light mode" : "Switch to dark mode"
        );
    }

    if (themeMeta) {
        themeMeta.setAttribute(
            "content",
            isDark ? "#111D17" : "#F4FAF6"
        );
    }
}

let savedTheme = "light";

try {
    savedTheme = localStorage.getItem("findit-theme") || "light";
} catch (error) {
    savedTheme = "light";
}

applyTheme(savedTheme);

if (themeToggle) {
    themeToggle.addEventListener("click", function () {

        const currentTheme =
            document.documentElement.getAttribute("data-theme") || "light";

        const newTheme = currentTheme === "dark" ? "light" : "dark";

        try {
            localStorage.setItem("findit-theme", newTheme);
        } catch (error) {
            // Theme still changes for the current page.
        }

        applyTheme(newTheme);
    });
}

/* SEARCH AND FILTERS */

const searchInput = document.getElementById("reportSearch");
const typeFilter = document.getElementById("typeFilter");
const statusFilter = document.getElementById("statusFilter");

const reportCards = document.querySelectorAll(".report-card");
const noResults = document.getElementById("noResults");

function filterReports() {

    if (!searchInput || !typeFilter || !statusFilter) {
        return;
    }

    const keyword = searchInput.value.toLowerCase().trim();
    const selectedType = typeFilter.value;
    const selectedStatus = statusFilter.value;

    let visibleCount = 0;

    reportCards.forEach(function(card) {

        const searchText = card.dataset.search || "";
        const itemType = card.dataset.type || "";
        const itemStatus = card.dataset.status || "";

        const matchesSearch = searchText.includes(keyword);

        const matchesType =
            selectedType === "All" || itemType === selectedType;

        const matchesStatus =
            selectedStatus === "All" || itemStatus === selectedStatus;

        const shouldShow =
            matchesSearch && matchesType && matchesStatus;

        card.style.display = shouldShow ? "" : "none";

        if (shouldShow) {
            visibleCount++;
        }

    });

    if (noResults) {
        noResults.style.display =
            visibleCount === 0 && reportCards.length > 0
                ? "block"
                : "none";
    }
}

if (searchInput) {
    searchInput.addEventListener("input", filterReports);
}

if (typeFilter) {
    typeFilter.addEventListener("change", filterReports);
}

if (statusFilter) {
    statusFilter.addEventListener("change", filterReports);
}
</script>

</body>
</html>
