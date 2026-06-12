-- Migration: update existing navigation labels from 'Mission' to 'Home'
-- Run this on the production database after deploying the code changes.

UPDATE navigation_items SET label = 'Home' WHERE source_type = 'system' AND system_key = 'mission';
