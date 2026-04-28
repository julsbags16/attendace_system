-- Create Database
CREATE DATABASE IF NOT EXISTS attendance_db;
USE attendance_db;

-- Users Table (Admin & Employees)
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin', 'employee') DEFAULT 'employee',
  department VARCHAR(100),
  position VARCHAR(100),
  phone VARCHAR(15),
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance Table
CREATE TABLE attendance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  login_time DATETIME,
  logout_time DATETIME,
  attendance_date DATE NOT NULL,
  status ENUM('present', 'absent', 'late', 'half-day', 'leave') DEFAULT 'present',
  hours_worked DECIMAL(5, 2),
  remarks VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_attendance (employee_id, attendance_date)
);

-- Leave Requests Table
CREATE TABLE leave_requests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  leave_type VARCHAR(50),
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(255),
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ML Predictions Table (for storing ML model predictions)
CREATE TABLE ml_predictions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  prediction_date DATE NOT NULL,
  predicted_attendance_rate DECIMAL(5, 2),
  risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
  recommendation TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_prediction (employee_id, prediction_date)
);

-- Attendance Statistics Table
CREATE TABLE attendance_statistics (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  total_present INT DEFAULT 0,
  total_absent INT DEFAULT 0,
  total_late INT DEFAULT 0,
  total_half_day INT DEFAULT 0,
  total_leave INT DEFAULT 0,
  average_hours_worked DECIMAL(5, 2),
  month_year VARCHAR(7),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_stats (employee_id, month_year)
);

-- Insert sample admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@company.com', '$2y$10$vkEjKRJBNaHDW8.ZZ2pB5uQjKFvfJ8s5EzKvWc8.fhS1k8N5T1XwO', 'Administrator', 'admin', 'active');

-- Insert sample employees
INSERT INTO users (username, email, password, full_name, role, department, position, status) VALUES
('john_doe', 'john@company.com', '$2y$10$vkEjKRJBNaHDW8.ZZ2pB5uQjKFvfJ8s5EzKvWc8.fhS1k8N5T1XwO', 'John Doe', 'employee', 'IT', 'Developer', 'active'),
('jane_smith', 'jane@company.com', '$2y$10$vkEjKRJBNaHDW8.ZZ2pB5uQjKFvfJ8s5EzKvWc8.fhS1k8N5T1XwO', 'Jane Smith', 'employee', 'HR', 'Manager', 'active'),
('mike_wilson', 'mike@company.com', '$2y$10$vkEjKRJBNaHDW8.ZZ2pB5uQjKFvfJ8s5EzKvWc8.fhS1k8N5T1XwO', 'Mike Wilson', 'employee', 'Finance', 'Accountant', 'active');