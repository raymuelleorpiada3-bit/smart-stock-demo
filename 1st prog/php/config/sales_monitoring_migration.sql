-- Reference for upgrading an older existing database.
-- Do not run this on the clean groupmate demo: groupmate_clean_setup.sql already includes it.

ALTER TABLE `product`
  ADD COLUMN IF NOT EXISTS `product_barcode` VARCHAR(50) NULL AFTER `product_name`;

CREATE UNIQUE INDEX IF NOT EXISTS `uq_store_product_barcode`
  ON `product` (`store_id`, `product_barcode`);

-- The original dump declares sales_id as a primary key without AUTO_INCREMENT.
ALTER TABLE `sales`
  MODIFY COLUMN `sales_id` BIGINT(20) NOT NULL AUTO_INCREMENT;

-- A past sale must keep its original timestamp when the row is edited later.
ALTER TABLE `sales`
  MODIFY COLUMN `sale_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS `idx_sales_store_product_date`
  ON `sales` (`store_id`, `product_id`, `sale_date`);
