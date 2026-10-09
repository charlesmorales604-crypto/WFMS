CREATE DATABASE IF NOT EXISTS user
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE user;

-- =========================
-- EMPLOYEES
-- =========================

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(30) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    address VARCHAR(255) NULL,
    position VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    employment_status ENUM('Regular','Probationary','Contractual','Part-time','Inactive') NOT NULL DEFAULT 'Regular',
    date_hired DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- USERS / LOGIN
-- =========================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('employee','admin') NOT NULL DEFAULT 'employee',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- ATTENDANCE
-- =========================
CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    ot_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    total_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Present','Absent','Late','On Leave','AWOL','Half Day') NOT NULL DEFAULT 'Present',
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_attendance_date (employee_id, attendance_date),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_attendance_status (status),
    CONSTRAINT fk_attendance_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- SHIFT / SCHEDULE REQUESTS
-- =========================
CREATE TABLE IF NOT EXISTS shift_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    current_shift ENUM('morning','afternoon','night') NOT NULL,
    requested_shift ENUM('morning','afternoon','night') NOT NULL,
    current_schedule VARCHAR(100) NOT NULL DEFAULT 'Monday - Friday',
    requested_schedule VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    until_date DATE NULL,
    reason TEXT NULL,
    status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shift_employee (employee_id),
    INDEX idx_shift_status (status),
    CONSTRAINT fk_shift_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shift_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- LEAVE REQUESTS
-- =========================
CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    leave_type ENUM('vacation','sick','emergency','personal','other') NOT NULL,
    leave_duration ENUM('whole-day','half-day') NOT NULL,
    date_from DATE NOT NULL,
    date_until DATE NOT NULL,
    reason TEXT NULL,
    status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leave_employee (employee_id),
    INDEX idx_leave_status (status),
    INDEX idx_leave_dates (date_from, date_until),
    CONSTRAINT fk_leave_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_leave_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- WORK SCHEDULE
-- =========================
CREATE TABLE IF NOT EXISTS work_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    shift ENUM('morning','afternoon','night') NOT NULL DEFAULT 'morning',
    schedule_days VARCHAR(100) NOT NULL DEFAULT 'Monday - Friday',
    start_time TIME NOT NULL DEFAULT '08:00:00',
    end_time TIME NOT NULL DEFAULT '17:00:00',
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- REQUEST HISTORY VIEW
-- =========================
CREATE OR REPLACE VIEW request_history AS
SELECT
    sr.id AS request_id,
    sr.employee_id,
    e.employee_id AS employee_number,
    e.full_name,
    'Shift Request' AS request_type,
    sr.created_at AS request_date,
    CONCAT(sr.current_shift, ' -> ', sr.requested_shift,
           ' | ', sr.requested_schedule) AS details,
    sr.reason,
    sr.status
FROM shift_requests sr
JOIN employees e ON e.id = sr.employee_id

UNION ALL

SELECT
    lr.id AS request_id,
    lr.employee_id,
    e.employee_id AS employee_number,
    e.full_name,
    'Leave Request' AS request_type,
    lr.created_at AS request_date,
    CONCAT(lr.leave_type, ' | ', lr.date_from, ' -> ', lr.date_until) AS details,
    lr.reason,
    lr.status
FROM leave_requests lr
JOIN employees e ON e.id = lr.employee_id;

-- =========================
-- DASHBOARD SUMMARY VIEW
-- =========================
CREATE OR REPLACE VIEW attendance_summary AS
SELECT
    employee_id,
    SUM(status = 'Present') AS present_count,
    SUM(status = 'Absent') AS absent_count,
    SUM(status = 'Late') AS late_count
FROM attendance
GROUP BY employee_id;


-- =========================
-- PAYSLIPS
-- =========================
CREATE TABLE IF NOT EXISTS payslips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    payroll_period_start DATE NOT NULL,
    payroll_period_end DATE NOT NULL,
    basic_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    leave_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    holiday_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sss DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pagibig DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    philhealth DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payslip_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_payslip_employee_period (employee_id, payroll_period_start, payroll_period_end)
) ENGINE=InnoDB;
