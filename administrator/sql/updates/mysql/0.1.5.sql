ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `is_bot` tinyint NOT NULL DEFAULT 0 AFTER `user_agent`,
	ADD KEY `idx_is_bot` (`is_bot`);
