-- Parent School Interest table
-- This table tracks which schools parents are interested in before they're connected to specific students

USE schoolcomm;

CREATE TABLE IF NOT EXISTS parent_school_interest (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    school_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY (parent_id, school_id)
);