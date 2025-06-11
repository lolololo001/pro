-- Add school_id column to parents table
ALTER TABLE parents ADD COLUMN school_id INT AFTER id;

-- Add foreign key constraint
ALTER TABLE parents ADD CONSTRAINT fk_parents_school_id FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- Update existing records (optional - set a default school if needed)
-- UPDATE parents SET school_id = 1 WHERE school_id IS NULL;