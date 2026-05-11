ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `ip_classification` varchar(50) NULL AFTER `status`;

UPDATE `#__downloadtracker_logs`
SET
	`ip_classification` = CASE
		WHEN `ip_address` IN ('127.0.0.1', '::1') THEN 'localhost'
		WHEN `ip_address` REGEXP '^172\\.(1[6-9]|2[0-9]|3[0-1])\\.' THEN 'docker_network'
		WHEN `ip_address` REGEXP '^(10\\.|192\\.168\\.)' THEN 'private_network'
		ELSE 'reserved'
	END,
	`ip_location_status` = CASE
		WHEN `ip_address` IN ('127.0.0.1', '::1') THEN 'skipped_localhost'
		WHEN `ip_address` REGEXP '^172\\.(1[6-9]|2[0-9]|3[0-1])\\.' THEN 'skipped_docker_network'
		WHEN `ip_address` REGEXP '^(10\\.|192\\.168\\.)' THEN 'skipped_private_network'
		ELSE 'skipped_reserved'
	END
WHERE `ip_classification` IS NULL
	AND `ip_location_status` = 'skipped_private_ip';
