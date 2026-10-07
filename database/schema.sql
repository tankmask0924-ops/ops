CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '姓名',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '管理员：能看到全部平台，能管理平台和账号',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 启用 0 禁用',
  `login_failures` int unsigned NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  `locked_until` datetime NULL DEFAULT NULL COMMENT '登录锁定到期时间',
  `password_changed_at` datetime NULL DEFAULT NULL COMMENT '在此之前签发的登录令牌全部失效',
  `last_login_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台账号';

CREATE TABLE IF NOT EXISTS `platforms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL COMMENT '标题',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类，空表示未分类',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '网址',
  `account` varchar(191) NOT NULL DEFAULT '' COMMENT '登录账号',
  `password_encrypted` text NOT NULL COMMENT '登录密码，AES-256-GCM 加密，密钥是 .env 的 PASSWORD_KEY',
  `description` varchar(1000) NOT NULL DEFAULT '' COMMENT '网站描述',
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `updated_by` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_title` (`title`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台';

CREATE TABLE IF NOT EXISTS `user_platforms` (
  `user_id` int unsigned NOT NULL,
  `platform_id` int unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `platform_id`),
  KEY `idx_platform_id` (`platform_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='普通账号能看到哪些平台';

CREATE TABLE IF NOT EXISTS `password_view_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `platform_id` int unsigned NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_platform_id` (`platform_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='查看平台密码的记录';
