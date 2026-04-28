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
    
    $user_id = $_SESSION['user_id'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Simple ML Model: Predict attendance based on historical data
    function calculateAttendanceStats($employee_id, $conn) {
        $last_30_days = date('Y-m-d', strtotime('-30 days'));
        
        $query = "SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
            COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count,
            AVG(hours_worked) as avg_hours
            FROM attendance 
            WHERE employee_id = $employee_id AND attendance_date >= '$last_30_days'";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        return $result->fetch_assoc();
    }
    
    function generatePrediction($employee_id, $conn) {
        $stats = calculateAttendanceStats($employee_id, $conn);
        
        $total_days = $stats['present_count'] + $stats['absent_count'] + $stats['late_count'];
        $attendance_rate = $total_days > 0 ? ($stats['present_count'] / $total_days) * 100 : 0;
        
        // Determine risk level
        if ($attendance_rate >= 90) {
            $risk_level = 'low';
            $recommendation = 'Excellent attendance! Keep maintaining this consistency.';
        } elseif ($attendance_rate >= 75) {
            $risk_level = 'medium';
            $recommendation = 'Your attendance is good but could be improved. Try to arrive on time more frequently.';
        } else {
            $risk_level = 'high';
            $recommendation = 'Your attendance needs immediate attention. Please improve punctuality and reduce absences.';
        }
        
        // Add personalized tips
        if ($stats['late_count'] > 5) {
            $recommendation .= ' You have been late frequently - consider leaving earlier to avoid delays.';
        }
        
        if ($stats['absent_count'] > 3) {
            $recommendation .= ' High absence rate detected - please contact HR if there are issues.';
        }
        
        if ($stats['avg_hours'] && $stats['avg_hours'] < 8) {
            $recommendation .= ' Average working hours are below 8 - ensure you complete full working hours.';
        }
        
        return [
            'attendance_rate' => round($attendance_rate, 2),
            'risk_level' => $risk_level,
            'recommendation' => $recommendation,
            'stats' => $stats
        ];
    }
    
    if ($action === 'getPrediction') {
        $target_user = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : $user_id;
        
        // Employees can only see their own predictions
        if ($_SESSION['role'] === 'employee' && $target_user != $user_id) {
            throw new Exception('Unauthorized');
        }
        
        $prediction = generatePrediction($target_user, $conn);
        
        // Save prediction to database
        $today = date('Y-m-d');
        $rate = $prediction['attendance_rate'];
        $risk = $prediction['risk_level'];
        $rec = $conn->real_escape_string($prediction['recommendation']);
        
        $insert_query = "INSERT INTO ml_predictions (employee_id, prediction_date, predicted_attendance_rate, risk_level, recommendation) 
        VALUES ($target_user, '$today', $rate, '$risk', '$rec') 
        ON DUPLICATE KEY UPDATE 
        predicted_attendance_rate = VALUES(predicted_attendance_rate), 
        risk_level = VALUES(risk_level), 
        recommendation = VALUES(recommendation)";
        
        $conn->query($insert_query);
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $prediction]);
        exit;
        
    } elseif ($action === 'getAllPredictions' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
        
        $predictions = [];
        while ($row = $result->fetch_assoc()) {
            $predictions[] = $row;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $predictions]);
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
