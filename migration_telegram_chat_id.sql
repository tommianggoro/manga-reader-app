-- Migration: Add telegram_chat_id to users table + notification tracking
-- Run this on existing production databases
-- NOTE: If telegram_chat_id column already exists (previous migration applied),
-- run the second ALTER separately to avoid "Duplicate column" error.

ALTER TABLE `users`
ADD COLUMN `telegram_chat_id` varchar(64) DEFAULT NULL AFTER `profile_photo_url`,
ADD INDEX `idx_telegram_chat_id` (`telegram_chat_id`);

-- Add notification tracking column to user_manga_state
-- This is SEPARATE from last_read_chapter_number (which is used for "Continue Reading" button)
ALTER TABLE `user_manga_state`
ADD COLUMN `last_notified_chapter_number` decimal(8,2) DEFAULT NULL AFTER `last_read_chapter_number`;