-- Live Server Database Update Script for the Yearly Active-Year System
-- Run this script on your live server to add the settings table.
-- This is purely additive: it does not modify or delete any existing rows.

-- Step 1: Create settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 2: Seed the active year (defaults to the current calendar year;
-- change it any time afterwards from the Settings page in the app)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('active_year', YEAR(CURDATE()));

-- Verification queries (run these to confirm the update worked)
-- SELECT * FROM settings;
-- SELECT COUNT(*) as settings_table_exists FROM information_schema.tables WHERE table_name = 'settings';

-- Success message
SELECT 'Yearly active-year system database update completed successfully!' as status;
