<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

lf_start_session();

function lf_log($msg) {
    file_put_contents(__DIR__ . '/../logs/debug_items.log', "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

lf_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
lf_log("POST Data: " . print_r($_POST, true));
lf_log("FILES Data: " . print_r($_FILES, true));
lf_log("Session User ID: " . ($_SESSION['user_id'] ?? 'NULL'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'You must be logged in to report an item.']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    handleCreateItem();
} elseif ($method === 'GET') {
    handleGetItems();
} else {
    lf_send_json(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

function handleGetItems() {
    global $conn;
    $conn = lf_get_db_connection();
    
    // Optional filters
    $filterUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    $sql = "
        SELECT i.*, c.name as category_name, ii.image_path, u.full_name as user_name
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN item_images ii ON i.id = ii.item_id AND ii.is_primary = 1
        LEFT JOIN users u ON i.user_id = u.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if ($filterUser) {
        $sql .= " AND i.user_id = ?";
        $params[] = $filterUser;
        $types .= "i";
    }

    if ($search) {
        $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location_found LIKE ? OR i.location_lost LIKE ?)";
        $wildcard = "%$search%";
        $params[] = $wildcard; 
        $params[] = $wildcard; 
        $params[] = $wildcard; 
        $params[] = $wildcard; 
        $types .= "ssss";
    }

    if ($category && $category !== 'all') {
        $sql .= " AND c.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    $sql .= " ORDER BY i.date_reported DESC LIMIT 50";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize fields for frontend
        $row['location'] = $row['item_type'] === 'lost' ? $row['location_lost'] : $row['location_found'];
        $row['date'] = $row['date_lost_found'];
        $items[] = $row;
    }

    lf_send_json(200, ['status' => 'success', 'data' => $items]);
}

function handleCreateItem() {
    global $conn;
    $conn = lf_get_db_connection();

    // Note: When using FormData with file uploads, $_POST and $_FILES are populated, not php://input JSON
    $userId = $_SESSION['user_id'];
    $title = trim($_POST['itemName'] ?? '');
    $categoryName = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $dateTime = $_POST['datetime'] ?? '';
    // Determine type based on source form or hidden field, but for now we can infer or pass it.
    // The previous plan didn't specify how to distinguish, so let's check a posted string or infer.
    // Ideally the form should send 'item_type'. Let's assume the frontend sends it.
    // If not, we might need to update the frontend to include it. 
    // Wait, the designs have separate forms. I will add `item_type` to the FormData in JS.
    $itemType = $_POST['type'] ?? 'lost'; // default to lost if not set, but should be set

    if ($title === '' || $categoryName === '' || $dateTime === '') {
        lf_send_json(422, ['status' => 'error', 'message' => 'Please fill in all required fields.']);
    }

    // Resolve Category ID
    // Map frontend values to DB values
    $categoryMap = [
        'Others' => 'Other',
        'Cards & IDs' => 'Documents',
        'Books' => 'Other' // Map Books to Other if not exists, or we could let it fallback
    ];
    
    $searchName = $categoryMap[$categoryName] ?? $categoryName;

    $catStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $catStmt->bind_param("s", $searchName);
    $catStmt->execute();
    $catResult = $catStmt->get_result();
    $categoryId = null;
    
    if ($row = $catResult->fetch_assoc()) {
        $categoryId = $row['id'];
    } else {
        // Fallback to 'Other'
        $otherStmt = $conn->query("SELECT id FROM categories WHERE name = 'Other' LIMIT 1");
        if ($otherRow = $otherStmt->fetch_assoc()) {
            $categoryId = $otherRow['id'];
        }
    }
    
    // Ensure we don't pass 0 or false
    if (!$categoryId) {
        $categoryId = null; // Let it be null if allowed, or it will fail FK if not allowed.
                            // Schema says: category_id INT UNSIGNED ... FOREIGN KEY references categories(id) ON DELETE SET NULL
                            // So NULL is allowed.
    }

    // Handle File Upload
    $uploadedImagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];
        
        // Simple validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes)) {
             lf_send_json(422, ['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
        }

        // Generate new name
        $newFileName = uniqid('item_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Store relative path
            $uploadedImagePath = 'uploads/' . $newFileName;
        } else {
             lf_send_json(500, ['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        }
    }

    try {
        $conn->begin_transaction();

        // 1. Insert Item
        // Map fields: 
        // location -> location_lost if type=lost, location_found if type=found
        $locationLost = ($itemType === 'lost') ? $location : null;
        $locationFound = ($itemType === 'found') ? $location : null;
        // date_lost_found -> $dateTime
        
        $insertQuery = "INSERT INTO items (user_id, category_id, title, description, item_type, location_lost, location_found, date_lost_found) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        if (!$stmt) {
             throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iissssss", $userId, $categoryId, $title, $description, $itemType, $locationLost, $locationFound, $dateTime);
        
        lf_log("Executing Insert...");
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $itemId = $stmt->insert_id;
        lf_log("Insert Success. Item ID: " . $itemId);

        // 2. Insert Image if exists
        if ($uploadedImagePath) {
            $imgStmt = $conn->prepare("INSERT INTO item_images (item_id, image_path, is_primary) VALUES (?, ?, 1)");
            $imgStmt->bind_param("is", $itemId, $uploadedImagePath);
            $imgStmt->execute();
        }

        $conn->commit();
        lf_log("Transaction Committed.");

        lf_send_json(201, [
            'status' => 'success', 
            'message' => 'Item reported successfully.',
            'item_id' => $itemId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Item creation error: ' . $e->getMessage());
        lf_send_json(500, ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
