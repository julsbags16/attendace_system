<?php
// Start output buffering and set headers first
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session
session_start();

// Database connection
require_once '../config/db.php';

// Verify connection
if ($conn->connect_error) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Not authenticated');
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        throw new Exception('Admin access required');
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    if ($action === 'getAll') {
        // Get all employees
        $query = "SELECT id, username, email, full_name, department, position, status, created_at 
                  FROM users 
                  WHERE role = 'employee' 
                  ORDER BY full_name ASC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'data' => $employees,
            'count' => count($employees)
        ]);
        exit;
        
    } elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate inputs
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        
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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if username or email exists
        $check_query = "SELECT id FROM users WHERE username = '" . $conn->real_escape_string($username) . "' 
                       OR email = '" . $conn->real_escape_string($email) . "'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            throw new Exception('Username or email already exists');
        }
        
        // Hash password
        $default_password = 'Welcome@123';
        $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);
        
        // Escape strings
        $username = $conn->real_escape_string($username);
        $email = $conn->real_escape_string($email);
        $full_name = $conn->real_escape_string($full_name);
        $department = $conn->real_escape_string($department);
        $position = $conn->real_escape_string($position);
        $hashed_password = $conn->real_escape_string($hashed_password);
        
        // Insert
        $insert_query = "INSERT INTO users (username, email, password, full_name, department, position, role, status) 
                        VALUES ('$username', '$email', '$hashed_password', '$full_name', '$department', '$position', 'employee', '$status')";
        
        if (!$conn->query($insert_query)) {
            throw new Exception('Insert failed: ' . $conn->error);
        }
        
        $new_id = $conn->insert_id;
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Employee created successfully',
            'employee_id' => $new_id,
            'default_password' => $default_password
        ]);
        exit;
        
    } elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        
        if ($employee_id <= 0) {
            throw new Exception('Invalid employee ID');
        }
        
        // Escape strings
        $full_name = $conn->real_escape_string($full_name);
        $email = $conn->real_escape_string($email);
        $department = $conn->real_escape_string($department);
        $position = $conn->real_escape_string($position);
        $status = $conn->real_escape_string($status);
        
        $update_query = "UPDATE users SET full_name = '$full_name', email = '$email', department = '$department', 
                        position = '$position', status = '$status' 
                        WHERE id = $employee_id AND role = 'employee'";
        
        if (!$conn->query($update_query)) {
            throw new Exception('Update failed: ' . $conn->error);
        }
        
        if ($conn->affected_rows === 0) {
            throw new Exception('Employee not found or no changes made');
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        exit;
        
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        
        if ($employee_id <= 0) {
            throw new Exception('Invalid employee ID');
        }
        
        // Delete from attendance first (foreign key constraint)
        $delete_attendance = "DELETE FROM attendance WHERE employee_id = $employee_id";
        $conn->query($delete_attendance);
        
        // Delete from ml_predictions
        $delete_predictions = "DELETE FROM ml_predictions WHERE employee_id = $employee_id";
        $conn->query($delete_predictions);
        
        // Delete from leave_requests
        $delete_leaves = "DELETE FROM leave_requests WHERE employee_id = $employee_id";
        $conn->query($delete_leaves);
        
        // Delete from attendance_statistics
        $delete_stats = "DELETE FROM attendance_statistics WHERE employee_id = $employee_id";
        $conn->query($delete_stats);
        
        // Finally delete the user
        $delete_query = "DELETE FROM users WHERE id = $employee_id AND role = 'employee'";
        
        if (!$conn->query($delete_query)) {
            throw new Exception('Delete failed: ' . $conn->error);
        }
        
        if ($conn->affected_rows === 0) {
            throw new Exception('Employee not found');
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
        exit;
        
    } else {
        throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
    exit;
}

$conn->close();
?>
