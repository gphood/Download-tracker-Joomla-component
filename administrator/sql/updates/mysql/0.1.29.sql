ALTER TABLE `#__downloadtracker_tokens`
	ADD COLUMN `source` varchar(50) NULL AFTER `last_email_error`,
	ADD COLUMN `source_reference` varchar(255) NULL AFTER `source`,
	ADD KEY `idx_source_reference` (`source`, `source_reference`);
