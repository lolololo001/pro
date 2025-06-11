-- Departments table for SchoolComm

USE schoolcomm;

-- Create departments table
CREATE TABLE IF NOT EXISTS departments (
    dep_id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    department_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Add index for faster lookups
CREATE INDEX idx_department_school ON departments(school_id);

-- Add unique constraint to prevent duplicate department names within a school
ALTER TABLE departments ADD CONSTRAINT unique_department_per_school UNIQUE (school_id, department_name);