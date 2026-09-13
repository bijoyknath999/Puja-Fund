-- Live Server Database Update Script for the Phase 2 REST API
-- Run this script on your live server to add token storage for the JSON API
-- (used by the Flutter mobile app). This is purely additive: it does not
-- modify or delete any existing rows.

-- Step 1: Create api_tokens table
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `token` varchar(64) NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verification queries (run these to confirm the update worked)
-- DESCRIBE api_tokens;
-- SELECT COUNT(*) as api_tokens_table_exists FROM information_schema.tables WHERE table_name = 'api_tokens';

-- Success message
SELECT 'Phase 2 REST API database update completed successfully!' as status;
