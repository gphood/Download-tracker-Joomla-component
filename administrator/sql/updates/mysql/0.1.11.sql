ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `requested_url` text NULL AFTER `referrer`;
