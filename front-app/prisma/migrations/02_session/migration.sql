CREATE TABLE `Session` (
  `id` VARCHAR(255) NOT NULL,
  `shop` TEXT NOT NULL,
  `state` TEXT NOT NULL,
  `isOnline` TINYINT(1) NOT NULL DEFAULT 0,
  `scope` TEXT,
  `expires` DATETIME NULL,
  `accessToken` TEXT NOT NULL,
  `userId` BIGINT,
  `firstName` TEXT,
  `lastName` TEXT,
  `email` TEXT,
  `accountOwner` TINYINT(1) NOT NULL DEFAULT 0,
  `locale` TEXT,
  `collaborator` TINYINT(1) DEFAULT 0,
  `emailVerified` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
