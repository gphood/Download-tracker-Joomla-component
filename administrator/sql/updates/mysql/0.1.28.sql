ALTER TABLE `#__downloadtracker_tokens`
	ADD COLUMN `emailed_at` datetime NULL AFTER `last_used_at`,
	ADD COLUMN `emailed_to` varchar(320) NULL AFTER `emailed_at`,
	ADD COLUMN `email_count` int unsigned NOT NULL DEFAULT 0 AFTER `emailed_to`,
	ADD COLUMN `last_email_status` varchar(50) NULL AFTER `email_count`,
	ADD COLUMN `last_email_error` text NULL AFTER `last_email_status`;
