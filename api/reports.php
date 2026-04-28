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
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized: Admin access required');
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'monthlyReport') {
        $month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
        
        $query = "SELECT 
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
        LEFT JOIN attendance a ON u.id = a.employee_id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = '$month'
        WHERE u.role = 'employee'
        GROUP BY u.id
        ORDER BY u.full_name";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'month' => $month, 'data' => $report]);
        exit;
        
    } elseif ($action === 'departmentReport') {
        $query = "SELECT 
            u.department,
            COUNT(u.id) as total_employees,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_presents,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absents,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_rate
        FROM users u
        LEFT JOIN attendance a ON u.id = a.employee_id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = '" . date('Y-m') . "'
        WHERE u.role = 'employee'
        GROUP BY u.department
        ORDER BY u.department";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $report]);
        exit;
        
    } elseif ($action === 'riskAnalysis') {
        $today = date('Y-m-d');
        
        $query = "SELECT p.*, u.full_name, u.department 
                 FROM ml_predictions p 
                 JOIN users u ON p.employee_id = u.id 
                 WHERE p.prediction_date = '$today' 
                 ORDER BY CASE WHEN p.risk_level = 'high' THEN 1 WHEN p.risk_level = 'medium' THEN 2 ELSE 3 END";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $analysis = [];
        while ($row = $result->fetch_assoc()) {
            $analysis[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $analysis]);
        exit;
        
    } elseif ($action === 'dailyTrend') {
        $month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
        
        $query = "SELECT 
            a.attendance_date,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
            COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_count
        FROM attendance a
        WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '$month'
        GROUP BY a.attendance_date
        ORDER BY a.attendance_date";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $trend = [];
        while ($row = $result->fetch_assoc()) {
            $trend[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $trend]);
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
