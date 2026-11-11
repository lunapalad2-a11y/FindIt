
<?php
session_start();

$isLoggedIn = isset($_SESSION["user_id"]);
$userId = $isLoggedIn ? (int) $_SESSION["user_id"] : 0;

$type = $_GET["type"] ?? "All";

if (!in_array($type, ["All", "Lost", "Found"], true)) {
    $type = "All";
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>FindIt | PLM Lost and Found</title>

    <meta
        name="description"
        content="FindIt is the PLM Campus Lost and Found System. Report lost belongings, browse found items, and reconnect people with their belongings."
    >

    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">

    <meta name="theme-color" content="#046A38">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="icon" type="image/png" href="assets/images/icon-192.png">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">

    <!-- Load saved theme before page displays -->
    <script>
    (function() {
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
        .browse-section {
            padding: 65px 7%;
            background: var(--section-bg);
        }

        .browse-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .browse-header h2 {
            color: var(--heading-color);
            margin-bottom: 10px;
        }

        .browse-header p {
            color: var(--muted);
        }

        .browse-filters {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .filter-btn {
            padding: 10px 22px;
            border: 1px solid var(--border);
            border-radius: 25px;
            background: var(--card-bg);
            color: var(--emerald);
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: var(--emerald);
            color: #FFFFFF;
            border-color: var(--emerald);
        }

        .filter-btn:focus-visible,
        .claim-btn:focus-visible,
        .edit-btn:focus-visible {
            outline: 3px solid var(--celadon);
            outline-offset: 3px;
        }

        .browse-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .browse-card {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px var(--shadow);
            transition: 0.25s;
        }

        .browse-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px var(--shadow);
        }

        .browse-photo,
        .browse-no-photo {
            width: 100%;
            height: 210px;
            display: block;
        }

        .browse-photo {
            object-fit: cover;
        }

        .browse-no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-celadon);
            color: var(--muted);
        }

        .browse-card-content {
            padding: 20px;
        }

        .browse-type {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: var(--light-celadon);
            color: var(--deep-emerald);
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .browse-card h3 {
            color: var(--heading-color);
            margin: 0 0 10px;
            font-size: 20px;
            overflow-wrap: anywhere;
        }

        .browse-description {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .browse-details {
            font-size: 13px;
            line-height: 1.9;
            color: var(--text);
        }

        .browse-details strong {
            color: var(--heading-color);
        }

        .claim-btn,
        .edit-btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            padding: 12px;
            margin-top: 18px;
            border-radius: 8px;
            color: #FFFFFF;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .claim-btn {
            background: var(--emerald);
        }

        .claim-btn:hover {
            background: var(--deep-emerald);
        }

        .edit-btn {
            background: #315F49;
        }

        .edit-btn:hover {
            background: #214633;
        }

        .claim-disabled {
            display: block;
            text-align: center;
            padding: 12px;
            margin-top: 18px;
            border-radius: 8px;
            background: var(--section-bg);
            color: var(--muted);
            font-size: 13px;
            font-weight: bold;
        }

        .empty-items {
            text-align: center;
            padding: 45px 20px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--muted);
        }

        .browse-login-note {
            text-align: center;
            margin-top: 25px;
            color: var(--muted);
            font-size: 14px;
        }

        .browse-loading {
            text-align: center;
            padding: 35px;
            color: var(--muted);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 900px) {
            .browse-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .browse-grid {
                grid-template-columns: 1fr;
            }

            .browse-section {
                padding: 45px 18px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .browse-card,
            .claim-btn,
            .edit-btn {
                transition: none;
            }

            .browse-card:hover {
                transform: none;
            }
        }
    </style>
</head>

<body>

<header class="navbar">

    <a href="index.php" class="logo" aria-label="FindIt Home">
        Find<span>It.</span>
    </a>

    <nav class="nav-links" aria-label="Main navigation">
        <a href="index.php" class="active" aria-current="page">Home</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#about">About</a>
    </nav>

    <div class="nav-buttons">

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Switch to dark mode"
            aria-pressed="false"
            title="Toggle dark mode"
        >
            <span id="themeIcon" aria-hidden="true">🌙</span>
        </button>

        <?php if ($isLoggedIn): ?>

            <a href="dashboard.php" class="login-btn">
                Dashboard
            </a>

            <a href="logout.php" class="register-btn">
                Log Out
            </a>

        <?php else: ?>

            <a href="login.php" class="login-btn">
                Log In
            </a>

            <a href="register.php" class="register-btn">
                Get Started
            </a>

        <?php endif; ?>

    </div>

</header>

<main>

    <section class="hero" aria-labelledby="hero-title">

        <div class="hero-content">

            <p class="eyebrow">
                PAMANTASAN NG LUNGSOD NG MAYNILA
            </p>

            <h1 id="hero-title">
                Lost something?<br>
                <span>Let's find it.</span>
            </h1>

            <p class="hero-description">
                FindIt helps the PLM community report lost belongings,
                share found items, and reconnect people with their things.
            </p>

            <div class="hero-buttons">

                <a href="#browse-items" class="primary-btn">
                    Find an Item
                </a>

                <a
                    href="<?php echo $isLoggedIn ? 'user/report-item.php' : 'login.php'; ?>"
                    class="secondary-btn"
                >
                    Report an Item
                </a>

            </div>

        </div>

        <div class="hero-visual">
            <div class="visual-icon" aria-hidden="true">⌕</div>
            <h3>One campus. One community.</h3>
            <p>Helping lost belongings find their way home.</p>
        </div>

    </section>

    <section class="search-section" id="search" aria-labelledby="search-title">

        <div>
            <h2 id="search-title">Looking for something?</h2>
            <p>Search for an item reported by the PLM community.</p>
        </div>

        <form class="search-form" id="searchForm">

            <label for="itemSearch" class="sr-only">
                Search for an item
            </label>

            <input
                type="search"
                id="itemSearch"
                name="search"
                placeholder="Search for an item..."
                aria-label="Search for an item"
                autocomplete="off"
            >

            <button type="submit">Search</button>

        </form>

    </section>

    <section
        class="browse-section"
        id="browse-items"
        aria-labelledby="browse-title"
    >

        <div class="browse-header">

            <p class="eyebrow">FIND WHAT YOU'RE LOOKING FOR</p>

            <h2 id="browse-title">Browse Reported Items</h2>

            <p>
                Explore lost and found belongings reported by the PLM community.
            </p>

        </div>

        <div class="browse-filters" role="group" aria-label="Filter reported items">

            <button
                type="button"
                class="filter-btn"
                data-type="All"
                aria-pressed="false"
            >
                All Items
            </button>

            <button
                type="button"
                class="filter-btn"
                data-type="Lost"
                aria-pressed="false"
            >
                Lost Items
            </button>

            <button
                type="button"
                class="filter-btn"
                data-type="Found"
                aria-pressed="false"
            >
                Found Items
            </button>

        </div>

        <div
            id="browseResults"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="browse-loading">
                Loading reported items...
            </div>
        </div>

        <?php if (!$isLoggedIn): ?>
            <p class="browse-login-note">
                Log in to submit a claim for a found item.
            </p>
        <?php endif; ?>

    </section>

    <section
        class="how-section"
        id="how-it-works"
        aria-labelledby="how-title"
    >

        <div class="section-heading">

            <p class="eyebrow">SIMPLE AND CONVENIENT</p>

            <h2 id="how-title">How FindIt Works</h2>

            <p>
                Three simple steps to help reunite people with their belongings.
            </p>

        </div>

        <div class="steps">

            <article class="step-card">
                <div class="step-number" aria-hidden="true">01</div>
                <h3>Report an Item</h3>
                <p>
                    Submit a report if you lost something or found an item on campus.
                </p>
            </article>

            <article class="step-card">
                <div class="step-number" aria-hidden="true">02</div>
                <h3>Search and Browse</h3>
                <p>
                    Explore reported items and look for belongings that match yours.
                </p>
            </article>

            <article class="step-card">
                <div class="step-number" aria-hidden="true">03</div>
                <h3>Submit a Claim</h3>
                <p>
                    Send a claim request and wait for the administrator's review.
                </p>
            </article>

        </div>

    </section>

    <section
        class="about-section"
        id="about"
        aria-labelledby="about-title"
    >

        <p class="eyebrow">ABOUT FINDIT</p>

        <h2 id="about-title">
            Making lost and found easier for the PLM community.
        </h2>

        <p>
            FindIt is a campus lost and found platform designed for PLM
            students, faculty, and staff. It provides one organized place
            to report, search, and manage lost and found belongings.
        </p>

    </section>

</main>

<footer class="footer">

    <a href="index.php" class="logo" aria-label="FindIt Home">
        Find<span>It.</span>
    </a>

    <p>PLM Campus Lost and Found System</p>

    <p class="copyright">
        © 2026 FindIt. All rights reserved.
    </p>

</footer>

<script>

// =====================================
// FINDIT - DARK MODE
// =====================================

const themeToggle = document.getElementById("themeToggle");
const themeIcon = document.getElementById("themeIcon");

function applyTheme(theme) {

    const isDark = theme === "dark";

    if (isDark) {
        document.documentElement.setAttribute("data-theme", "dark");
    } else {
        document.documentElement.setAttribute("data-theme", "light");
    }

    themeIcon.textContent = isDark ? "☀️" : "🌙";

    themeToggle.setAttribute(
        "aria-label",
        isDark ? "Switch to light mode" : "Switch to dark mode"
    );

    themeToggle.setAttribute(
        "aria-pressed",
        String(isDark)
    );

    themeToggle.setAttribute(
        "title",
        isDark ? "Switch to light mode" : "Switch to dark mode"
    );

    const themeColor = document.querySelector('meta[name="theme-color"]');

    if (themeColor) {
        themeColor.setAttribute(
            "content",
            isDark ? "#111D17" : "#046A38"
        );
    }
}

let currentTheme = "light";

try {
    const savedTheme = localStorage.getItem("findit-theme");

    if (savedTheme === "dark") {
        currentTheme = "dark";
    }

} catch (error) {
    console.warn("Unable to read theme preference:", error);
}

applyTheme(currentTheme);

themeToggle.addEventListener("click", function() {

    const isCurrentlyDark =
        document.documentElement.getAttribute("data-theme") === "dark";

    const newTheme = isCurrentlyDark ? "light" : "dark";

    applyTheme(newTheme);

    try {
        localStorage.setItem("findit-theme", newTheme);
    } catch (error) {
        console.warn("Unable to save theme preference:", error);
    }

});


// =====================================
// FINDIT - BROWSE AND SEARCH
// =====================================

const searchForm = document.getElementById("searchForm");
const searchInput = document.getElementById("itemSearch");
const browseResults = document.getElementById("browseResults");
const filterButtons = document.querySelectorAll(".filter-btn");

let selectedType = <?php echo json_encode($type); ?>;
let searchTimer = null;

const isLoggedIn = <?php echo $isLoggedIn ? "true" : "false"; ?>;

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, function(character) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[character];
    });
}


// =====================================
// CREATE ITEM CARD
// =====================================

function createItemCard(item) {

    let photoHTML = `
        <div class="browse-no-photo">
            No Photo Available
        </div>
    `;

    if (
        item.photo_url &&
        /^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i.test(item.photo_url)
    ) {
        photoHTML = `
            <img
                src="${escapeHTML(item.photo_url)}"
                alt="Photo of ${escapeHTML(item.item_name)}"
                class="browse-photo"
                loading="lazy"
            >
        `;
    }

    let claimHTML = "";
    let editHTML = "";

    // EDIT BUTTON
    // Only the owner of the report can see this button.
    if (isLoggedIn && item.is_owner === true) {

        editHTML = `
            <a
                href="user/edit-item.php?item_id=${encodeURIComponent(item.item_id)}"
                class="edit-btn"
            >
                Edit My Report
            </a>
        `;
    }

    // CLAIM BUTTON
    if (item.item_type === "Found") {

        if (!isLoggedIn) {

            claimHTML = `
                <a href="login.php" class="claim-btn">
                    Log In to Claim
                </a>
            `;

        } else if (item.is_owner) {

            claimHTML = `
                <span class="claim-disabled">
                    Your Reported Item
                </span>
            `;

        } else if (item.already_claimed) {

            claimHTML = `
                <span class="claim-disabled">
                    Claim Already Submitted
                </span>
            `;

        } else if (item.can_claim) {

            claimHTML = `
                <a
                    href="user/claim-item.php?item_id=${encodeURIComponent(item.item_id)}"
                    class="claim-btn"
                >
                    Claim This Item
                </a>
            `;
        }
    }

    return `
        <article class="browse-card">

            ${photoHTML}

            <div class="browse-card-content">

                <span class="browse-type">
                    ${escapeHTML(item.item_type)} Item
                </span>

                <h3>${escapeHTML(item.item_name)}</h3>

                <p class="browse-description">
                    ${escapeHTML(item.description)}
                </p>

                <div class="browse-details">

                    <div>
                        <strong>Category:</strong>
                        ${escapeHTML(item.category)}
                    </div>

                    <div>
                        <strong>Location:</strong>
                        ${escapeHTML(item.location)}
                    </div>

                    <div>
                        <strong>Date:</strong>
                        ${escapeHTML(item.date_lost_found)}
                    </div>

                    <div>
                        <strong>Reported By:</strong>
                        ${escapeHTML(item.reporter_name)}
                    </div>

                </div>

                ${editHTML}
                ${claimHTML}

            </div>
        </article>
    `;
}


// =====================================
// LOAD ITEMS
// =====================================

async function loadItems() {

    browseResults.setAttribute("aria-busy", "true");

    browseResults.innerHTML = `
        <div class="browse-loading">
            Loading reported items...
        </div>
    `;

    const keyword = searchInput.value.trim();

    const apiURL = new URL("api/items.php", document.baseURI);

    apiURL.searchParams.set("search", keyword);
    apiURL.searchParams.set("type", selectedType);

    try {

        const response = await fetch(apiURL.href, {
            method: "GET",
            headers: {
                "Accept": "application/json"
            },
            cache: "no-store"
        });

        if (!response.ok) {
            throw new Error("Server error: " + response.status);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || "Unable to retrieve items.");
        }

        if (!Array.isArray(data.items)) {
            throw new Error("Invalid items response from the server.");
        }

        if (data.items.length === 0) {

            browseResults.innerHTML = `
                <div class="empty-items">
                    <h3>No Items Found</h3>
                    <p>
                        No approved reports are available for this filter.
                        Try another keyword or filter.
                    </p>
                </div>
            `;

            return;
        }

        const cards = data.items.map(function(item) {
            return createItemCard(item);
        }).join("");

        browseResults.innerHTML = `
            <div class="browse-grid">
                ${cards}
            </div>
        `;

    } catch (error) {

        console.error("FindIt Browse Error:", error);

        browseResults.innerHTML = `
            <div class="empty-items">
                <h3>Unable to Load Items</h3>

                <p>${escapeHTML(error.message)}</p>

                <button
                    type="button"
                    class="claim-btn"
                    onclick="loadItems()"
                >
                    Try Again
                </button>
            </div>
        `;

    } finally {

        browseResults.setAttribute("aria-busy", "false");

    }

}


// =====================================
// SEARCH FORM
// =====================================

searchForm.addEventListener("submit", function(event) {
    event.preventDefault();
    loadItems();
});


// =====================================
// LIVE SEARCH
// =====================================

searchInput.addEventListener("input", function() {

    clearTimeout(searchTimer);

    searchTimer = setTimeout(function() {
        loadItems();
    }, 300);

});


// =====================================
// LOST AND FOUND FILTERS
// =====================================

filterButtons.forEach(function(button) {

    button.addEventListener("click", function() {

        selectedType = this.dataset.type;

        filterButtons.forEach(function(filter) {

            const isActive = filter === button;

            filter.classList.toggle("active", isActive);
            filter.setAttribute("aria-pressed", String(isActive));

        });

        loadItems();

    });

});


// =====================================
// SET ACTIVE FILTER
// =====================================

filterButtons.forEach(function(button) {

    const isActive = button.dataset.type === selectedType;

    button.classList.toggle("active", isActive);
    button.setAttribute("aria-pressed", String(isActive));

});


// =====================================
// INITIAL LOAD
// =====================================

loadItems();

</script>

<!-- FINDIT SERVICE WORKER -->
<script>
if ("serviceWorker" in navigator) {

    window.addEventListener("load", function() {

        navigator.serviceWorker.register("/findit/service-worker.js")

            .then(function(registration) {
                console.log(
                    "FindIt Service Worker registered successfully:",
                    registration.scope
                );
            })

            .catch(function(error) {
                console.error(
                    "FindIt Service Worker registration failed:",
                    error
                );
            });

    });

}
</script>

</body>
</html>
