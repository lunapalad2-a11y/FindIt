
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$userId = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$itemId = filter_input(INPUT_GET, "item_id", FILTER_VALIDATE_INT);

if (!$itemId || $itemId < 1) {
    header("Location: ../index.php#browse-items");
    exit;
}

// Get the Found Item
$stmt = $pdo->prepare("
    SELECT
        items.*,
        users.full_name AS reporter_name
    FROM items
    INNER JOIN users ON items.user_id = users.user_id
    WHERE items.item_id = ?
    AND items.status = 'Approved'
    AND items.item_type = 'Found'
");

$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item || (int) $item["user_id"] === $userId) {
    header("Location: ../index.php#browse-items");
    exit;
}

// Check if user already submitted a claim
$stmt = $pdo->prepare("
    SELECT claim_id
    FROM claims
    WHERE item_id = ?
    AND user_id = ?
    AND status IN ('Pending', 'Approved')
    LIMIT 1
");

$stmt->execute([$itemId, $userId]);

if ($stmt->fetch()) {
    header("Location: my-claims.php?already_claimed=1");
    exit;
}

// Get the user's own Approved Lost Reports
$stmt = $pdo->prepare("
    SELECT
        item_id,
        item_name,
        category,
        location,
        date_lost_found
    FROM items
    WHERE user_id = ?
    AND item_type = 'Lost'
    AND status = 'Approved'
    ORDER BY date_posted DESC
");

$stmt->execute([$userId]);
$lostReports = $stmt->fetchAll();

$error = "";
$selectedLostItem = "";
$messageValue = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $messageValue = trim($_POST["claim_message"] ?? "");
    $selectedLostItem = trim($_POST["lost_item_id"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Invalid request. Please refresh the page.";

    } elseif ($messageValue === "") {
        $error = "Please explain why you believe this item belongs to you.";

    } elseif (strlen($messageValue) > 2000) {
        $error = "Your message must not exceed 2000 characters.";

    } elseif (
        $selectedLostItem !== "" &&
        (!ctype_digit($selectedLostItem) || (int) $selectedLostItem < 1)
    ) {
        $error = "Invalid Lost Report selection.";

    } else {

        try {

            $pdo->beginTransaction();

            // Lock the Found Item
            $stmt = $pdo->prepare("
                SELECT item_id, user_id, status, item_type
                FROM items
                WHERE item_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$itemId]);
            $currentItem = $stmt->fetch();

            if (
                !$currentItem ||
                $currentItem["status"] !== "Approved" ||
                $currentItem["item_type"] !== "Found" ||
                (int) $currentItem["user_id"] === $userId
            ) {
                throw new Exception(
                    "This item is no longer available for claiming."
                );
            }

            // Check for an existing claim again
            $stmt = $pdo->prepare("
                SELECT claim_id
                FROM claims
                WHERE item_id = ?
                AND user_id = ?
                AND status IN ('Pending', 'Approved')
                LIMIT 1
            ");

            $stmt->execute([$itemId, $userId]);

            if ($stmt->fetch()) {
                throw new Exception(
                    "You already submitted a claim for this item."
                );
            }

            // Validate selected Lost Report
            $lostItemId = null;

            if ($selectedLostItem !== "") {

                $lostItemId = (int) $selectedLostItem;

                $stmt = $pdo->prepare("
                    SELECT item_id
                    FROM items
                    WHERE item_id = ?
                    AND user_id = ?
                    AND item_type = 'Lost'
                    AND status = 'Approved'
                    FOR UPDATE
                ");

                $stmt->execute([$lostItemId, $userId]);

                if (!$stmt->fetch()) {
                    throw new Exception(
                        "The selected Lost Report is invalid or no longer available."
                    );
                }
            }

            // Insert the claim with its connected Lost Report
            $stmt = $pdo->prepare("
                INSERT INTO claims (
                    item_id,
                    lost_item_id,
                    user_id,
                    claim_message,
                    status
                )
                VALUES (?, ?, ?, ?, 'Pending')
            ");

            $stmt->execute([
                $itemId,
                $lostItemId,
                $userId,
                $messageValue
            ]);

            $pdo->commit();

            header("Location: my-claims.php?success=1");
            exit;

        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("FindIt Claim Error: " . $ex->getMessage());

            $error = $ex instanceof PDOException
                ? "Unable to submit your claim. Please try again."
                : $ex->getMessage();
        }
    }
}

// Validate item photo path before displaying it
$photo = $item["photo_url"] ?? "";

$validPhoto = preg_match(
    '~^uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~i',
    $photo
);

$hasPhoto = $validPhoto &&
    is_file(__DIR__ . "/../" . $photo);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#046A38">

    <title>Claim Item | FindIt</title>

    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem("findit-theme");

                if (savedTheme === "dark") {
                    document.documentElement.setAttribute("data-theme", "dark");
                }
            } catch (error) {
                console.warn("Unable to load saved theme:", error);
            }
        })();
    </script>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 35px 18px;
            background: var(--page-bg, var(--soft-green));
            color: var(--text);
            font-family: Arial, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            max-width: 750px;
            margin: auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px;
        }

        .back-btn {
            display: inline-block;
            padding: 12px 20px;
            background: var(--emerald);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: 0.25s ease;
        }

        .back-btn:hover {
            background: var(--deep-emerald);
            transform: translateY(-2px);
        }

        .claim-theme-toggle {
            width: 43px;
            height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text);
            font-size: 19px;
            cursor: pointer;
            flex-shrink: 0;
            transition: 0.25s ease;
        }

        .claim-theme-toggle:hover {
            border-color: var(--emerald);
            background: var(--section-bg);
            transform: rotate(12deg);
        }

        .claim-theme-toggle:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .claim-panel {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px var(--shadow);
            transition: background-color 0.3s, color 0.3s;
        }

        .claim-header {
            padding: 25px;
            background: var(--light-celadon);
        }

        .claim-header h1 {
            margin: 0 0 8px;
            color: var(--heading-color);
        }

        .claim-header p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .item-details {
            padding: 25px;
        }

        .item-photo {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            background: var(--section-bg);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .item-details h2 {
            color: var(--heading-color);
            margin-top: 0;
        }

        .details {
            line-height: 1.9;
            font-size: 14px;
            color: var(--text);
            overflow-wrap: anywhere;
        }

        .claim-form {
            padding: 25px;
            border-top: 1px solid var(--border);
        }

        .claim-form h2 {
            color: var(--heading-color);
            margin-top: 0;
        }

        .claim-form label {
            display: block;
            margin: 18px 0 9px;
            font-weight: bold;
            color: var(--text);
        }

        .claim-form textarea,
        .claim-form select {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            font: inherit;
            background: var(--input-bg);
            color: var(--text);
            transition: border-color 0.2s, background-color 0.3s;
        }

        .claim-form select option {
            background: var(--card-bg);
            color: var(--text);
        }

        .claim-form textarea {
            min-height: 150px;
            resize: vertical;
        }

        .claim-form textarea::placeholder {
            color: var(--muted);
        }

        .claim-form textarea:focus,
        .claim-form select:focus {
            outline: 2px solid var(--celadon);
            border-color: var(--emerald);
        }

        .hint {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
            margin: 10px 0 18px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--emerald);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .submit-btn:hover {
            background: var(--deep-emerald);
        }

        .error {
            margin: 20px 25px 0;
            padding: 12px;
            background: #F8D7DA;
            color: #721C24;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .lost-report-section {
            padding: 16px;
            background: var(--section-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .lost-report-section p {
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
            margin: 8px 0 0;
        }

        @media (max-width: 600px) {
            body {
                padding: 22px 14px;
            }

            .claim-header,
            .item-details,
            .claim-form {
                padding: 20px;
            }

            .error {
                margin-left: 20px;
                margin-right: 20px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="topbar">

        <a href="../index.php#browse-items" class="back-btn">
            ← Back to Browse
        </a>

        <button
            type="button"
            class="claim-theme-toggle"
            id="claimThemeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Toggle dark mode"
        >
            <span id="claimThemeIcon" aria-hidden="true">🌙</span>
        </button>

    </div>

    <div class="claim-panel">

        <div class="claim-header">
            <h1>Claim This Item</h1>
            <p>Provide details to help verify that this item belongs to you.</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="item-details">

            <?php if ($hasPhoto): ?>
                <img
                    src="<?= e("../" . $photo) ?>"
                    alt="<?= e($item["item_name"]) ?>"
                    class="item-photo"
                >
            <?php endif; ?>

            <h2><?= e($item["item_name"]) ?></h2>

            <div class="details">
                <div>
                    <strong>Category:</strong>
                    <?= e($item["category"]) ?>
                </div>

                <div>
                    <strong>Description:</strong>
                    <?= e($item["description"]) ?>
                </div>

                <div>
                    <strong>Location:</strong>
                    <?= e($item["location"]) ?>
                </div>

                <div>
                    <strong>Reported By:</strong>
                    <?= e($item["reporter_name"]) ?>
                </div>
            </div>

        </div>

        <form method="POST" class="claim-form">

            <h2>Your Claim Details</h2>

            <div class="lost-report-section">

                <label for="lost_item_id">
                    Connect Your Lost Item Report
                </label>

                <select name="lost_item_id" id="lost_item_id">

                    <option value="">
                        None - Do not connect a Lost Report
                    </option>

                    <?php foreach ($lostReports as $lost): ?>

                        <option
                            value="<?= (int) $lost["item_id"] ?>"
                            <?= $selectedLostItem == $lost["item_id"] ? "selected" : "" ?>
                        >
                            <?= e($lost["item_name"]) ?>
                            - <?= e($lost["category"]) ?>
                            (<?= e($lost["location"]) ?>)
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (empty($lostReports)): ?>

                    <p>
                        You currently have no approved Lost Reports to connect.
                        You can still submit your claim without connecting one.
                    </p>

                <?php else: ?>

                    <p>
                        Select the Lost Report that matches this Found Item.
                        When your claim is approved by the administrator,
                        the connected Lost Report will also be marked as Resolved.
                    </p>

                <?php endif; ?>

            </div>

            <label for="claim_message">
                Why does this item belong to you?
            </label>

            <textarea
                name="claim_message"
                id="claim_message"
                maxlength="2000"
                required
                placeholder="Describe identifying details, when you lost the item, or other information that can help verify ownership."
            ><?= e($messageValue) ?></textarea>

            <p class="hint">
                Provide accurate details that can help the administrator
                verify your claim. Do not include passwords or other sensitive information.
            </p>

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <button type="submit" class="submit-btn">
                Submit Claim
            </button>

        </form>

    </div>

</div>

<script>
    // DARK MODE
    const claimThemeToggle =
        document.getElementById("claimThemeToggle");

    const claimThemeIcon =
        document.getElementById("claimThemeIcon");

    function applyClaimTheme(theme) {

        const isDark = theme === "dark";

        document.documentElement.setAttribute(
            "data-theme",
            isDark ? "dark" : "light"
        );

        claimThemeIcon.textContent = isDark ? "☀️" : "🌙";

        claimThemeToggle.setAttribute(
            "aria-label",
            isDark ? "Switch to light mode" : "Switch to dark mode"
        );

        claimThemeToggle.setAttribute(
            "aria-pressed",
            String(isDark)
        );

        const themeColor =
            document.querySelector('meta[name="theme-color"]');

        if (themeColor) {
            themeColor.setAttribute(
                "content",
                isDark ? "#111D17" : "#046A38"
            );
        }
    }

    let savedClaimTheme = "light";

    try {
        savedClaimTheme =
            localStorage.getItem("findit-theme") || "light";
    } catch (error) {
        console.warn("Unable to read saved theme:", error);
    }

    applyClaimTheme(savedClaimTheme);

    claimThemeToggle.addEventListener("click", function () {

        const currentTheme =
            document.documentElement.getAttribute("data-theme");

        const newTheme =
            currentTheme === "dark" ? "light" : "dark";

        applyClaimTheme(newTheme);

        try {
            localStorage.setItem("findit-theme", newTheme);
        } catch (error) {
            console.warn("Unable to save theme:", error);
        }
    });
</script>

</body>
</html>