ALTER TABLE `#__downloadtracker_items`
	ADD COLUMN `customer_instructions` text NULL AFTER `requires_token`,
	ADD COLUMN `update_enabled` tinyint NOT NULL DEFAULT 0 AFTER `customer_instructions`,
	ADD COLUMN `update_element` varchar(255) NULL AFTER `update_enabled`,
	ADD COLUMN `update_type` varchar(20) NULL AFTER `update_element`,
	ADD COLUMN `update_folder` varchar(100) NULL AFTER `update_type`,
	ADD COLUMN `update_client` varchar(20) NOT NULL DEFAULT 'site' AFTER `update_folder`,
	ADD COLUMN `update_sha256` char(64) NULL AFTER `update_client`,
	ADD COLUMN `update_targetplatform` varchar(100) NULL AFTER `update_sha256`,
	ADD COLUMN `update_php_minimum` varchar(20) NULL AFTER `update_targetplatform`;

ALTER TABLE `#__downloadtracker_tokens`
	ADD COLUMN `purpose` varchar(20) NOT NULL DEFAULT 'download' AFTER `token_prefix`,
	ADD KEY `idx_purpose` (`purpose`);

UPDATE `#__downloadtracker_logs`
SET `requested_url` = CONCAT(SUBSTRING_INDEX(`requested_url`, 'token=', 1), 'token=[redacted]')
WHERE `requested_url` LIKE '%token=%';

UPDATE `#__downloadtracker_logs`
SET `referrer` = CONCAT(SUBSTRING_INDEX(`referrer`, 'token=', 1), 'token=[redacted]')
WHERE `referrer` LIKE '%token=%';
