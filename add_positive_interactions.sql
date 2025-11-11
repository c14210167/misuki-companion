-- Add positive_interactions column to users table
-- This tracks the number of positive interactions for trust level calculation
-- Run this migration once to update your existing database

ALTER TABLE users ADD COLUMN positive_interactions INT DEFAULT 0;

-- Update existing users to have 0 positive interactions if NULL
UPDATE users SET positive_interactions = 0 WHERE positive_interactions IS NULL;
