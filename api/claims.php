```php
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once "../config/database.php";

function respond($success, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

// Authentication
if (!isset($_SESSION["user_id"])) {
    respond(false, "Please log in first.", [], 401);
}

$userId = (int) $_SESSION["user_id"];
$method = $_SERVER["REQUEST_METHOD"];

// GET: View logged-in user's claims
if ($method === "GET") {
    try {
        $stmt = $pdo->prepare("
            SELECT
                claims.claim_id,
                claims.item_id,
                claims.claim_message,
                claims.status AS claim_status,
                claims.created_at,
                items.item_name,
                items.item_type,
                items.location,
                items.status AS item_status,
                items.photo_url
            FROM claims
            INNER JOIN items
                ON claims.item_id = items.item_id
            WHERE claims.user_id = ?
            ORDER BY claims.created_at DESC
        ");

        $stmt->execute([$userId]);

        respond(
            true,
            "Claims retrieved successfully.",
            $stmt->fetchAll()
        );

    } catch (PDOException $e) {
        error_log($e->getMessage());
        respond(false, "Unable to retrieve claims.", [], 500);
    }
}

// POST: Submit a claim
if ($method === "POST") {

    // Accept JSON or regular form data
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        $input = $_POST;
    }

    $itemId = filter_var(
        $input["item_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $claimMessage = trim($input["claim_message"] ?? "");

    if (!$itemId || $itemId < 1) {
        respond(false, "Invalid item ID.", [], 400);
    }

    if ($claimMessage === "") {
        respond(false, "Please provide a claim message.", [], 400);
    }

    if (strlen($claimMessage) > 2000) {
        respond(false, "Claim message must not exceed 2000 characters.", [], 400);
    }

    try {
        // Check item availability and ownership
        $stmt = $pdo->prepare("
            SELECT item_id, user_id, status, item_type
            FROM items
            WHERE item_id = ?
        ");

        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            respond(false, "Item not found.", [], 404);
        }

        if ((int)$item["user_id"] === $userId) {
            respond(false, "You cannot claim your own report.", [], 403);
        }

        if ($item["status"] !== "Approved") {
            respond(false, "This item is not available for claiming.", [], 400);
        }

        // Only found items can be claimed
        if ($item["item_type"] !== "Found") {
            respond(false, "Only found items can be claimed.", [], 400);
        }

        // Prevent duplicate pending or approved claims
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
            respond(false, "You already have an active claim for this item.", [], 409);
        }

        // Create claim
        $stmt = $pdo->prepare("
            INSERT INTO claims (
                item_id,
                user_id,
                claim_message,
                status
            )
            VALUES (?, ?, ?, 'Pending')
        ");

        $stmt->execute([
            $itemId,
            $userId,
            $claimMessage
        ]);

        $claimId = (int)$pdo->lastInsertId();

        respond(
            true,
            "Your claim has been submitted successfully. Please wait for admin review.",
            [
                "claim_id" => $claimId,
                "item_id" => $itemId,
                "status" => "Pending"
            ],
            201
        );

    } catch (PDOException $e) {
        error_log($e->getMessage());
        respond(false, "Unable to submit claim. Please try again.", [], 500);
    }
}

// Unsupported HTTP methods
header("Allow: GET, POST");
respond(false, "Method not allowed.", [], 405);
?>