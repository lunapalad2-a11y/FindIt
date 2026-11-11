
<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute(["email" => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "Admin") {
                header("Location: admin/dashboard.php");
                exit;
            }

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Log in to your FindIt account to access the campus lost and found system.">
    <meta name="theme-color" content="#046A38">

    <title>Login | FindIt</title>

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
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 75px 20px 40px;
            background: var(--light-celadon);
            position: relative;
            transition: background-color 0.3s ease;
        }

        .login-theme-toggle {
            position: absolute;
            top: 22px;
            right: 25px;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text);
            font-size: 19px;
            cursor: pointer;
            transition: 0.25s;
        }

        .login-theme-toggle:hover {
            background: var(--section-bg);
            border-color: var(--emerald);
            transform: rotate(12deg);
        }

        .login-theme-toggle:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 45px;
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 10px 35px var(--shadow);
            transition: background-color 0.3s, color 0.3s;
        }

        .login-logo {
            text-align: center;
            font-size: 32px;
            font-weight: 900;
            color: var(--emerald);
            margin-bottom: 25px;
        }

        .login-container h1 {
            text-align: center;
            font-size: 32px;
            font-weight: 900;
            color: var(--heading-color);
            margin-bottom: 8px;
        }

        .login-subtitle {
            text-align: center;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .login-form label {
            display: block;
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .login-form input {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            margin-bottom: 20px;
            background: var(--input-bg);
            color: var(--text);
        }

        .login-form input::placeholder {
            color: var(--muted);
        }

        .login-form input:focus {
            border-color: var(--emerald);
            outline: 2px solid var(--celadon);
            outline-offset: 2px;
        }

        .login-submit {
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

        .login-submit:hover {
            background: var(--deep-emerald);
        }

        .login-submit:focus-visible,
        .register-link a:focus-visible,
        .back-home:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            color: var(--muted);
            font-size: 14px;
        }

        .register-link a {
            color: var(--emerald);
            font-weight: 800;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--deep-emerald);
            font-size: 13px;
            font-weight: 700;
        }

        .error-message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #FCE8E8;
            color: #A32626;
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 22px;
            }

            .login-container h1 {
                font-size: 27px;
            }

            .login-theme-toggle {
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

    <main class="login-page">

        <button
            type="button"
            class="login-theme-toggle"
            id="loginThemeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Toggle dark mode"
        >
            <span id="loginThemeIcon" aria-hidden="true">🌙</span>
        </button>

        <section class="login-container" aria-labelledby="login-heading">

            <div class="login-logo" aria-label="FindIt homepage">
                FindIt.
            </div>

            <h1 id="login-heading">Welcome Back!</h1>

            <p class="login-subtitle">
                Log in to your FindIt account.
            </p>

            <?php if ($error): ?>
                <div
                    class="error-message"
                    role="alert"
                    aria-live="assertive"
                >
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form
                class="login-form"
                action="login.php"
                method="POST"
                aria-label="Login form"
            >

                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    autocomplete="username"
                    required
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="login-submit">
                    Log In
                </button>

            </form>

            <p class="register-link">
                Don't have an account?
                <a href="register.php">Create an account</a>
            </p>

            <a href="index.php" class="back-home">
                Back to Homepage
            </a>

        </section>
    </main>

    <script>
        const loginThemeToggle = document.getElementById("loginThemeToggle");
        const loginThemeIcon = document.getElementById("loginThemeIcon");

        function applyLoginTheme(theme) {
            const isDark = theme === "dark";

            document.documentElement.setAttribute(
                "data-theme",
                isDark ? "dark" : "light"
            );

            loginThemeIcon.textContent = isDark ? "☀️" : "🌙";

            loginThemeToggle.setAttribute(
                "aria-label",
                isDark ? "Switch to light mode" : "Switch to dark mode"
            );

            loginThemeToggle.setAttribute(
                "aria-pressed",
                String(isDark)
            );

            const themeColor = document.querySelector('meta[name="theme-color"]');

            if (themeColor) {
                themeColor.setAttribute(
                    "content",
                    isDark ? "#111D17" : "#046A38"
                );
            }
        }

        let savedLoginTheme = "light";

        try {
            savedLoginTheme =
                localStorage.getItem("findit-theme") || "light";
        } catch (error) {
            console.warn("Unable to read saved theme:", error);
        }

        applyLoginTheme(savedLoginTheme);

        loginThemeToggle.addEventListener("click", function () {
            const currentTheme =
                document.documentElement.getAttribute("data-theme");

            const newTheme =
                currentTheme === "dark" ? "light" : "dark";

            applyLoginTheme(newTheme);

            try {
                localStorage.setItem("findit-theme", newTheme);
            } catch (error) {
                console.warn("Unable to save theme:", error);
            }
        });
    </script>

</body>
</html>