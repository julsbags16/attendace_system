<?php
// Prevent any output before JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check connection
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: User not logged in']);
    exit;
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'getAll') {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, department, position, status, created_at FROM users WHERE role = 'employee' ORDER BY full_name");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $employees = [];
        
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        $stmt->close();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $employees]);
        
    } elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get and sanitize input
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        $role = 'employee';
        
        // Validation
        if (empty($username)) {
            throw new Exception('Username is required');
        }
        if (empty($email)) {
            throw new Exception('Email is required');
        }
        if (empty($full_name)) {
            throw new Exception('Full name is required');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if username or email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$checkStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $checkStmt->bind_param("ss", $username, $email);
        if (!$checkStmt->execute()) {
            throw new Exception('Execute failed: ' . $checkStmt->error);
        }
        
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            throw new Exception('Username or email already exists');
        }
        $checkStmt->close();
        
        // Hash password
        $default_password = 'Welcome@123';
        $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);
        
        // Insert new employee
        $insertStmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, department, position, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$insertStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!$insertStmt->bind_param("ssssssss", $username, $email, $hashed_password, $full_name, $department, $position, $role, $status)) {
            throw new Exception('Bind failed: ' . $insertStmt->error);
        }
        
        if (!$insertStmt->execute()) {
            throw new Exception('Execute failed: ' . $insertStmt->error);
        }
        
        $new_id = $insertStmt->insert_id;
        $insertStmt->close();
        
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Employee created successfully',
            'employee_id' => $new_id,
            'default_password' => $default_password
        ]);
        
    } elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        
        if ($employee_id === 0) {
            throw new Exception('Invalid employee ID');
        }
        
        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, department = ?, position = ?, status = ? WHERE id = ? AND role = 'employee'");
        
        if (!$updateStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!$updateStmt->bind_param("ssssi", $full_name, $email, $department, $position, $status, $employee_id)) {
            throw new Exception('Bind failed: ' . $updateStmt->error);
        }
        
        if (!$updateStmt->execute()) {
            throw new Exception('Execute failed: ' . $updateStmt->error);
        }
        
        if ($updateStmt->affected_rows === 0) {
            $updateStmt->close();
            throw new Exception('Employee not found or no changes made');
        }
        
        $updateStmt->close();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        
        if ($employee_id === 0) {
            throw new Exception('Invalid employee ID');
        }
        
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
        
        if (!$deleteStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!$deleteStmt->bind_param("i", $employee_id)) {
            throw new Exception('Bind failed: ' . $deleteStmt->error);
        }
        
        if (!$deleteStmt->execute()) {
            throw new Exception('Execute failed: ' . $deleteStmt->error);
        }
        
        if ($deleteStmt->affected_rows === 0) {
            $deleteStmt->close();
            throw new Exception('Employee not found');
        }
        
        $deleteStmt->close();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
        
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action or method']);
    }
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$conn->close();
?>