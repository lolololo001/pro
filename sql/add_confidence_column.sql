-- Add confidence_score column to parent_feedback table
-- Run this script if you want to store confidence scores in the database

ALTER TABLE parent_feedback 
ADD COLUMN confidence_score DECIMAL(3,2) DEFAULT 0.5 
AFTER sentiment_label;

-- Add index for better performance
CREATE INDEX idx_sentiment_confidence ON parent_feedback(sentiment_label, confidence_score);

-- Update existing records with default confidence score
UPDATE parent_feedback 
SET confidence_score = 0.5 
WHERE confidence_score IS NULL; 