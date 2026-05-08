ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `requested_alias` varchar(255) NULL AFTER `downloaded_at`,
	ADD COLUMN `resolved_version` varchar(50) NULL AFTER `version`;
