
<?php

error_reporting(E_ALL);
ini_set("display_errors", "1");

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$allowedTypes = ["Lost", "Found"];

$itemType = $_GET["type"] ?? "Lost";

if (!in_array($itemType, $allowedTypes, true)) {
    $itemType = "Lost";
}

$validCategories = [
    "Electronics",
    "Accessories",
    "School Supplies",
    "Clothing",
    "Personal Belongings",
    "Others"
];

$error = "";
$success = isset($_GET["success"]);
$itemName = "";
$description = "";
$category = "";
$location = "";
$date = "";

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $itemName = trim($_POST["item_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $date = $_POST["date_lost_found"] ?? "";
    $postedType = $_POST["item_type"] ?? "";

    if (
        $itemName === "" ||
        $description === "" ||
        $category === "" ||
        $location === "" ||
        $date === ""
    ) {
        $error = "Please complete all required fields.";

    } elseif (!in_array($postedType, $allowedTypes, true)) {
        $error = "Invalid item type.";

    } elseif (!in_array($category, $validCategories, true)) {
        $error = "Please select a valid category.";

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !checkdate(
            (int) substr($date, 5, 2),
            (int) substr($date, 8, 2),
            (int) substr($date, 0, 4)
        ) ||
        $date > date("Y-m-d")
    ) {
        $error = "Please enter a valid date.";

    } else {

        $photoPath = null;
        $uploadedFile = null;

        // Handle optional image upload
        if (
            isset($_FILES["item_photo"]) &&
            $_FILES["item_photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $file = $_FILES["item_photo"];

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

                    if (!is_dir($uploadDirectory)) {
                        if (!mkdir($uploadDirectory, 0755, true)) {
                            $error = "Unable to create the uploads folder.";
                        }
                    }

                    if ($error === "") {

                        $fileName = bin2hex(random_bytes(16))
                            . "." . $allowedMimeTypes[$mimeType];

                        $destination = $uploadDirectory . $fileName;

                        if (move_uploaded_file($file["tmp_name"], $destination)) {
                            $photoPath = "uploads/" . $fileName;
                            $uploadedFile = $destination;
                        } else {
                            $error = "Unable to save the uploaded photo.";
                        }
                    }
                }
            }
        }

        // Save report to database
        if ($error === "") {

            try {

                $sql = "INSERT INTO items
                    (
                        user_id,
                        item_name,
                        description,
                        category,
                        item_type,
                        location,
                        date_lost_found,
                        status,
                        photo_url
                    )
                    VALUES
                    (
                        :user_id,
                        :item_name,
                        :description,
                        :category,
                        :item_type,
                        :location,
                        :date_lost_found,
                        'Pending',
                        :photo_url
                    )";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ":user_id" => $_SESSION["user_id"],
                    ":item_name" => $itemName,
                    ":description" => $description,
                    ":category" => $category,
                    ":item_type" => $postedType,
                    ":location" => $location,
                    ":date_lost_found" => $date,
                    ":photo_url" => $photoPath
                ]);

                header(
                    "Location: report-item.php?type=" .
                    urlencode($postedType) . "&success=1"
                );
                exit;

            } catch (PDOException $e) {

                if ($uploadedFile && file_exists($uploadedFile)) {
                    unlink($uploadedFile);
                }

                error_log("FindIt report submission error: " . $e->getMessage());

                $error = "Unable to save your report. Please try again later.";
            }
        }
    }

    if (in_array($postedType, $allowedTypes, true)) {
        $itemType = $postedType;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#046A38">

    <title>Report <?php echo e($itemType); ?> Item | FindIt</title>

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
        body {
            margin: 0;
            background: var(--page-bg, var(--soft-green, #F4FAF6));
            color: var(--text, #183B2A);
            transition: background-color 0.3s, color 0.3s;
        }

        .report-container {
            max-width: 750px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .report-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
        }

        .back-btn {
            display: inline-block;
            padding: 12px 20px;
            background: var(--emerald, #046A38);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .back-btn:hover {
            background: var(--deep-emerald, #024D2B);
            transform: translateY(-2px);
        }

        .report-theme-toggle {
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

        .report-theme-toggle:hover {
            border-color: var(--emerald);
            background: var(--section-bg);
            transform: rotate(12deg);
        }

        .report-theme-toggle:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .report-card {
            background: var(--card-bg, #FFFFFF);
            color: var(--text);
            padding: 35px;
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 16px;
            box-shadow: 0 8px 25px var(--shadow, rgba(4, 106, 56, 0.08));
            transition: background-color 0.3s, color 0.3s;
        }

        .report-card h1 {
            color: var(--heading-color, var(--dark-emerald, #013B22));
            margin: 0 0 10px;
        }

        .description {
            color: var(--muted, #698273);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text, #183B2A);
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border: 1px solid var(--border, #D3E8DA);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            background: var(--input-bg, #FFFFFF);
            color: var(--text, #183B2A);
            transition: border-color 0.2s, background-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px var(--shadow);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--muted);
        }

        .form-group select option {
            background: var(--card-bg);
            color: var(--text);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Validation styles */
        .form-group .field-invalid {
            border: 2px solid #D93025 !important;
            box-shadow: 0 0 0 2px rgba(217, 48, 37, 0.10);
        }

        .field-error-text {
            display: none;
            color: #D93025;
            font-size: 12px;
            margin-top: 6px;
        }

        .field-error-text.visible {
            display: block;
        }

        .validation-message {
            display: none;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 22px;
            line-height: 1.5;
            background: #FCE8E8;
            color: #A32626;
            border-left: 4px solid #D93025;
        }

        .validation-message.visible {
            display: block;
        }

        .upload-area {
            border: 2px dashed var(--celadon, #ACE1C0);
            background: var(--section-bg, #F4FAF6);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }

        .upload-area p {
            color: var(--muted, #698273);
            font-size: 13px;
            margin: 8px 0;
        }

        .upload-area input {
            border: none;
            background: transparent;
            padding: 10px 0;
        }

        #imagePreview {
            display: none;
            max-width: 100%;
            max-height: 250px;
            margin: 15px auto 0;
            border-radius: 10px;
            object-fit: contain;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: var(--emerald, #046A38);
            color: #FFFFFF;
            font-family: inherit;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .submit-btn:hover {
            background: var(--deep-emerald, #024D2B);
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        .error {
            background: #FCE8E8;
            color: #A32626;
            overflow-wrap: anywhere;
        }

        .success {
            background: #E4F3E9;
            color: #024D2B;
        }

        @media (max-width: 500px) {
            .report-card {
                padding: 22px;
            }

            .report-container {
                margin-top: 30px;
            }

            .report-topbar {
                gap: 10px;
            }

            .back-btn {
                padding: 11px 14px;
                font-size: 13px;
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

<main class="report-container">

    <div class="report-topbar">

        <a href="../dashboard.php" class="back-btn">
            ← Back to Dashboard
        </a>

        <button
            type="button"
            class="report-theme-toggle"
            id="reportThemeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Toggle dark mode"
        >
            <span id="reportThemeIcon" aria-hidden="true">🌙</span>
        </button>

    </div>

    <section class="report-card">

        <h1>Report <?php echo e($itemType); ?> Item</h1>

        <p class="description">
            Provide the details of the item to help the PLM community
            identify and locate it.
        </p>

        <?php if ($success): ?>
            <div class="message success">
                Your report has been submitted successfully!
                It is now pending admin approval.
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="message error" role="alert">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <div
            class="validation-message"
            id="validationMessage"
            role="alert"
            aria-live="assertive"
        >
            <strong>Please complete all required fields.</strong>
            Check the fields marked in red.
        </div>

        <form
            id="reportForm"
            method="POST"
            enctype="multipart/form-data"
            action="report-item.php?type=<?php echo e($itemType); ?>"
            novalidate
        >

            <input
                type="hidden"
                name="item_type"
                value="<?php echo e($itemType); ?>"
            >

            <div class="form-group">
                <label for="item_name">Item Name</label>

                <input
                    type="text"
                    id="item_name"
                    name="item_name"
                    maxlength="100"
                    placeholder="e.g. Black Wallet"
                    value="<?php echo e($itemName); ?>"
                    required
                >

                <small class="field-error-text"></small>
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

                <small class="field-error-text"></small>
            </div>

            <div class="form-group">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe the item's color, brand, or other identifying details."
                    required
                ><?php echo e($description); ?></textarea>

                <small class="field-error-text"></small>
            </div>

            <div class="form-group">
                <label for="location">Location in PLM</label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="100"
                    placeholder="e.g. Main Building, Library"
                    value="<?php echo e($location); ?>"
                    required
                >

                <small class="field-error-text"></small>
            </div>

            <div class="form-group">
                <label for="date_lost_found">
                    Date <?php echo $itemType === "Lost" ? "Lost" : "Found"; ?>
                </label>

                <input
                    type="date"
                    id="date_lost_found"
                    name="date_lost_found"
                    max="<?php echo date("Y-m-d"); ?>"
                    value="<?php echo e($date); ?>"
                    required
                >

                <small class="field-error-text"></small>
            </div>

            <div class="form-group">
                <label for="item_photo">Item Photo (Optional)</label>

                <div class="upload-area">

                    <p><strong>Upload an item picture</strong></p>
                    <p>JPG, PNG, or WEBP · Maximum 5 MB</p>

                    <input
                        type="file"
                        id="item_photo"
                        name="item_photo"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <img
                        id="imagePreview"
                        alt="Selected item photo preview"
                    >

                </div>
            </div>

            <button type="submit" class="submit-btn">
                Submit Report
            </button>

        </form>

    </section>

</main>

<script>
    // DARK MODE
    const reportThemeToggle =
        document.getElementById("reportThemeToggle");

    const reportThemeIcon =
        document.getElementById("reportThemeIcon");

    function applyReportTheme(theme) {

        const isDark = theme === "dark";

        document.documentElement.setAttribute(
            "data-theme",
            isDark ? "dark" : "light"
        );

        reportThemeIcon.textContent = isDark ? "☀️" : "🌙";

        reportThemeToggle.setAttribute(
            "aria-label",
            isDark ? "Switch to light mode" : "Switch to dark mode"
        );

        reportThemeToggle.setAttribute(
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

    let savedReportTheme = "light";

    try {
        savedReportTheme =
            localStorage.getItem("findit-theme") || "light";
    } catch (error) {
        console.warn("Unable to read saved theme:", error);
    }

    applyReportTheme(savedReportTheme);

    reportThemeToggle.addEventListener("click", function () {

        const currentTheme =
            document.documentElement.getAttribute("data-theme");

        const newTheme =
            currentTheme === "dark" ? "light" : "dark";

        applyReportTheme(newTheme);

        try {
            localStorage.setItem("findit-theme", newTheme);
        } catch (error) {
            console.warn("Unable to save theme:", error);
        }
    });


    // FORM VALIDATION
    const reportForm = document.getElementById("reportForm");
    const validationMessage =
        document.getElementById("validationMessage");

    const requiredFields = [
        document.getElementById("item_name"),
        document.getElementById("category"),
        document.getElementById("description"),
        document.getElementById("location"),
        document.getElementById("date_lost_found")
    ];

    function clearFieldError(field) {
        field.classList.remove("field-invalid");
        field.removeAttribute("aria-invalid");

        const errorText =
            field.parentElement.querySelector(".field-error-text");

        if (errorText) {
            errorText.textContent = "";
            errorText.classList.remove("visible");
        }
    }

    function showFieldError(field) {
        field.classList.add("field-invalid");
        field.setAttribute("aria-invalid", "true");

        const errorText =
            field.parentElement.querySelector(".field-error-text");

        if (errorText) {
            errorText.textContent = "This field is required.";
            errorText.classList.add("visible");
        }
    }

    reportForm.addEventListener("submit", function (event) {

        let firstInvalidField = null;
        let hasError = false;

        requiredFields.forEach(function (field) {

            clearFieldError(field);

            if (field.value.trim() === "") {

                showFieldError(field);
                hasError = true;

                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            }
        });

        if (hasError) {

            event.preventDefault();

            validationMessage.classList.add("visible");

            validationMessage.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            if (firstInvalidField) {
                firstInvalidField.focus({
                    preventScroll: true
                });
            }

        } else {
            validationMessage.classList.remove("visible");
        }
    });

    requiredFields.forEach(function (field) {

        field.addEventListener("input", function () {
            if (field.value.trim() !== "") {
                clearFieldError(field);
            }
        });

        field.addEventListener("change", function () {
            if (field.value.trim() !== "") {
                clearFieldError(field);
            }
        });
    });


    // IMAGE PREVIEW
    const photoInput = document.getElementById("item_photo");
    const imagePreview = document.getElementById("imagePreview");

    photoInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) {
            imagePreview.style.display = "none";
            imagePreview.removeAttribute("src");
            return;
        }

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert("Please select a JPG, PNG, or WEBP image.");
            this.value = "";
            imagePreview.style.display = "none";
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert("Photo must not exceed 5 MB.");
            this.value = "";
            imagePreview.style.display = "none";
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            imagePreview.src = event.target.result;
            imagePreview.style.display = "block";
        };

        reader.readAsDataURL(file);
    });
</script>

</body>
</html>
