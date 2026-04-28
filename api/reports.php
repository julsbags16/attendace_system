<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'monthlyReport') {
    $month = $_GET['month'] ?? date('Y-m');
    
    $stmt = $conn->prepare("SELECT 
        u.id,
        u.full_name,
        u.department,
        u.position,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_count,
        ROUND(AVG(a.hours_worked), 2) as avg_hours,
        ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_percentage
    FROM users u
    LEFT JOIN attendance a ON u.id = a.employee_id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
    WHERE u.role = 'employee'
    GROUP BY u.id
    ORDER BY u.full_name");
    
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report = [];
    while ($row = $result->fetch_assoc()) {
        $report[] = $row;
    }
    
    echo json_encode(['success' => true, 'month' => $month, 'data' => $report]);
    
} elseif ($action === 'departmentReport') {
    $stmt = $conn->prepare("SELECT 
        u.department,
        COUNT(u.id) as total_employees,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_presents,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absents,
        ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_rate
    FROM users u
    LEFT JOIN attendance a ON u.id = a.employee_id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
    WHERE u.role = 'employee'
    GROUP BY u.department
    ORDER BY u.department");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report = [];
    while ($row = $result->fetch_assoc()) {
        $report[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $report]);
    
} elseif ($action === 'riskAnalysis') {
    $stmt = $conn->prepare("SELECT p.*, u.full_name, u.department FROM ml_predictions p 
    JOIN users u ON p.employee_id = u.id 
    WHERE p.prediction_date = CURDATE() 
    ORDER BY CASE WHEN p.risk_level = 'high' THEN 1 WHEN p.risk_level = 'medium' THEN 2 ELSE 3 END");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $analysis = [];
    while ($row = $result->fetch_assoc()) {
        $analysis[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $analysis]);
    
} elseif ($action === 'dailyTrend') {
    $month = $_GET['month'] ?? date('Y-m');
    
    $stmt = $conn->prepare("SELECT 
        a.attendance_date,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_count
    FROM attendance a
    WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
    GROUP BY a.attendance_date
    ORDER BY a.attendance_date");
    
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trend = [];
    while ($row = $result->fetch_assoc()) {
        $trend[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $trend]);
    
} elseif ($action === 'todaySnapshot') {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave
    FROM attendance 
    WHERE attendance_date = ?");
    
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $snapshot = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'data' => $snapshot, 'date' => $today]);
}
?>