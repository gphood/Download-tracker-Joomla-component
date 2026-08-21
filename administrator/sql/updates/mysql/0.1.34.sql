ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `bot_reason` varchar(100) NULL AFTER `is_bot`;
