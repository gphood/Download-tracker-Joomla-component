ALTER TABLE `#__downloadtracker_items`
	ADD COLUMN `requires_token` tinyint NOT NULL DEFAULT 0 AFTER `private_file`;

CREATE TABLE IF NOT EXISTS `#__downloadtracker_tokens` (
	`id` bigint NOT NULL AUTO_INCREMENT,
	`item_id` int NOT NULL,
	`label` varchar(255) NULL,
	`token_hash` char(64) NOT NULL,
	`token_prefix` varchar(16) NULL,
	`state` tinyint NOT NULL DEFAULT 1,
	`expires_at` datetime NULL,
	`max_uses` int unsigned NULL,
	`used_count` int unsigned NOT NULL DEFAULT 0,
	`customer_email` varchar(320) NULL,
	`note` text NULL,
	`last_used_at` datetime NULL,
	`created` datetime NULL,
	`created_by` int unsigned NOT NULL DEFAULT 0,
	`modified` datetime NULL,
	`modified_by` int unsigned NOT NULL DEFAULT 0,
	`checked_out` int unsigned NULL,
	`checked_out_time` datetime NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `idx_token_hash` (`token_hash`),
	KEY `idx_item_id` (`item_id`),
	KEY `idx_state` (`state`),
	KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__downloadtracker_logs`
	ADD COLUMN `token_id` bigint NULL AFTER `target_url`,
	ADD COLUMN `token_prefix` varchar(16) NULL AFTER `token_id`,
	ADD COLUMN `token_status` varchar(30) NULL AFTER `token_prefix`,
	ADD KEY `idx_token_id` (`token_id`),
	ADD KEY `idx_token_status` (`token_status`);
