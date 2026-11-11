
// =====================================
// FINDIT - SEARCH FUNCTIONALITY
// =====================================

const searchForm = document.getElementById("searchForm");
const itemSearch = document.getElementById("itemSearch");
const searchMessage = document.getElementById("searchMessage");

if (searchForm && itemSearch && searchMessage) {
    searchForm.addEventListener("submit", function(event) {
        event.preventDefault();

        const searchValue = itemSearch.value.trim();

        if (searchValue === "") {
            searchMessage.textContent =
                "Please enter an item to search.";
            return;
        }

        searchMessage.textContent =
            "Item search will be available once listings are connected.";
    });
}


// =====================================
// FINDIT - DARK MODE FUNCTIONALITY
// =====================================

const themeToggle = document.getElementById("themeToggle");
const themeIcon = document.getElementById("themeIcon");
const themeText = document.getElementById("themeText");

const THEME_KEY = "findit-theme";

function getSavedTheme() {
    try {
        const savedTheme = localStorage.getItem(THEME_KEY);

        return savedTheme === "dark" ? "dark" : "light";

    } catch (error) {
        console.warn("Unable to access saved theme:", error);
        return "light";
    }
}

function saveTheme(theme) {
    try {
        localStorage.setItem(THEME_KEY, theme);

    } catch (error) {
        console.warn("Unable to save theme:", error);
    }
}

function applyTheme(theme) {

    const selectedTheme = theme === "dark" ? "dark" : "light";

    document.documentElement.setAttribute(
        "data-theme",
        selectedTheme
    );

    if (themeIcon) {
        themeIcon.textContent =
            selectedTheme === "dark" ? "☀️" : "🌙";
    }

    if (themeText) {
        themeText.textContent =
            selectedTheme === "dark" ? "Light Mode" : "Dark Mode";
    }

    if (themeToggle) {
        themeToggle.setAttribute(
            "aria-label",
            selectedTheme === "dark"
                ? "Switch to light mode"
                : "Switch to dark mode"
        );

        themeToggle.setAttribute("aria-pressed", String(selectedTheme === "dark"));
    }
}

// Apply saved theme
const initialTheme = getSavedTheme();
applyTheme(initialTheme);

// Toggle theme
if (themeToggle) {
    themeToggle.addEventListener("click", function() {

        const currentTheme =
            document.documentElement.getAttribute("data-theme");

        const newTheme =
            currentTheme === "dark" ? "light" : "dark";

        applyTheme(newTheme);
        saveTheme(newTheme);
    });
}
