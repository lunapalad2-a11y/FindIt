
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$userId = (int) $_SESSION["user_id"];
$itemId = filter_input(INPUT_GET, "item_id", FILTER_VALIDATE_INT);

if (!$itemId || $itemId < 1) {
    header("Location: ../dashboard.php");
    exit;
}

$validCategories = [
    "Electronics",
    "Accessories",
    "School Supplies",
    "Clothing",
    "Personal Belongings",
    "Others"
];

$allowedTypes = ["Lost", "Found"];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function validDate($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    return checkdate(
        (int)substr($date, 5, 2),
        (int)substr($date, 8, 2),
        (int)substr($date, 0, 4)
    ) && $date <= date("Y-m-d");
}

function isSafePhotoPath($path) {
    return is_string($path) &&
        preg_match('/^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $path);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$uploadedFile = null;
$newPhoto = null;

$stmt = $pdo->prepare("
    SELECT *
    FROM items
    WHERE item_id = :item_id
      AND user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ":item_id" => $itemId,
    ":user_id" => $userId
]);

$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit("Item not found or you are not authorized to edit this item.");
}

$itemName = $item["item_name"];
$description = $item["description"];
$category = $item["category"];
$itemType = $item["item_type"];
$location = $item["location"];
$date = $item["date_lost_found"];
$currentPhoto = $item["photo_url"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $itemName = trim($_POST["item_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $itemType = $_POST["item_type"] ?? "";
    $location = trim($_POST["location"] ?? "");
    $date = $_POST["date_lost_found"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Invalid request. Please refresh the page.";

    } elseif (
        $itemName === "" ||
        $description === "" ||
        $category === "" ||
        $location === "" ||
        $date === ""
    ) {
        $error = "Please complete all required fields.";

    } elseif (mb_strlen($itemName) > 150) {
        $error = "Item name must not exceed 150 characters.";

    } elseif (mb_strlen($location) > 255) {
        $error = "Location must not exceed 255 characters.";

    } elseif (!in_array($category, $validCategories, true)) {
        $error = "Please select a valid category.";

    } elseif (!in_array($itemType, $allowedTypes, true)) {
        $error = "Invalid item type.";

    } elseif (!validDate($date)) {
        $error = "Please enter a valid date.";

    } else {

        $newPhoto = $currentPhoto;
        $file = $_FILES["item_photo"] ?? null;

        if ($file && $file["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $error = "There was a problem uploading your photo.";

            } elseif ($file["size"] > 5 * 1024 * 1024) {
                $error = "Photo must not exceed 5 MB.";

            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file["tmp_name"]);

                $allowedMimeTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (
                    !isset($allowedMimeTypes[$mimeType]) ||
                    getimagesize($file["tmp_name"]) === false
                ) {
                    $error = "Only valid JPG, PNG, and WEBP images are allowed.";

                } else {

                    $uploadDirectory = __DIR__ . "/../uploads/";

                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true)
                    ) {
                        $error = "Unable to create uploads folder.";

                    } else {

                        $fileName = bin2hex(random_bytes(16))
                            . "." . $allowedMimeTypes[$mimeType];

                        $destination = $uploadDirectory . $fileName;

                        if (move_uploaded_file($file["tmp_name"], $destination)) {
                            $newPhoto = "uploads/" . $fileName;
                            $uploadedFile = $destination;
                        } else {
                            $error = "Unable to save uploaded photo.";
                        }
                    }
                }
            }
        }

        if ($error === "") {

            try {

                $update = $pdo->prepare("
                    UPDATE items
                    SET
                        item_name = :item_name,
                        description = :description,
                        category = :category,
                        item_type = :item_type,
                        location = :location,
                        date_lost_found = :date_lost_found,
                        photo_url = :photo_url,
                        status = 'Pending'
                    WHERE item_id = :item_id
                      AND user_id = :user_id
                ");

                $update->execute([
                    ":item_name" => $itemName,
                    ":description" => $description,
                    ":category" => $category,
                    ":item_type" => $itemType,
                    ":location" => $location,
                    ":date_lost_found" => $date,
                    ":photo_url" => $newPhoto,
                    ":item_id" => $itemId,
                    ":user_id" => $userId
                ]);

                if (
                    $uploadedFile &&
                    isSafePhotoPath($currentPhoto)
                ) {
                    $oldPhotoFile = __DIR__ . "/../" . $currentPhoto;

                    if (is_file($oldPhotoFile)) {
                        unlink($oldPhotoFile);
                    }
                }

                header("Location: ../dashboard.php?updated=1");
                exit;

            } catch (Throwable $e) {

                if ($uploadedFile && is_file($uploadedFile)) {
                    unlink($uploadedFile);
                }

                error_log("FindIt item update error: " . $e->getMessage());

                $error = "Unable to update your item. Please try again.";
            }
        }
    }
}

$photoToDisplay = $newPhoto ?: $currentPhoto;

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#046A38">

    <title>Edit Item | FindIt</title>

    <script>
    (function() {
        try {
            if (localStorage.getItem("findit-theme") === "dark") {
                document.documentElement.setAttribute("data-theme", "dark");
            }
        } catch (error) {
            console.warn("Unable to load theme:", error);
        }
    })();
    </script>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            margin: 0;
            background: var(--page-bg, #F4FAF6);
            color: var(--text, #183B2A);
        }

        .edit-container {
            max-width: 750px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .edit-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .back-btn,
        .submit-btn {
            display: inline-block;
            padding: 13px 20px;
            background: var(--emerald, #046A38);
            color: #FFFFFF;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .back-btn:hover,
        .submit-btn:hover {
            background: var(--deep-emerald, #024D2B);
        }

        .theme-toggle {
            width: 43px;
            height: 43px;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text);
            cursor: pointer;
            font-size: 19px;
        }

        .edit-card {
            padding: 35px;
            background: var(--card-bg, #FFFFFF);
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 16px;
            box-shadow: 0 8px 25px var(--shadow);
        }

        .edit-card h1 {
            color: var(--heading-color);
            margin-top: 0;
        }

        .edit-description {
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--input-bg, #FFFFFF);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .current-photo {
            display: block;
            max-width: 100%;
            max-height: 230px;
            margin: 12px auto;
            border-radius: 10px;
            object-fit: contain;
        }

        .upload-note {
            color: var(--muted);
            font-size: 13px;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #FCE8E8;
            color: #A32626;
        }

        .status-note {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: var(--section-bg);
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .submit-btn {
            width: 100%;
            font-size: 15px;
        }

        @media (max-width: 500px) {
            .edit-card {
                padding: 22px;
            }

            .edit-container {
                margin-top: 30px;
            }
        }
    </style>
</head>

<body>

<main class="edit-container">

    <div class="edit-topbar">
        <a href="../dashboard.php" class="back-btn">
            ← Back to Dashboard
        </a>

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Toggle dark mode"
        >🌙</button>
    </div>

    <section class="edit-card">

        <h1>Edit Item Report</h1>

        <p class="edit-description">
            Update the details of your reported item.
            Make sure all information is accurate.
        </p>

        <div class="status-note">
            After saving your changes, your report will return to
            Pending status and require admin approval before appearing
            on the public item list again.
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
            >

            <div class="form-group">
                <label for="item_name">Item Name</label>
                <input
                    type="text"
                    id="item_name"
                    name="item_name"
                    maxlength="150"
                    value="<?php echo e($itemName); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>

                    <?php foreach ($validCategories as $cat): ?>
                        <option
                            value="<?php echo e($cat); ?>"
                            <?php echo $category === $cat ? "selected" : ""; ?>
                        >
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="item_type">Item Type</label>
                <select id="item_type" name="item_type" required>
                    <?php foreach ($allowedTypes as $type): ?>
                        <option
                            value="<?php echo e($type); ?>"
                            <?php echo $itemType === $type ? "selected" : ""; ?>
                        >
                            <?php echo e($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    required
                ><?php echo e($description); ?></textarea>
            </div>

            <div class="form-group">
                <label for="location">Location in PLM</label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="255"
                    value="<?php echo e($location); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="date_lost_found">Date Lost or Found</label>
                <input
                    type="date"
                    id="date_lost_found"
                    name="date_lost_found"
                    max="<?php echo date("Y-m-d"); ?>"
                    value="<?php echo e($date); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="item_photo">Replace Item Photo (Optional)</label>

                <p class="upload-note">
                    <?php if (isSafePhotoPath($photoToDisplay)): ?>
                        Current photo:
                        <img
                            src="../<?php echo e($photoToDisplay); ?>"
                            alt="Current item photo"
                            class="current-photo"
                        >
                    <?php else: ?>
                        No photo currently uploaded.
                    <?php endif; ?>

                    Select a new photo only if you want to replace it.
                    JPG, PNG, or WEBP. Maximum 5 MB.
                </p>

                <input
                    type="file"
                    id="item_photo"
                    name="item_photo"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >
            </div>

            <button type="submit" class="submit-btn">
                Save Changes
            </button>

        </form>

    </section>

</main>

<script>
const themeToggle = document.getElementById("themeToggle");

function updateThemeButton() {
    const isDark =
        document.documentElement.getAttribute("data-theme") === "dark";

    themeToggle.textContent = isDark ? "☀️" : "🌙";

    themeToggle.setAttribute(
        "aria-label",
        isDark ? "Switch to light mode" : "Switch to dark mode"
    );
}

updateThemeButton();

themeToggle.addEventListener("click", function() {
    const isDark =
        document.documentElement.getAttribute("data-theme") === "dark";

    const newTheme = isDark ? "light" : "dark";

    document.documentElement.setAttribute("data-theme", newTheme);

    try {
        localStorage.setItem("findit-theme", newTheme);
    } catch (error) {
        console.warn("Unable to save theme:", error);
    }

    updateThemeButton();
});
</script>

</body>
</html>
