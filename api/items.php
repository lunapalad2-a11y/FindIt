
<?php
ob_start();

session_start();

header("Content-Type: application/json; charset=UTF-8");

function respond($success, $message, $items = [], $httpCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($httpCode);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "items" => $items
    ], JSON_UNESCAPED_SLASHES);

    exit;
}

try {
    require_once __DIR__ . "/../config/database.php";

    $search = trim($_GET["search"] ?? "");
    $type = $_GET["type"] ?? "All";

    if (!in_array($type, ["All", "Lost", "Found"], true)) {
        $type = "All";
    }

    $isLoggedIn = isset($_SESSION["user_id"]);
    $userId = $isLoggedIn ? (int) $_SESSION["user_id"] : 0;

    $sql = "
        SELECT
            items.item_id,
            items.user_id,
            items.item_name,
            items.description,
            items.category,
            items.item_type,
            items.location,
            items.date_lost_found,
            items.photo_url,
            items.status,
            users.full_name AS reporter_name,

            CASE
                WHEN items.user_id = :owner_id THEN 1
                ELSE 0
            END AS is_owner,

            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM claims
                    WHERE claims.item_id = items.item_id
                    AND claims.user_id = :claim_user_id
                    AND claims.status IN ('Pending', 'Approved')
                ) THEN 1
                ELSE 0
            END AS already_claimed

        FROM items

        INNER JOIN users
            ON items.user_id = users.user_id

        WHERE items.status = 'Approved'
    ";

    $params = [
        ":owner_id" => $userId,
        ":claim_user_id" => $userId
    ];

    if ($type !== "All") {
        $sql .= " AND items.item_type = :item_type";
        $params[":item_type"] = $type;
    }

    if ($search !== "") {
        $sql .= "
            AND (
                items.item_name LIKE :search_name
                OR items.description LIKE :search_description
                OR items.category LIKE :search_category
                OR items.location LIKE :search_location
            )
        ";

        $keyword = "%" . $search . "%";

        $params[":search_name"] = $keyword;
        $params[":search_description"] = $keyword;
        $params[":search_category"] = $keyword;
        $params[":search_location"] = $keyword;
    }

    $sql .= " ORDER BY items.date_posted DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item["item_id"] = (int) $item["item_id"];
        $item["user_id"] = (int) $item["user_id"];

        $item["is_owner"] = (bool) $item["is_owner"];
        $item["already_claimed"] = (bool) $item["already_claimed"];

        $item["can_claim"] =
            $isLoggedIn &&
            $item["item_type"] === "Found" &&
            !$item["is_owner"] &&
            !$item["already_claimed"];
    }

    unset($item);

    respond(true, "Items retrieved successfully.", $items);

} catch (Throwable $e) {
    error_log("Browse API Error: " . $e->getMessage());

    respond(
        false,
        "Unable to retrieve items. Check the PHP error log.",
        [],
        500
    );
}