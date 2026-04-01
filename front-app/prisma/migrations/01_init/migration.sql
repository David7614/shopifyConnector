-- CreateTable
CREATE TABLE `accesstokens` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `id_user` INTEGER NULL,
    `access_token` TEXT NULL,
    `refresh_token` TEXT NULL,
    `expiry` INTEGER NULL,
    `scope` VARCHAR(255) NULL,
    `state` TEXT NULL,

    INDEX `idx-user_config-id_user`(`id_user`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `customers` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` VARCHAR(85) NULL,
    `email` VARCHAR(255) NOT NULL,
    `login` VARCHAR(255) NULL,
    `registration` DATETIME(0) NOT NULL,
    `first_name` VARCHAR(255) NOT NULL DEFAULT '',
    `lastname` VARCHAR(255) NOT NULL DEFAULT '',
    `zip_code` VARCHAR(55) NOT NULL DEFAULT '',
    `phone` VARCHAR(55) NULL DEFAULT '',
    `newsletter_frequency` VARCHAR(55) NULL,
    `sms_frequency` VARCHAR(55) NULL,
    `nlf_time` DATETIME(0) NULL,
    `data_permission` VARCHAR(55) NULL,
    `tags` TEXT NULL,
    `server_response` TEXT NULL,
    `error` TEXT NULL,
    `data_hash` VARCHAR(255) NULL,
    `last_modification_date` DATETIME(0) NULL,
    `user_id` INTEGER NOT NULL,
    `page` INTEGER NOT NULL,
    `parameters` TEXT NOT NULL,
    `is_wholesaler` INTEGER NOT NULL DEFAULT 0,
    `is_disabled` INTEGER NOT NULL DEFAULT 0,
    `country` VARCHAR(25) NOT NULL DEFAULT '',
    `updated` TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
    `verify_email` BOOLEAN NULL,
    `shop_id` BOOLEAN NULL,
    `crm_verified` BOOLEAN NOT NULL DEFAULT false,

    INDEX `customer_id`(`customer_id`),
    INDEX `email`(`email`),
    INDEX `user_id`(`user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `disabled_feeds` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `user_id` INTEGER NOT NULL,
    `integration_type` VARCHAR(255) NOT NULL,

    INDEX `user_id`(`user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `idosel_subscriptions` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NULL,
    `customer_login` VARCHAR(255) NULL,
    `customer_email` VARCHAR(255) NULL,
    `customer_phone` VARCHAR(20) NULL,
    `newsletter_approval` INTEGER NOT NULL DEFAULT 0,
    `sms_approval` INTEGER NOT NULL DEFAULT 0,
    `date_modification` DATETIME(0) NULL,
    `shop_id` INTEGER NOT NULL,
    `user_id` INTEGER NOT NULL,
    `sync_flag` INTEGER NOT NULL DEFAULT 0,

    INDEX `customer_email`(`customer_email`),
    INDEX `customer_id`(`customer_id`),
    INDEX `user_id`(`user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `integration_data` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NOT NULL,
    `task` VARCHAR(255) NOT NULL,
    `value` VARCHAR(255) NOT NULL,

    INDEX `customer_id`(`customer_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `magazines` (
    `id` INTEGER NOT NULL,
    `location_id` INTEGER NOT NULL,
    `parent_id` INTEGER NOT NULL,
    `location_name` VARCHAR(255) NOT NULL,
    `location_path` VARCHAR(255) NOT NULL,
    `location_code` VARCHAR(255) NOT NULL,
    `stock_id` INTEGER NOT NULL,
    `user_id` INTEGER NOT NULL,

    INDEX `user_id`(`user_id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `orders` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` VARCHAR(255) NOT NULL,
    `customer_id` INTEGER NULL,
    `created_on` DATETIME(0) NOT NULL,
    `finished_on` DATETIME(0) NULL,
    `status` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(55) NULL,
    `zip_code` VARCHAR(55) NULL,
    `country_code` VARCHAR(55) NULL,
    `user_id` INTEGER NOT NULL,
    `page` INTEGER NOT NULL,
    `order_positions` TEXT NOT NULL,
    `updated` TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),

    INDEX `customer_id`(`customer_id`),
    INDEX `user_id`(`user_id`),
    UNIQUE INDEX `order_id_user_id`(`order_id`, `user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ordersv2` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `customer_id` INTEGER NULL,
    `created_on` DATETIME(0) NOT NULL,
    `finished_on` DATETIME(0) NULL,
    `status` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(55) NULL,
    `zip_code` VARCHAR(55) NULL,
    `country_code` VARCHAR(55) NULL,
    `user_id` INTEGER NOT NULL,
    `page` INTEGER NOT NULL,
    `order_positions` TEXT NOT NULL,
    `updated` TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),

    INDEX `customer_id`(`customer_id`),
    INDEX `order_id`(`order_id`),
    INDEX `user_id`(`user_id`),
    UNIQUE INDEX `order_id_user_id`(`order_id`, `user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `positions` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `amount` INTEGER NOT NULL,
    `price` VARCHAR(255) NOT NULL,
    `order_id` INTEGER NOT NULL,

    INDEX `order_id`(`order_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `product` (
    `ID` INTEGER NOT NULL AUTO_INCREMENT,
    `PRODUCT_ID` INTEGER NOT NULL,
    `URL` VARCHAR(550) NOT NULL,
    `TITLE` VARCHAR(250) NOT NULL,
    `PRICE` DOUBLE NOT NULL,
    `BRAND` VARCHAR(250) NOT NULL,
    `DESCRIPTION` TEXT NOT NULL,
    `PRICE_BEFORE_DISCOUNT` DOUBLE NOT NULL DEFAULT 0,
    `PRICE_WHOLESALE` DOUBLE NOT NULL DEFAULT 0,
    `PRICE_BUY` DOUBLE NOT NULL DEFAULT 0,
    `IMAGE` VARCHAR(250) NOT NULL DEFAULT '',
    `PRODUCT_LINE` VARCHAR(250) NOT NULL,
    `CATEGORYTEXT` TEXT NOT NULL,
    `SHOW` VARCHAR(55) NOT NULL,
    `PARAMETERS` LONGTEXT NOT NULL,
    `VARIANT` LONGTEXT NOT NULL,
    `PRICES` TEXT NOT NULL,
    `STOCK` INTEGER NOT NULL DEFAULT 0,
    `response` LONGTEXT NOT NULL,
    `params_hash` VARCHAR(50) NOT NULL,
    `user_id` INTEGER NOT NULL,
    `translation` VARCHAR(5) NOT NULL,
    `created` TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
    `fixed_url` INTEGER NOT NULL DEFAULT 0,
    `deleted` INTEGER NOT NULL DEFAULT 0,
    `parent_id` INTEGER NOT NULL DEFAULT 0,
    `variants_names` VARCHAR(250) NOT NULL DEFAULT '',
    `variants_values` TEXT NOT NULL,
    `from_api_page` INTEGER NULL,

    INDEX `user_id`(`user_id`),
    PRIMARY KEY (`ID`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_access_tokens` (
    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    `shop_id` INTEGER UNSIGNED NULL,
    `expires_at` TIMESTAMP(0) NULL,
    `created_at` TIMESTAMP(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
    `access_token` CHAR(50) NULL,
    `refresh_token` CHAR(50) NULL,

    INDEX `shop_id`(`shop_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_attributes` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `attribute_id` INTEGER NOT NULL,
    `name` VARCHAR(250) NOT NULL,
    `description` TEXT NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_attributes_options` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_attributes_id` INTEGER NOT NULL,
    `option_id` INTEGER NOT NULL,
    `value` VARCHAR(250) NOT NULL,

    INDEX `shoper_attributes_id`(`shoper_attributes_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_billings` (
    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    `shop_id` INTEGER UNSIGNED NULL,
    `created_at` TIMESTAMP(0) NULL DEFAULT CURRENT_TIMESTAMP(0),

    INDEX `shop_id`(`shop_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_categories` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `category_id` INTEGER NOT NULL,
    `order` INTEGER NOT NULL,
    `root` INTEGER NOT NULL,
    `in_loyalty` INTEGER NOT NULL,
    `parent_id` INTEGER NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_categories_language` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_categories_id` INTEGER NOT NULL,
    `translation` VARCHAR(5) NOT NULL,
    `name` VARCHAR(250) NOT NULL,
    `description` TEXT NOT NULL,
    `description_bottom` TEXT NOT NULL,
    `active` INTEGER NOT NULL,
    `isdefault` INTEGER NOT NULL,
    `seo_title` VARCHAR(250) NOT NULL,
    `seo_description` VARCHAR(250) NOT NULL,
    `seo_keywords` VARCHAR(250) NOT NULL,
    `permalink` VARCHAR(250) NOT NULL,

    INDEX `shoper_categories_id`(`shoper_categories_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_currencies_list` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `rate` FLOAT NOT NULL,
    `active` INTEGER NOT NULL,
    `order` INTEGER NOT NULL,
    `default` INTEGER NOT NULL,
    `rate_sync` FLOAT NOT NULL,
    `rate_date` DATETIME(0) NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_languages_list` (
    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `active` INTEGER NOT NULL,
    `order` INTEGER NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_metafields` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `metafield_id` INTEGER NOT NULL,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `object` VARCHAR(10) NOT NULL,
    `key` VARCHAR(25) NOT NULL,
    `namespace` VARCHAR(15) NOT NULL,
    `description` VARCHAR(250) NOT NULL,
    `type` INTEGER NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_producer` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `producer_id` INTEGER NOT NULL,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `name` VARCHAR(250) NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_shops` (
    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at` TIMESTAMP(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
    `shop` VARCHAR(128) NULL,
    `shop_url` VARCHAR(512) NULL,
    `version` INTEGER NULL,
    `installed` SMALLINT NULL DEFAULT 0,

    INDEX `shop`(`shop`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_status` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `status_id` INTEGER NOT NULL,
    `active` INTEGER NOT NULL,
    `default` INTEGER NOT NULL,
    `type` INTEGER NOT NULL,
    `order` INTEGER NOT NULL,
    `translation` VARCHAR(5) NOT NULL,
    `name` VARCHAR(250) NOT NULL,
    `message` TEXT NOT NULL,

    INDEX `order`(`order`),
    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_subscribers` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `subscriber_id` INTEGER NOT NULL,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `email` VARCHAR(250) NOT NULL,
    `active` INTEGER NOT NULL,
    `used` INTEGER NULL DEFAULT 0,
    `dateadd` DATETIME(0) NOT NULL,
    `ipaddress` VARCHAR(50) NULL,
    `lang_id` INTEGER NOT NULL,
    `groups` VARCHAR(250) NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_subscriptions` (
    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    `shop_id` INTEGER UNSIGNED NOT NULL,
    `expires_at` TIMESTAMP(0) NULL,

    INDEX `shop_id`(`shop_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_user_address` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `address_book_id` INTEGER UNSIGNED NOT NULL,
    `user_id` INTEGER NOT NULL,
    `address_name` VARCHAR(250) NOT NULL,
    `company_name` VARCHAR(250) NOT NULL,
    `pesel` VARCHAR(25) NOT NULL,
    `firstname` VARCHAR(250) NOT NULL,
    `lastname` VARCHAR(250) NOT NULL,
    `street_1` VARCHAR(250) NOT NULL,
    `street_2` VARCHAR(250) NOT NULL,
    `city` VARCHAR(250) NOT NULL,
    `zip_code` VARCHAR(15) NOT NULL,
    `state` VARCHAR(15) NOT NULL,
    `country` VARCHAR(15) NOT NULL,
    `default` INTEGER NOT NULL,
    `shipping_default` INTEGER NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `sortkey` VARCHAR(250) NOT NULL,
    `country_code` VARCHAR(5) NOT NULL,
    `tax_identification_number` VARCHAR(50) NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `shoper_user_tag` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `shoper_shops_id` INTEGER UNSIGNED NOT NULL,
    `tag_id` INTEGER NOT NULL,
    `name` VARCHAR(250) NOT NULL,
    `lang_id` INTEGER NOT NULL,

    INDEX `shoper_shops_id`(`shoper_shops_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `user` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `fronturl` VARCHAR(255) NOT NULL DEFAULT '',
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `register_date` DATETIME(0) NOT NULL,
    `active` TINYINT NOT NULL DEFAULT 1,
    `registerToken` VARCHAR(255) NOT NULL,
    `client_id` VARCHAR(255) NOT NULL,
    `client_secret` VARCHAR(255) NOT NULL,
    `uuid` VARCHAR(255) NOT NULL,
    `shop_type` VARCHAR(10) NOT NULL,
    `user_type` VARCHAR(10) NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `user_config` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `id_user` INTEGER NULL,
    `key` VARCHAR(255) NOT NULL,
    `value` VARCHAR(255) NULL,

    INDEX `idx-user_config-id_user`(`id_user`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `user_data` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `user_id` INTEGER NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `value` VARCHAR(250) NOT NULL,

    INDEX `user_id`(`user_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `xml_feed_queue` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `integrated` INTEGER NOT NULL,
    `next_integration_date` DATETIME(0) NOT NULL,
    `executed_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
    `finished_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
    `integration_type` VARCHAR(255) NOT NULL,
    `current_integrate_user` INTEGER NOT NULL,
    `page` INTEGER NOT NULL,
    `max_page` INTEGER NOT NULL,
    `parameters` TEXT NOT NULL,

    INDEX `current_integrate_user`(`current_integrate_user`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `accesstokens` ADD CONSTRAINT `accesstokens_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `customers` ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `disabled_feeds` ADD CONSTRAINT `disabled_feeds_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `idosel_subscriptions` ADD CONSTRAINT `idosel_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `integration_data` ADD CONSTRAINT `integration_data_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `magazines` ADD CONSTRAINT `magazines_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `orders` ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ordersv2` ADD CONSTRAINT `ordersv2_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `positions` ADD CONSTRAINT `positions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `product` ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_access_tokens` ADD CONSTRAINT `FK_access_tokens_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_attributes` ADD CONSTRAINT `shoper_attributes_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_attributes_options` ADD CONSTRAINT `shoper_attributes_options_ibfk_1` FOREIGN KEY (`shoper_attributes_id`) REFERENCES `shoper_attributes`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_billings` ADD CONSTRAINT `FK_billings_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_categories` ADD CONSTRAINT `shoper_categories_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_categories_language` ADD CONSTRAINT `shoper_categories_language_ibfk_1` FOREIGN KEY (`shoper_categories_id`) REFERENCES `shoper_categories`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_currencies_list` ADD CONSTRAINT `shoper_currencies_list_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_languages_list` ADD CONSTRAINT `shoper_languages_list_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_metafields` ADD CONSTRAINT `shoper_metafields_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_producer` ADD CONSTRAINT `shoper_producer_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_status` ADD CONSTRAINT `shoper_status_ibfk_2` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_subscribers` ADD CONSTRAINT `shoper_subscribers_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_subscriptions` ADD CONSTRAINT `FK_subscriptions_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_user_address` ADD CONSTRAINT `shoper_user_address_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `shoper_user_tag` ADD CONSTRAINT `shoper_user_tag_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `user_config` ADD CONSTRAINT `fk-user_config-id_user` FOREIGN KEY (`id_user`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `user_data` ADD CONSTRAINT `user_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `xml_feed_queue` ADD CONSTRAINT `xml_feed_queue_ibfk_1` FOREIGN KEY (`current_integrate_user`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
