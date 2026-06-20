-- Run this once against your existing database to add the missing
-- `role` column (needed for the User/Admin login feature).
-- Safe to run even if some users already exist — defaults them to 'user'.

ALTER TABLE `users`
    ADD COLUMN `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER `password_hash`;
