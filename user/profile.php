
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$userId = (int) $_SESSION["user_id"];

$success = "";
$error = "";

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Get current user information
$stmt = $pdo->prepare(
    "SELECT user_id, full_name, email, password, role
     FROM users
     WHERE user_id = :user_id
     LIMIT 1"
);

$stmt->execute(["user_id" => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $action = $_POST["action"] ?? "";

        // UPDATE PROFILE
        if ($action === "update_profile") {

            $fullName = trim($_POST["full_name"] ?? "");
            $email = trim($_POST["email"] ?? "");

            if ($fullName === "" || $email === "") {
                $error = "Please complete all profile fields.";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";

            } else {

                // Check if email is already used by another account
                $checkEmail = $pdo->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = :email
                     AND user_id != :user_id
                     LIMIT 1"
                );

                $checkEmail->execute([
                    "email" => $email,
                    "user_id" => $userId
                ]);

                if ($checkEmail->fetch()) {
                    $error = "This email address is already registered.";

                } else {

                    $updateProfile = $pdo->prepare(
                        "UPDATE users
                         SET full_name = :full_name,
                             email = :email
                         WHERE user_id = :user_id"
                    );

                    $updateProfile->execute([
                        "full_name" => $fullName,
                        "email" => $email,
                        "user_id" => $userId
                    ]);

                    // Update session information
                    $_SESSION["full_name"] = $fullName;
                    $_SESSION["email"] = $email;

                    $success = "Your profile has been updated successfully.";
                }
            }

        // CHANGE PASSWORD
        } elseif ($action === "change_password") {

            $currentPassword = $_POST["current_password"] ?? "";
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            if (
                $currentPassword === "" ||
                $newPassword === "" ||
                $confirmPassword === ""
            ) {
                $error = "Please complete all password fields.";

            } elseif (!password_verify($currentPassword, $user["password"])) {
                $error = "Your current password is incorrect.";

            } elseif (strlen($newPassword) < 8) {
                $error = "Your new password must be at least 8 characters.";

            } elseif ($newPassword !== $confirmPassword) {
                $error = "The new passwords do not match.";

            } elseif (password_verify($newPassword, $user["password"])) {
                $error = "Please choose a password different from your current one.";

            } else {

                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $updatePassword = $pdo->prepare(
                    "UPDATE users
                     SET password = :password
                     WHERE user_id = :user_id"
                );

                $updatePassword->execute([
                    "password" => $hashedPassword,
                    "user_id" => $userId
                ]);

                $success = "Your password has been changed successfully.";
            }

        } else {
            $error = "Invalid form request.";
        }
    }
}

// Get updated user information
$stmt = $pdo->prepare(
    "SELECT user_id, full_name, email, password, role
     FROM users
     WHERE user_id = :user_id
     LIMIT 1"
);

$stmt->execute(["user_id" => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit;
}

// Keep session information updated
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];

$initial = strtoupper(substr(trim($user["full_name"]), 0, 1));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Manage your FindIt account profile and password.">
    <meta name="theme-color" content="#F4FAF6">

    <title>My Profile | FindIt</title>

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
        .profile-page {
            min-height: 100vh;
            padding: 45px 20px 70px;
            background: var(--page-bg);
            color: var(--text);
            transition: background-color 0.3s, color 0.3s;
        }

        .profile-container {
            width: 100%;
            max-width: 950px;
            margin: 0 auto;
        }

        .profile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 30px;
        }

        .profile-heading h1 {
            color: var(--heading-color);
            font-size: 30px;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .profile-heading p {
            color: var(--muted);
            font-size: 14px;
        }

        .profile-theme-toggle {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text);
            font-size: 19px;
            cursor: pointer;
            transition: 0.25s;
        }

        .profile-theme-toggle:hover {
            border-color: var(--emerald);
            transform: rotate(12deg);
        }

        .profile-theme-toggle:focus-visible,
        .profile-button:focus-visible,
        .profile-back:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .profile-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px var(--shadow);
        }

        .profile-overview {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .profile-avatar {
            width: 85px;
            height: 85px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--light-celadon);
            color: var(--deep-emerald);
            font-size: 34px;
            font-weight: 900;
        }

        .profile-overview h2 {
            color: var(--heading-color);
            font-size: 23px;
            font-weight: 900;
            margin-bottom: 5px;
            overflow-wrap: anywhere;
        }

        .profile-overview p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 8px;
            overflow-wrap: anywhere;
        }

        .profile-role {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: var(--light-celadon);
            color: var(--deep-emerald);
            font-size: 12px;
            font-weight: 800;
        }

        .profile-section-title {
            color: var(--heading-color);
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .profile-section-description {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-field.full-width {
            grid-column: 1 / -1;
        }

        .profile-field label {
            color: var(--text);
            font-size: 13px;
            font-weight: 800;
        }

        .profile-field input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .profile-field input::placeholder {
            color: var(--muted);
        }

        .profile-field input:focus {
            border-color: var(--emerald);
            outline: 2px solid var(--celadon);
            outline-offset: 2px;
        }

        .profile-field input[readonly] {
            background: var(--section-bg);
            cursor: not-allowed;
        }

        .profile-button {
            grid-column: 1 / -1;
            justify-self: start;
            padding: 13px 23px;
            border: none;
            border-radius: 8px;
            background: var(--emerald);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.25s;
        }

        .profile-button:hover {
            background: var(--deep-emerald);
        }

        .profile-alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
        }

        .profile-alert.success {
            background: #E3F5E9;
            color: #176238;
            border: 1px solid #B8E2C6;
        }

        .profile-alert.error {
            background: #FCE8E8;
            color: #A32626;
            border: 1px solid #F2C5C5;
        }

        html[data-theme="dark"] .profile-alert.success {
            background: #1D3B29;
            color: #A6E7BE;
            border-color: #315B46;
        }

        html[data-theme="dark"] .profile-alert.error {
            background: #422626;
            color: #FFB8B8;
            border-color: #704040;
        }

        .profile-back {
            display: inline-block;
            color: var(--emerald);
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            margin-top: 5px;
        }

        .profile-back:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .profile-page {
                padding: 30px 15px 50px;
            }

            .profile-heading h1 {
                font-size: 25px;
            }

            .profile-card {
                padding: 22px 18px;
            }

            .profile-overview {
                align-items: flex-start;
                gap: 15px;
            }

            .profile-avatar {
                width: 65px;
                height: 65px;
                font-size: 27px;
            }

            .profile-overview h2 {
                font-size: 18px;
            }

            .profile-form {
                grid-template-columns: 1fr;
            }

            .profile-field.full-width,
            .profile-button {
                grid-column: 1;
            }

            .profile-button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<main class="profile-page">

    <div class="profile-container">

        <div class="profile-top">

            <div class="profile-heading">
                <h1>My Profile</h1>
                <p>Manage your FindIt account information.</p>
            </div>

            <button
                type="button"
                class="profile-theme-toggle"
                id="profileThemeToggle"
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Toggle dark mode"
            >
                <span id="profileThemeIcon" aria-hidden="true">🌙</span>
            </button>

        </div>

        <?php if ($success !== ""): ?>
            <div class="profile-alert success" role="status" aria-live="polite">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="profile-alert error" role="alert" aria-live="assertive">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE OVERVIEW -->
        <section class="profile-card">

            <div class="profile-overview">

                <div class="profile-avatar" aria-hidden="true">
                    <?= e($initial) ?>
                </div>

                <div>
                    <h2><?= e($user["full_name"]) ?></h2>
                    <p><?= e($user["email"]) ?></p>

                    <span class="profile-role">
                        <?= e($user["role"]) ?> Account
                    </span>
                </div>

            </div>

        </section>

        <!-- EDIT PROFILE -->
        <section class="profile-card">

            <h2 class="profile-section-title">Personal Information</h2>

            <p class="profile-section-description">
                Update your name and email address associated with your FindIt account.
            </p>

            <form method="POST" action="profile.php" class="profile-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="update_profile"
                >

                <div class="profile-field full-width">
                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= e($user["full_name"]) ?>"
                        maxlength="150"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="profile-field full-width">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($user["email"]) ?>"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="profile-field">
                    <label for="role">Account Role</label>

                    <input
                        type="text"
                        id="role"
                        value="<?= e($user["role"]) ?>"
                        readonly
                    >
                </div>

                <div class="profile-field">
                    <label for="user_id">User ID</label>

                    <input
                        type="text"
                        id="user_id"
                        value="<?= e($user["user_id"]) ?>"
                        readonly
                    >
                </div>

                <button type="submit" class="profile-button">
                    Save Changes
                </button>

            </form>

        </section>

        <!-- CHANGE PASSWORD -->
        <section class="profile-card">

            <h2 class="profile-section-title">Change Password</h2>

            <p class="profile-section-description">
                Update your password to help keep your FindIt account secure.
                Your new password must contain at least 8 characters.
            </p>

            <form method="POST" action="profile.php" class="profile-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >

                <div class="profile-field full-width">
                    <label for="current_password">Current Password</label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Enter your current password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="profile-field">
                    <label for="new_password">New Password</label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter new password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="profile-field">
                    <label for="confirm_password">Confirm New Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="profile-button">
                    Update Password
                </button>

            </form>

        </section>

        <a href="../dashboard.php" class="profile-back">
            ← Back to Dashboard
        </a>

    </div>

</main>

<script>
    const profileThemeToggle =
        document.getElementById("profileThemeToggle");

    const profileThemeIcon =
        document.getElementById("profileThemeIcon");

    const profileThemeMeta =
        document.querySelector('meta[name="theme-color"]');

    function applyProfileTheme(theme) {
        const isDark = theme === "dark";

        document.documentElement.setAttribute(
            "data-theme",
            isDark ? "dark" : "light"
        );

        profileThemeIcon.textContent = isDark ? "☀️" : "🌙";

        profileThemeToggle.setAttribute(
            "aria-label",
            isDark ? "Switch to light mode" : "Switch to dark mode"
        );

        profileThemeToggle.setAttribute(
            "aria-pressed",
            String(isDark)
        );

        if (profileThemeMeta) {
            profileThemeMeta.setAttribute(
                "content",
                isDark ? "#111D17" : "#F4FAF6"
            );
        }
    }

    let currentProfileTheme = "light";

    try {
        currentProfileTheme =
            localStorage.getItem("findit-theme") === "dark"
                ? "dark"
                : "light";
    } catch (error) {
        console.warn("Unable to read saved theme:", error);
    }

    applyProfileTheme(currentProfileTheme);

    profileThemeToggle.addEventListener("click", function () {

        const currentTheme =
            document.documentElement.getAttribute("data-theme");

        const newTheme =
            currentTheme === "dark" ? "light" : "dark";

        applyProfileTheme(newTheme);

        try {
            localStorage.setItem("findit-theme", newTheme);
        } catch (error) {
            console.warn("Unable to save theme:", error);
        }
    });
</script>

</body>
</html>
