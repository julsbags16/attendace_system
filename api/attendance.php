<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/db.php';

if ($conn->connect_error) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? '';
    
    if ($action === 'login') {
        // Employee login (mark attendance)
        $today = date('Y-m-d');
        
        // Check if already logged in today
        $check_query = "SELECT id, login_time, logout_time FROM attendance 
                       WHERE employee_id = $user_id AND attendance_date = '$today' LIMIT 1";
        $check_result = $conn->query($check_query);
        
        if (!$check_result) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        if ($check_result->num_rows > 0) {
            $record = $check_result->fetch_assoc();
            if ($record['login_time'] && !$record['logout_time']) {
                throw new Exception('Already logged in today');
            }
        }
        
        $login_time = date('Y-m-d H:i:s');
        $status = 'present';
        
        // Check if late (after 9 AM)
        $login_hour = intval(date('H'));
        if ($login_hour > 9) {
            $status = 'late';
        }
        
        $insert_query = "INSERT INTO attendance (employee_id, login_time, attendance_date, status) 
                        VALUES ($user_id, '$login_time', '$today', '$status')";
        
        if (!$conn->query($insert_query)) {
            throw new Exception('Failed to log in: ' . $conn->error);
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Logged in successfully',
            'status' => $status
        ]);
        exit;
        
    } elseif ($action === 'logout') {
        // Employee logout
        $today = date('Y-m-d');
        $logout_time = date('Y-m-d H:i:s');
        
        $select_query = "SELECT id, login_time FROM attendance 
                        WHERE employee_id = $user_id AND attendance_date = '$today' LIMIT 1";
        $select_result = $conn->query($select_query);
        
        if (!$select_result || $select_result->num_rows === 0) {
            throw new Exception('No login record found');
        }
        
        $record = $select_result->fetch_assoc();
        $login_time = strtotime($record['login_time']);
        $logout_time_unix = strtotime($logout_time);
        $hours_worked = round(($logout_time_unix - $login_time) / 3600, 2);
        
        $update_query = "UPDATE attendance SET logout_time = '$logout_time', hours_worked = $hours_worked 
                        WHERE id = " . $record['id'];
        
        if (!$conn->query($update_query)) {
            throw new Exception('Failed to log out: ' . $conn->error);
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully',
            'hours_worked' => $hours_worked
        ]);
        exit;
        
    } elseif ($action === 'getEmployeeAttendance') {
        // Get employee's attendance records
        $month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
        
        $query = "SELECT * FROM attendance 
                 WHERE employee_id = $user_id AND DATE_FORMAT(attendance_date, '%Y-%m') = '$month' 
                 ORDER BY attendance_date DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $records]);
        exit;
        
    } elseif ($action === 'getAllAttendance') {
        // Admin: Get all attendance records
        $query = "SELECT a.*, u.full_name, u.department 
                 FROM attendance a 
                 JOIN users u ON a.employee_id = u.id 
                 ORDER BY a.attendance_date DESC LIMIT 1000";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $records]);
        exit;
        
    } elseif ($action === 'updateAttendance' && $_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'admin') {
        // Admin: Update attendance record
        $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        
        if ($attendance_id <= 0 || empty($status)) {
            throw new Exception('Invalid input');
        }
        
        $remarks = $conn->real_escape_string($remarks);
        
        $update_query = "UPDATE attendance SET status = '$status', remarks = '$remarks' WHERE id = $attendance_id";
        
        if (!$conn->query($update_query)) {
            throw new Exception('Update failed: ' . $conn->error);
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
        exit;
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

$conn->close();
?>
