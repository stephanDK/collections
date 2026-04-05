-- ============================================================
-- Migration: Add guest user support
-- Run this ONCE in phpMyAdmin on your existing database
-- ============================================================
ALTER TABLE users ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;
