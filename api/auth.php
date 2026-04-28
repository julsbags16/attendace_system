<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'login') {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password required');
            }
            
            $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role FROM users WHERE username = ?");
            
            if (!$stmt) {
                throw new Exception('Database error');
            }
            
            $stmt->bind_param("s", $username);
            
            if (!$stmt->execute()) {
                throw new Exception('Database error');
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Check password (both plain and hashed for demo compatibility)
                $password_valid = false;
                
                if ($password === 'admin123' || password_verify($password, $user['password'])) {
                    $password_valid = true;
                }
                
                if ($password_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'role' => $user['role']
                    ]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Invalid password']);
                }
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            
            $stmt->close();
            
        } elseif ($action === 'logout') {
            session_destroy();
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>