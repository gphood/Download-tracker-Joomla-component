ALTER TABLE `#__downloadtracker_items`
	ADD COLUMN `source_type` varchar(20) NOT NULL DEFAULT 'external' AFTER `version`,
	ADD COLUMN `private_file` varchar(1024) NULL AFTER `target_url`;

UPDATE `#__downloadtracker_items`
SET `source_type` = 'external'
WHERE `source_type` IS NULL
	OR `source_type` = '';
