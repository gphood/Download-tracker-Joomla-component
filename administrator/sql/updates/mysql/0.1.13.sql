ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `country_code` varchar(10) NULL AFTER `status`,
	ADD COLUMN `country_name` varchar(100) NULL AFTER `country_code`,
	ADD COLUMN `continent_code` varchar(10) NULL AFTER `country_name`,
	ADD COLUMN `continent_name` varchar(100) NULL AFTER `continent_code`,
	ADD COLUMN `asn` varchar(50) NULL AFTER `continent_name`,
	ADD COLUMN `asn_name` varchar(255) NULL AFTER `asn`,
	ADD COLUMN `asn_domain` varchar(255) NULL AFTER `asn_name`,
	ADD COLUMN `ip_location_provider` varchar(100) NULL AFTER `asn_domain`,
	ADD COLUMN `ip_location_checked_at` datetime NULL AFTER `ip_location_provider`,
	ADD COLUMN `ip_location_status` varchar(50) NULL AFTER `ip_location_checked_at`,
	ADD COLUMN `ip_location_response` mediumtext NULL AFTER `ip_location_status`,
	ADD KEY `idx_ip_location_checked_at` (`ip_location_checked_at`);
