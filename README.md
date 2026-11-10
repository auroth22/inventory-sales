# Inventory and Sales System

A web-based inventory and point-of-sale system for a small pharmacy, built with PHP and MySQL.

## Features

- Login and register popup (new accounts are always **staff**)
- Dashboard with product, category and low-stock counts and today's sales
- Categories and products management (admin only)
- Low-stock items shown first on the Products page
- New Sale (POS) with a cart, a database transaction at checkout, and printable receipts
- Sales history and reports (COUNT, SUM, AVG, MIN, MAX, GROUP BY, HAVING)
- Admin and staff roles, enforced on the server
- Archive instead of delete: a product or category that is part of past sales is hidden (`is_active = 0`) so old receipts and reports still work
- Session idle timeout and a light / dark theme
- Read-only JSON API (`GET` only, login required)

## Requirements

- XAMPP (Apache + MySQL, PHP 8 or newer)

## How to run

1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\inventory-sales`.
3. Open `localhost/phpmyadmin`, create an empty database named `inventory_db`, then click **Import** and choose `database/inventory_db.sql`.
4. Visit `localhost/inventory-sales` and log in with the demo account.

**Demo account (for local testing only):** `admin` / `admin123`

## Database scripts (`database/`)

| File | What it does |
|---|---|
| `inventory_db.sql` | Full export: tables, sample pharmacy data and the demo users. Import this to set up. |
| `reset.sql` | **Deletes** all sales, products and categories (keeps users). Back up first. |
| `seed.sql` | Refills the database with sample pharmacy data. Run `reset.sql` before it. |

## Roles

| | Admin | Staff |
|---|---|---|
| Dashboard, New Sale, receipts | Yes | Yes |
| View products | Yes | Yes |
| Add / edit / delete products | Yes | No |
| Categories | Yes | No |

Role changes and password resets are done in phpMyAdmin on purpose, so the website has no way to escalate privileges.

## JSON API (read-only)

All endpoints use `GET`, need a logged-in session and return JSON. Without a session they return `401`.

| Endpoint | Returns |
|---|---|
| `api/products.php` | Active products. Optional `?q=name` and `?category_id=1` |
| `api/low-stock.php` | Products at or below their reorder level |
| `api/sales-today.php` | Today's sales count and total |

## Project structure

```
inventory-sales/
├── api/         JSON endpoints
├── assets/
├── database/    SQL export, reset and seed scripts
├── includes/    auth, database connection, header, footer
├── pages/       products, categories, sales, receipts, reports
├── index.php    dashboard
├── login.php    login and register popup
└── style.css
```

## Possible improvements

- Token authentication and create / update / delete endpoints (a full REST API)
- Clickable, sortable table columns
- A restore screen for archived products and categories
- Product images

## Fun fact

I'll have two number 9s, a number 9 large, a number 6 with extra dip, a number 7, two number 45s, one with cheese, and a large soda.