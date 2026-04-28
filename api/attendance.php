<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($action === 'login') {
    // Employee login (mark attendance)
    $today = date('Y-m-d');
    
    // Check if already logged in today
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already logged in today']);
    } else {
        $login_time = date('Y-m-d H:i:s');
        $status = 'present';
        
        // Check if login is after 9 AM (late)
        $login_hour = intval(date('H', strtotime($login_time)));
        if ($login_hour > 9) {
            $status = 'late';
        }
        
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, login_time, attendance_date, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $login_time, $today, $status);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'status' => $status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to log in']);
        }
    }
    
} elseif ($action === 'logout') {
    // Employee logout
    $today = date('Y-m-d');
    $logout_time = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT id, login_time FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        $login_time = strtotime($record['login_time']);
        $logout_time_unix = strtotime($logout_time);
        $hours_worked = round(($logout_time_unix - $login_time) / 3600, 2);
        
        $stmt = $conn->prepare("UPDATE attendance SET logout_time = ?, hours_worked = ? WHERE id = ?");
        $stmt->bind_param("sii", $logout_time, $hours_worked, $record['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Logged out successfully', 'hours_worked' => $hours_worked]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to log out']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No login record found']);
    }
    
} elseif ($action === 'getEmployeeAttendance') {
    // Get employee's attendance records
    $month = $_GET['month'] ?? date('Y-m');
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? ORDER BY attendance_date DESC");
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $records]);
    
} elseif ($action === 'getAllAttendance' && $user_role === 'admin') {
    // Admin: Get all attendance records
    $stmt = $conn->prepare("SELECT a.*, u.full_name, u.department FROM attendance a JOIN users u ON a.employee_id = u.id ORDER BY a.attendance_date DESC LIMIT 500");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $records]);
    
} elseif ($action === 'updateAttendance' && $user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin: Update attendance record
    $attendance_id = $_POST['attendance_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $stmt = $conn->prepare("UPDATE attendance SET status = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $remarks, $attendance_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
}
?>