
<?php
session_start();
require_once "config/database.php";

$success = "";
$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $csrf_token = $_POST["csrf_token"] ?? "";

    $allowed_roles = ["Student", "Faculty", "Staff"];

    if (!hash_equals($_SESSION["csrf_token"], $csrf_token)) {
        $error = "Invalid request. Please refresh the page and try again.";

    } elseif (
        $full_name === "" ||
        $email === "" ||
        $role === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please complete all fields.";

    } elseif (strlen($full_name) > 100) {
        $error = "Full name must not exceed 100 characters.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($email) > 100) {
        $error = "Email address must not exceed 100 characters.";

    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = "Invalid account type.";

    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";

    } else {

        try {
            $checkEmail = $pdo->prepare(
                "SELECT user_id FROM users WHERE email = :email LIMIT 1"
            );

            $checkEmail->execute(["email" => $email]);

            if ($checkEmail->fetch()) {
                $error = "Email address is already registered.";

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertUser = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role)
                     VALUES (:full_name, :email, :password, :role)"
                );

                $insertUser->execute([
                    "full_name" => $full_name,
                    "email" => $email,
                    "password" => $hashedPassword,
                    "role" => $role
                ]);

                $success = "Account created successfully! You can now log in.";

                $_POST = [];

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $error = "Unable to create your account. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Create a FindIt account and join the PLM campus lost and found community.">
    <meta name="theme-color" content="#046A38">

    <title>Register | FindIt</title>

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

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .register-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 75px 20px 40px;
            background: var(--light-celadon);
            position: relative;
            transition: background-color 0.3s ease;
        }

        .register-theme-toggle {
            position: absolute;
            top: 22px;
            right: 25px;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text);
            font-size: 19px;
            cursor: pointer;
            transition: 0.25s;
        }

        .register-theme-toggle:hover {
            background: var(--section-bg);
            border-color: var(--emerald);
            transform: rotate(12deg);
        }

        .register-theme-toggle:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .register-container {
            width: 100%;
            max-width: 480px;
            padding: 40px;
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 10px 35px var(--shadow);
            transition: background-color 0.3s, color 0.3s;
        }

        .register-logo {
            text-align: center;
            font-size: 32px;
            font-weight: 900;
            color: var(--emerald);
            margin-bottom: 20px;
        }

        .register-container h1 {
            text-align: center;
            font-size: 30px;
            font-weight: 900;
            color: var(--heading-color);
            margin-bottom: 8px;
        }

        .register-subtitle {
            text-align: center;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 25px;
        }

        .register-form label {
            display: block;
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .register-form input,
        .register-form select {
            width: 100%;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            margin-bottom: 17px;
            background: var(--input-bg);
            color: var(--text);
        }

        .register-form input::placeholder {
            color: var(--muted);
        }

        .register-form input:focus,
        .register-form select:focus {
            border-color: var(--emerald);
            outline: 2px solid var(--celadon);
            outline-offset: 2px;
        }

        .register-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: var(--emerald);
            color: #FFFFFF;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
        }

        .register-submit:hover {
            background: var(--deep-emerald);
        }

        .register-submit:focus-visible,
        .login-link a:focus-visible,
        .back-home:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .login-link a {
            color: var(--emerald);
            font-weight: 800;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: var(--deep-emerald);
            font-size: 13px;
            font-weight: 700;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
        }

        .success-message {
            background: #E4F3E9;
            color: #024D2B;
        }

        .error-message {
            background: #FCE8E8;
            color: #A32626;
        }

        .success-message a {
            display: inline-block;
            margin-top: 5px;
            color: #024D2B;
            font-weight: 800;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 22px;
            }

            .register-container h1 {
                font-size: 26px;
            }

            .register-theme-toggle {
                top: 15px;
                right: 15px;
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

    <main class="register-page">

        <button
            type="button"
            class="register-theme-toggle"
            id="registerThemeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Toggle dark mode"
        >
            <span id="registerThemeIcon" aria-hidden="true">🌙</span>
        </button>

        <section
            class="register-container"
            aria-labelledby="register-heading"
        >

            <div class="register-logo">FindIt.</div>

            <h1 id="register-heading">Create an Account</h1>

            <p class="register-subtitle">
                Join the PLM campus lost and found community.
            </p>

            <?php if ($success): ?>
                <div
                    class="message success-message"
                    role="status"
                    aria-live="polite"
                >
                    <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
                    <br>
                    <a href="login.php">Go to Login</a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    class="message error-message"
                    role="alert"
                    aria-live="assertive"
                >
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form
                class="register-form"
                action="register.php"
                method="POST"
                aria-label="Registration form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
                >

                <label for="full_name">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST["full_name"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    maxlength="100"
                    autocomplete="name"
                    required
                >

                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    maxlength="100"
                    autocomplete="email"
                    required
                >

                <label for="role">Account Type</label>
                <select id="role" name="role" required>
                    <option value="">Select account type</option>

                    <option value="Student"
                        <?= (($_POST["role"] ?? "") === "Student") ? "selected" : "" ?>>
                        Student
                    </option>

                    <option value="Faculty"
                        <?= (($_POST["role"] ?? "") === "Faculty") ? "selected" : "" ?>>
                        Faculty
                    </option>

                    <option value="Staff"
                        <?= (($_POST["role"] ?? "") === "Staff") ? "selected" : "" ?>>
                        Staff
                    </option>
                </select>

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm your password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button type="submit" class="register-submit">
                    Create Account
                </button>

            </form>

            <p class="login-link">
                Already have an account?
                <a href="login.php">Log In</a>
            </p>

            <a href="index.php" class="back-home">
                Back to Homepage
            </a>

        </section>

    </main>

    <script>
        const registerThemeToggle =
            document.getElementById("registerThemeToggle");

        const registerThemeIcon =
            document.getElementById("registerThemeIcon");

        function applyRegisterTheme(theme) {
            const isDark = theme === "dark";

            document.documentElement.setAttribute(
                "data-theme",
                isDark ? "dark" : "light"
            );

            registerThemeIcon.textContent = isDark ? "☀️" : "🌙";

            registerThemeToggle.setAttribute(
                "aria-label",
                isDark ? "Switch to light mode" : "Switch to dark mode"
            );

            registerThemeToggle.setAttribute(
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

        let savedRegisterTheme = "light";

        try {
            savedRegisterTheme =
                localStorage.getItem("findit-theme") || "light";
        } catch (error) {
            console.warn("Unable to read saved theme:", error);
        }

        applyRegisterTheme(savedRegisterTheme);

        registerThemeToggle.addEventListener("click", function () {
            const currentTheme =
                document.documentElement.getAttribute("data-theme");

            const newTheme =
                currentTheme === "dark" ? "light" : "dark";

            applyRegisterTheme(newTheme);

            try {
                localStorage.setItem("findit-theme", newTheme);
            } catch (error) {
                console.warn("Unable to save theme:", error);
            }
        });
    </script>

</body>
</html>