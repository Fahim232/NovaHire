-- Migration: Merge 'javascript' grooming videos into 'Frontend' category
-- Run this SQL on your database to fix the category mismatch

UPDATE grooming_videos SET category = 'Frontend' WHERE category = 'javascript';

-- Also update any user_quiz_status records that reference 'javascript'
UPDATE user_quiz_status SET category = 'Frontend' WHERE category = 'javascript';

-- Verify the changes
SELECT category, COUNT(*) as video_count FROM grooming_videos GROUP BY category;
