
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] === "Admin") {
    header("Location: admin/dashboard.php");
    exit;
}

$fullName = htmlspecialchars($_SESSION["full_name"], ENT_QUOTES, "UTF-8");

$firstName = htmlspecialchars(
    explode(" ", $_SESSION["full_name"])[0],
    ENT_QUOTES,
    "UTF-8"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#046A38">

    <title>Dashboard | FindIt</title>

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
        body {
            background: var(--soft-green);
            margin: 0;
            transition: background-color 0.3s, color 0.3s;
        }

        .dashboard-nav {
            background: var(--navbar-bg);
            color: var(--text);
            padding: 18px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.3s;
        }

        .dashboard-logo {
            font-size: 27px;
            font-weight: bold;
            color: var(--emerald);
            text-decoration: none;
        }

        .dashboard-nav-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .dashboard-nav-right span {
            color: var(--text);
            font-size: 14px;
        }

        .dashboard-theme-toggle {
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
            flex-shrink: 0;
            transition: 0.25s;
        }

        .dashboard-theme-toggle:hover {
            background: var(--section-bg);
            border-color: var(--emerald);
            transform: rotate(12deg);
        }

        .dashboard-theme-toggle:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 19px;
            border: 1.5px solid var(--emerald);
            border-radius: 9px;
            background: var(--card-bg);
            color: var(--emerald);
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.25s ease;
        }

        .logout-btn:hover {
            background: var(--emerald);
            color: #FFFFFF;
            box-shadow: 0 4px 12px var(--shadow);
            transform: translateY(-2px);
        }

        .logout-icon {
            font-size: 17px;
        }

        .dashboard-container {
            max-width: 1150px;
            margin: 45px auto;
            padding: 0 25px;
        }

        .welcome-banner {
            background: linear-gradient(
                120deg,
                var(--emerald),
                var(--deep-emerald)
            );
            color: #FFFFFF;
            padding: 45px;
            border-radius: 18px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner .eyebrow {
            color: var(--celadon);
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .welcome-banner h1 {
            color: #FFFFFF;
            font-size: 34px;
            margin: 0 0 12px;
        }

        .welcome-banner p {
            color: #E4F3E9;
            max-width: 550px;
            line-height: 1.7;
        }

        .section-heading {
            margin: 40px 0 20px;
        }

        .section-heading h2 {
            color: var(--heading-color);
            margin-bottom: 7px;
        }

        .section-heading p {
            color: var(--muted);
            margin: 0;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .dashboard-card {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 25px;
            text-decoration: none;
            transition: 0.25s ease;
            min-height: 145px;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px var(--shadow);
            border-color: var(--celadon);
        }

        .dashboard-card:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .card-icon {
            width: 46px;
            height: 46px;
            background: var(--light-celadon);
            color: var(--emerald);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            margin-bottom: 20px;
        }

        .dashboard-card h3 {
            color: var(--heading-color);
            font-size: 17px;
            margin: 0 0 9px;
        }

        .dashboard-card p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
        }

        .dashboard-footer {
            margin-top: 45px;
            padding: 20px 0;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 750px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-banner {
                padding: 30px 25px;
            }

            .welcome-banner h1 {
                font-size: 27px;
            }
        }

        @media (max-width: 500px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-nav {
                padding: 15px 18px;
            }

            .dashboard-nav-right {
                gap: 9px;
            }

            .dashboard-nav-right span {
                display: none;
            }

            .logout-btn {
                padding: 9px 12px;
            }

            .dashboard-container {
                margin-top: 25px;
                padding: 0 18px;
            }

            .dashboard-footer {
                gap: 15px;
                flex-wrap: wrap;
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

    <nav class="dashboard-nav">
        <a href="dashboard.php" class="dashboard-logo">FindIt.</a>

        <div class="dashboard-nav-right">
            <span><?php echo $fullName; ?></span>

            <button
                type="button"
                class="dashboard-theme-toggle"
                id="dashboardThemeToggle"
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Toggle dark mode"
            >
                <span id="dashboardThemeIcon" aria-hidden="true">🌙</span>
            </button>

            <a href="logout.php" class="logout-btn">
                <span class="logout-icon">↪</span>
                Log Out
            </a>
        </div>
    </nav>

    <main class="dashboard-container">

        <section class="welcome-banner">
            <div class="eyebrow">PLM Campus Lost and Found</div>

            <h1>Hello, <?php echo $firstName; ?>!</h1>

            <p>
                Welcome back to FindIt. Whether you've lost something
                or found someone else's belongings, we're here to help.
            </p>
        </section>

        <section class="section-heading">
            <h2>What would you like to do?</h2>
            <p>Choose an action to get started.</p>
        </section>

        <section class="dashboard-grid">

            <a href="user/report-item.php?type=Lost" class="dashboard-card">
                <div class="card-icon">⌕</div>
                <h3>Report Lost Item</h3>
                <p>Let the campus community know about something you've lost.</p>
            </a>

            <a href="user/report-item.php?type=Found" class="dashboard-card">
                <div class="card-icon">＋</div>
                <h3>Report Found Item</h3>
                <p>Help return a found belonging to its rightful owner.</p>
            </a>

            <a href="index.php#search" class="dashboard-card">
                <div class="card-icon">⌕</div>
                <h3>Browse Items</h3>
                <p>Explore reported lost and found belongings.</p>
            </a>

            <a href="user/my-reports.php" class="dashboard-card">
                <div class="card-icon">▤</div>
                <h3>My Reports</h3>
                <p>View and keep track of your submitted reports.</p>
            </a>

            <a href="user/my-claims.php" class="dashboard-card">
                <div class="card-icon">✓</div>
                <h3>My Claims</h3>
                <p>Check the progress of your submitted claims.</p>
            </a>

            <!-- NEW: MY PROFILE -->
            <a href="user/profile.php" class="dashboard-card">
                <div class="card-icon">♙</div>
                <h3>My Profile</h3>
                <p>View and update your personal information and account password.</p>
            </a>

        </section>

        <footer class="dashboard-footer">
            <span>© 2026 FindIt. PLM Campus Community.</span>

            <a href="logout.php" class="logout-btn">
                <span class="logout-icon">↪</span>
                Sign Out
            </a>
        </footer>

    </main>

    <script>
        const dashboardThemeToggle =
            document.getElementById("dashboardThemeToggle");

        const dashboardThemeIcon =
            document.getElementById("dashboardThemeIcon");

        function applyDashboardTheme(theme) {
            const isDark = theme === "dark";

            document.documentElement.setAttribute(
                "data-theme",
                isDark ? "dark" : "light"
            );

            dashboardThemeIcon.textContent = isDark ? "☀️" : "🌙";

            dashboardThemeToggle.setAttribute(
                "aria-label",
                isDark ? "Switch to light mode" : "Switch to dark mode"
            );

            dashboardThemeToggle.setAttribute(
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

        let savedDashboardTheme = "light";

        try {
            savedDashboardTheme =
                localStorage.getItem("findit-theme") || "light";
        } catch (error) {
            console.warn("Unable to read saved theme:", error);
        }

        applyDashboardTheme(savedDashboardTheme);

        dashboardThemeToggle.addEventListener("click", function () {
            const currentTheme =
                document.documentElement.getAttribute("data-theme");

            const newTheme =
                currentTheme === "dark" ? "light" : "dark";

            applyDashboardTheme(newTheme);

            try {
                localStorage.setItem("findit-theme", newTheme);
            } catch (error) {
                console.warn("Unable to save theme:", error);
            }
        });
    </script>

</body>
</html>
