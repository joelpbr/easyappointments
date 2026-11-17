<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_staff_attendance extends EA_Migration
{
    public function up(): void
    {
        // No cascading user FK: legally retained records survive deletion of a login.
        $tables = [
            'attendance_profiles' =>
                'employee_id INT PRIMARY KEY, employee_name VARCHAR(512) NOT NULL, contract_type VARCHAR(16) NOT NULL DEFAULT "unknown", weekly_minutes INT NULL, agreement VARCHAR(512) NOT NULL DEFAULT "", pause_policy VARCHAR(16) NOT NULL DEFAULT "unknown", updated_at DATETIME NOT NULL',
            'attendance_entries' =>
                'id BIGINT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, employee_name VARCHAR(512) NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NULL, timezone VARCHAR(64) NOT NULL, segments MEDIUMTEXT NOT NULL, policy_snapshot TEXT NOT NULL, revision INT NOT NULL DEFAULT 1, voided TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, KEY attendance_employee (employee_id, start_at), KEY attendance_open (employee_id, end_at)',
            'attendance_journal' =>
                'id BIGINT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, entry_id BIGINT NULL, actor_id INT NOT NULL, actor_name VARCHAR(512) NOT NULL, action VARCHAR(32) NOT NULL, reason TEXT NOT NULL, before_json MEDIUMTEXT NULL, after_json MEDIUMTEXT NOT NULL, request_key VARCHAR(64) NULL, created_at DATETIME NOT NULL, UNIQUE KEY attendance_request (actor_id, request_key), KEY attendance_history (employee_id, id)',
            'attendance_requests' =>
                'id BIGINT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, employee_name VARCHAR(512) NOT NULL, work_date DATE NOT NULL, message TEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "pending", resolution TEXT NULL, resolved_by INT NULL, resolved_at DATETIME NULL, created_at DATETIME NOT NULL, KEY attendance_pending (employee_id, status)',
            'attendance_reports' =>
                'id BIGINT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, employee_name VARCHAR(512) NOT NULL, month CHAR(7) NOT NULL, snapshot MEDIUMTEXT NOT NULL, issued_by INT NOT NULL, issued_at DATETIME NOT NULL, delivered_at DATETIME NULL, delivery_note TEXT NULL, KEY attendance_month (employee_id, month)',
        ];
        foreach ($tables as $name => $fields) {
            $table = $this->db->dbprefix($name);
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `$table` ($fields) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Attendance records must be retained. This migration cannot delete them.');
    }
}
