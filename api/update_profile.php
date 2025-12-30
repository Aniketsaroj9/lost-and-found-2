<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

lf_require_post();
lf_start_session();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Unauthorized']);
}

$conn = lf_get_db_connection();
$userId = $_SESSION['user_id'];
$data = lf_get_request_body();

$fullName = trim($data['fullName'] ?? '');
$phone = trim($data['phone'] ?? '');
$studentId = trim($data['studentId'] ?? '');

// Basic validation
if (empty($fullName)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Full Name is required']);
}

try {
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, student_id = ? WHERE id = ?");
    $stmt->bind_param("sssi", $fullName, $phone, $studentId, $userId);
    
    if ($stmt->execute()) {
        // Update session name if changed
        $_SESSION['user_name'] = $fullName;
        
        lf_send_json(200, [
            'status' => 'success', 
            'message' => 'Profile updated successfully',
            'user' => [
                'fullName' => $fullName,
                'phone' => $phone,
                'studentId' => $studentId
            ]
        ]);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    lf_send_json(500, ['status' => 'error', 'message' => 'Failed to update profile']);
}
