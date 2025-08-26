-- Live Server Database Update Script for Fund Transfer System
-- Run this script on your live server to add transfer functionality

-- Step 1: Update transactions table to include 'transfer' type
ALTER TABLE `transactions` MODIFY COLUMN `type` enum('collection','expense','transfer') NOT NULL;

-- Step 2: Create transfers table
CREATE TABLE IF NOT EXISTS `transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `transfer_date` date NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`),
  KEY `created_by` (`created_by`),
  INDEX `idx_transfer_date` (`transfer_date`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 3: Create additional indexes for better performance
-- Note: Run these individually and ignore "Duplicate key name" errors if they already exist
-- CREATE INDEX `idx_transfers_from_user_date` ON `transfers` (`from_user_id`, `transfer_date`);
-- CREATE INDEX `idx_transfers_to_user_date` ON `transfers` (`to_user_id`, `transfer_date`);
-- CREATE INDEX `idx_transfers_created_at` ON `transfers` (`created_at`);

-- Verification queries (run these to confirm the update worked)
-- SHOW COLUMNS FROM transactions WHERE Field = 'type';
-- DESCRIBE transfers;
-- SELECT COUNT(*) as transfer_table_exists FROM information_schema.tables WHERE table_name = 'transfers';

-- Success message
SELECT 'Fund Transfer System database update completed successfully!' as status;
