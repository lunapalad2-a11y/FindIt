
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$userId = (int) $_SESSION["user_id"];

$claims = [];
$pageError = "";

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

function claimStatusClass($status) {
    switch ($status) {
        case "Approved":
            return "approved";
        case "Rejected":
            return "rejected";
        default:
            return "pending";
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT
            c.claim_id,
            c.claim_message,
            c.status AS claim_status,
            c.created_at,
            c.updated_at,
            i.item_id,
            i.item_name,
            i.description,
            i.category,
            i.item_type,
            i.location,
            i.date_lost_found,
            i.photo_url,
            i.status AS item_status
        FROM claims c
        INNER JOIN items i ON c.item_id = i.item_id
        WHERE c.user_id = :user_id
        ORDER BY c.created_at DESC
    ");

    $stmt->execute([
        ":user_id" => $userId
    ]);

    $claims = $stmt->fetchAll();

} catch (PDOException $ex) {
    error_log("FindIt My Claims Error: " . $ex->getMessage());
    $pageError = "Unable to load your claims. Please try again later.";
}

$totalClaims = count($claims);

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($claims as $claim) {
    switch ($claim["claim_status"]) {
        case "Pending":
            $pendingCount++;
            break;
        case "Approved":
            $approvedCount++;
            break;
        case "Rejected":
            $rejectedCount++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Claims | FindIt</title>

    <script>
        (function () {
            const savedTheme = localStorage.getItem("findit-theme");

            if (savedTheme === "dark") {
                document.documentElement.setAttribute("data-theme", "dark");
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
            padding: 35px 5%;
            background: var(--page-bg, var(--soft-green, #F4FAF6));
            color: var(--text, #183B2A);
            font-family: Arial, sans-serif;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .page-container {
            max-width: 1400px;
            margin: auto;
        }

        /* HEADER */

        .top-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
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
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            border-color: var(--emerald, #046A38);
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            color: var(--heading-color, var(--deep-emerald, #024D2B));
            font-size: 32px;
        }

        .page-header p {
            color: var(--muted, #698273);
            margin: 0;
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 28px;
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
        }

        .search-box,
        .status-filter {
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
            min-width: 200px;
        }

        .status-filter {
            min-width: 170px;
        }

        .status-filter option {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
        }

        .search-box:focus,
        .status-filter:focus {
            border-color: var(--emerald, #046A38);
        }

        /* CLAIM GRID */

        .claims-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .claim-card {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px var(--shadow, rgba(0,0,0,0.04));
            transition: transform 0.25s, background 0.25s, border 0.25s;
        }

        .claim-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px var(--shadow, rgba(0,0,0,0.08));
        }

        .item-photo,
        .no-photo {
            width: 100%;
            height: 210px;
            display: block;
            background: var(--light-celadon, #E4F3E9);
        }

        .item-photo {
            object-fit: cover;
        }

        .no-photo {
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--muted, #698273);
        }

        .claim-content {
            padding: 20px;
        }

        .claim-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
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

        .pending {
            background: #FFF3CD;
            color: #856404;
        }

        .approved {
            background: #D4EDDA;
            color: #155724;
        }

        .rejected {
            background: #F8D7DA;
            color: #721C24;
        }

        .claim-content h2 {
            margin: 0 0 10px;
            color: var(--heading-color, var(--deep-emerald, #024D2B));
            font-size: 20px;
            overflow-wrap: anywhere;
        }

        .item-description {
            color: var(--muted, #698273);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
            overflow-wrap: anywhere;
        }

        .claim-info {
            border-top: 1px solid var(--border, #E5EEE8);
            padding-top: 12px;
            font-size: 13px;
        }

        .detail {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 10px 0;
        }

        .detail span:first-child {
            color: var(--muted, #698273);
        }

        .detail span:last-child {
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .claim-message {
            padding: 13px;
            margin-top: 16px;
            background: var(--section-bg, var(--soft-green, #F4FAF6));
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .claim-message strong {
            display: block;
            margin-bottom: 6px;
        }

        .claim-date {
            margin-top: 14px;
            color: var(--muted, #698273);
            font-size: 12px;
            line-height: 1.6;
        }

        /* STATUS EXPLANATION */

        .status-info {
            margin-top: 14px;
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

        /* EMPTY STATE */

        .empty-state {
            background: var(--card-bg, white);
            color: var(--text, #183B2A);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 14px;
            padding: 55px 20px;
            text-align: center;
        }

        .empty-state h2 {
            color: var(--heading-color, var(--deep-emerald, #024D2B));
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--muted, #698273);
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .browse-btn {
            display: inline-block;
            padding: 12px 22px;
            background: var(--emerald, #046A38);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .browse-btn:hover {
            background: var(--deep-emerald, #024D2B);
        }

        .no-results {
            display: none;
        }

        .page-error {
            padding: 15px;
            margin-bottom: 20px;
            background: #F8D7DA;
            color: #721C24;
            border-radius: 8px;
        }

        /* DARK MODE STATUS COLORS */

        html[data-theme="dark"] .pending {
            background: #4A3B1B;
            color: #F6D98A;
        }

        html[data-theme="dark"] .approved {
            background: #1E4930;
            color: #A9E6BA;
        }

        html[data-theme="dark"] .rejected {
            background: #512B32;
            color: #F2B5BE;
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

        html[data-theme="dark"] .page-error {
            background: #43262D;
            color: #F2B5BE;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {
            .claims-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            body {
                padding: 25px 18px;
            }

            .claims-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .filter-container {
                flex-direction: column;
            }

            .search-box,
            .status-filter {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<main class="page-container">

    <div class="top-actions">
        <a href="../dashboard.php" class="back-btn">
            ← Back to Dashboard
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

    <div class="page-header">
        <h1>My Claims</h1>
        <p>Track your submitted claims and monitor their review status.</p>
    </div>

    <?php if ($pageError !== ""): ?>

        <div class="page-error">
            <?= e($pageError) ?>
        </div>

    <?php else: ?>

        <!-- SUMMARY CARDS -->

        <section class="summary-grid">

            <div class="summary-card">
                <p>Total Claims</p>
                <h2><?= $totalClaims ?></h2>
            </div>

            <div class="summary-card">
                <p>Pending</p>
                <h2><?= $pendingCount ?></h2>
            </div>

            <div class="summary-card">
                <p>Approved</p>
                <h2><?= $approvedCount ?></h2>
            </div>

            <div class="summary-card">
                <p>Rejected</p>
                <h2><?= $rejectedCount ?></h2>
            </div>

        </section>

        <!-- SEARCH AND FILTER -->

        <div class="filter-container">

            <input
                type="search"
                id="claimSearch"
                class="search-box"
                placeholder="Search item name, category, or location..."
            >

            <select id="statusFilter" class="status-filter">
                <option value="All">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>

        </div>

        <?php if (count($claims) > 0): ?>

            <div class="claims-grid" id="claimsGrid">

                <?php foreach ($claims as $claim): ?>

                    <?php
                    $photo = $claim["photo_url"] ?? "";
                    $hasPhoto = validPhoto($photo);

                    $status = $claim["claim_status"];

                    $searchText = strtolower(
                        $claim["item_name"] . " " .
                        $claim["description"] . " " .
                        $claim["category"] . " " .
                        $claim["location"] . " " .
                        $claim["claim_message"]
                    );

                    switch ($status) {
                        case "Approved":
                            $statusMessage = "Your claim has been approved by the administrator.";
                            break;

                        case "Rejected":
                            $statusMessage = "Your claim was rejected. Please contact the administrator if you need clarification.";
                            break;

                        default:
                            $statusMessage = "Your claim is waiting for administrator review.";
                            break;
                    }
                    ?>

                    <article
                        class="claim-card"
                        data-search="<?= e($searchText) ?>"
                        data-status="<?= e($status) ?>"
                    >

                        <?php if ($hasPhoto): ?>

                            <img
                                src="../<?= e($photo) ?>"
                                alt="<?= e($claim["item_name"]) ?>"
                                class="item-photo"
                            >

                        <?php else: ?>

                            <div class="no-photo">
                                No Photo Available
                            </div>

                        <?php endif; ?>

                        <div class="claim-content">

                            <div class="claim-top">

                                <span class="item-type">
                                    <?= e($claim["item_type"]) ?> Item
                                </span>

                                <span class="status <?= claimStatusClass($status) ?>">
                                    <?= e($status) ?>
                                </span>

                            </div>

                            <h2><?= e($claim["item_name"]) ?></h2>

                            <p class="item-description">
                                <?= e($claim["description"]) ?>
                            </p>

                            <div class="claim-info">

                                <div class="detail">
                                    <span>Claim ID</span>
                                    <span>#<?= (int) $claim["claim_id"] ?></span>
                                </div>

                                <div class="detail">
                                    <span>Category</span>
                                    <span><?= e($claim["category"]) ?></span>
                                </div>

                                <div class="detail">
                                    <span>Location</span>
                                    <span><?= e($claim["location"]) ?></span>
                                </div>

                                <div class="detail">
                                    <span>Date Lost/Found</span>
                                    <span><?= e($claim["date_lost_found"]) ?></span>
                                </div>

                            </div>

                            <div class="claim-message">
                                <strong>Your Claim Message</strong>
                                <?= nl2br(e($claim["claim_message"])) ?>
                            </div>

                            <div class="status-info info-<?= strtolower($status) ?>">
                                <?= e($statusMessage) ?>
                            </div>

                            <div class="claim-date">
                                Submitted:
                                <?= e(date("M d, Y - h:i A", strtotime($claim["created_at"]))) ?>

                                <?php if ($claim["updated_at"] !== $claim["created_at"]): ?>
                                    <br>
                                    Last Updated:
                                    <?= e(date("M d, Y - h:i A", strtotime($claim["updated_at"]))) ?>
                                <?php endif; ?>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

            <div class="empty-state no-results" id="noResults">
                <h2>No Matching Claims</h2>
                <p>Try another keyword or select a different status.</p>
            </div>

        <?php else: ?>

            <div class="empty-state">

                <h2>No Claims Yet</h2>

                <p>
                    You haven't submitted any claims.
                    Once you claim an approved Found item, it will appear here.
                </p>

                <a href="../index.php#browse" class="browse-btn">
                    Browse Items
                </a>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

<script>
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

const savedTheme = localStorage.getItem("findit-theme") || "light";
applyTheme(savedTheme);

if (themeToggle) {
    themeToggle.addEventListener("click", function () {
        const currentTheme =
            document.documentElement.getAttribute("data-theme") || "light";

        const newTheme = currentTheme === "dark" ? "light" : "dark";

        localStorage.setItem("findit-theme", newTheme);
        applyTheme(newTheme);
    });
}

/* SEARCH AND STATUS FILTER */

const searchInput = document.getElementById("claimSearch");
const statusFilter = document.getElementById("statusFilter");

const claimCards = document.querySelectorAll(".claim-card");
const noResults = document.getElementById("noResults");

function filterClaims() {

    if (!searchInput || !statusFilter) {
        return;
    }

    const keyword = searchInput.value.toLowerCase().trim();
    const selectedStatus = statusFilter.value;

    let visibleCount = 0;

    claimCards.forEach(function (card) {

        const searchText = card.dataset.search || "";
        const status = card.dataset.status || "";

        const matchesSearch = searchText.includes(keyword);

        const matchesStatus =
            selectedStatus === "All" || status === selectedStatus;

        const shouldShow = matchesSearch && matchesStatus;

        card.style.display = shouldShow ? "" : "none";

        if (shouldShow) {
            visibleCount++;
        }

    });

    if (noResults) {
        noResults.style.display =
            visibleCount === 0 && claimCards.length > 0
                ? "block"
                : "none";
    }
}

if (searchInput) {
    searchInput.addEventListener("input", filterClaims);
}

if (statusFilter) {
    statusFilter.addEventListener("change", filterClaims);
}
</script>

</body>
</html>