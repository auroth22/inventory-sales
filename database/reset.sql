-- reset.sql: WIPES all sales, products and categories.
-- Keeps the users table. Dev/demo use only. Back up first.
DELETE FROM sale_items;
DELETE FROM sales;
DELETE FROM products;
DELETE FROM categories;

ALTER TABLE sale_items AUTO_INCREMENT = 1;
ALTER TABLE sales      AUTO_INCREMENT = 1;
ALTER TABLE products   AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
