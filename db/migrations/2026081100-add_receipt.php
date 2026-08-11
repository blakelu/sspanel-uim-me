<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class () implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `receipt` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '收据ID',
                `receipt_no` varchar(32) NOT NULL COMMENT '收据编号',
                `user_id` bigint(20) unsigned NOT NULL COMMENT '用户ID',
                `order_id` bigint(20) unsigned NOT NULL COMMENT '订单ID',
                `invoice_id` bigint(20) unsigned NOT NULL COMMENT '账单ID',
                `seller_name` varchar(255) NOT NULL DEFAULT '' COMMENT '卖方名称',
                `seller_registration_no` varchar(255) NOT NULL DEFAULT '' COMMENT '卖方注册号',
                `seller_address` varchar(1024) NOT NULL DEFAULT '' COMMENT '卖方地址',
                `seller_tax_id` varchar(255) NOT NULL DEFAULT '' COMMENT '卖方税号',
                `seller_sst_no` varchar(255) NOT NULL DEFAULT '' COMMENT '卖方SST号',
                `seller_logo` varchar(1024) NOT NULL DEFAULT '' COMMENT '卖方Logo',
                `buyer_name` varchar(255) NOT NULL COMMENT '买方名称',
                `buyer_tax_id` varchar(255) NOT NULL DEFAULT '' COMMENT '买方税号',
                `buyer_registration_no` varchar(255) NOT NULL DEFAULT '' COMMENT '买方注册号',
                `buyer_contact_no` varchar(64) NOT NULL DEFAULT '' COMMENT '买方电话',
                `buyer_email` varchar(255) NOT NULL DEFAULT '' COMMENT '买方邮箱',
                `buyer_sst_no` varchar(255) NOT NULL DEFAULT '' COMMENT '买方SST号',
                `buyer_address` varchar(1024) NOT NULL DEFAULT '' COMMENT '买方地址',
                `item_name` varchar(255) NOT NULL DEFAULT '' COMMENT '项目名称',
                `quantity` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT '数量',
                `unit_price` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '单价',
                `discount` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '优惠金额',
                `subtotal` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '未税金额',
                `tax_rate` decimal(5,2) unsigned NOT NULL DEFAULT 0 COMMENT '税率',
                `tax_amount` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '税额',
                `total` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '含税金额',
                `currency` char(3) NOT NULL DEFAULT 'CNY' COMMENT '币种',
                `tax_region` varchar(255) NOT NULL DEFAULT '' COMMENT '税务地区',
                `tax_type` varchar(255) NOT NULL DEFAULT '' COMMENT '税种',
                `footer` varchar(255) NOT NULL DEFAULT '' COMMENT '页脚文案',
                `create_time` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
                `update_time` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间',
                PRIMARY KEY (`id`),
                UNIQUE KEY `receipt_no` (`receipt_no`),
                UNIQUE KEY `order_id` (`order_id`),
                KEY `user_id` (`user_id`),
                KEY `invoice_id` (`invoice_id`),
                KEY `create_time` (`create_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return 2026081100;
    }

    public function down(): int
    {
        DB::getPdo()->exec('DROP TABLE IF EXISTS `receipt`;');

        return 2025070500;
    }
};
