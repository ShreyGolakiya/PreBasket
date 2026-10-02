# PreBasket – Smart Supermarket Pre-Order and Quick Collection System

> **"Shop Before You Shop."**
> Customers pre-order groceries online, then walk in and collect the packed parcel
> without searching the aisles or waiting in the billing queue.

A basic-level college **Web Technology semester project** built with
**HTML · CSS · JavaScript · PHP · MySQL · XAMPP · SVG · HTML5 Canvas**.
No cloud server, no internet, no external libraries – everything runs on `localhost`.

---

## 1. How to run it (5 minutes)

1. Install **XAMPP** (PHP 7.4 or newer – any current XAMPP is fine).
2. Copy the whole **`PreBasket`** folder into:

   ```
   C:\xampp\htdocs\PreBasket\
   ```

3. Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**.
4. Import the database (only once):
   1. Open **http://localhost/phpmyadmin**
   2. Click the **Import** tab
   3. Choose the file `PreBasket\database\prebasket.sql` → click **Import** (at the bottom)

   *(The file creates the database `prebasket`, all tables and the sample data.)*
5. Open the website:

   | Area | Address |
   |------|---------|
   | Customer website | http://localhost/PreBasket/ |
   | Admin / shop owner panel | http://localhost/PreBasket/admin/login.php |

### Demo logins

| Role | Login | Password |
|------|-------|----------|
| Admin (shop owner) | username `admin` | `admin123` |
| Customer | `demo@prebasket.test` | `demo123` |
| Customer 2 | `riya@prebasket.test` | `demo123` |

You can also create your own customer with the **Sign up** button.

> If you changed the MySQL password in XAMPP, edit **one file only**: `config/database.php`.

---

## 2. The main idea (customer journey)

```
Browse / search products  →  Add to cart  →  Checkout
  →  Online Payment (demo)  or  Cash at Store
  →  Order ID + QR code
  →  Staff:  Accepted → Preparing → Ready for Collection
  →  Customer arrives, shows Order ID / QR
  →  Staff verifies and marks Collected  →  Completed
  →  Customer leaves feedback
```

Order status flow shown to the customer:
`Pending → Accepted → Preparing → Ready for Collection → Collected → Completed`
(plus `Rejected` / `Cancelled`). Random jumps such as *Pending → Completed* are **blocked**.

---

## 3. Features

### Customer side
| Page | File |
|------|------|
| Home (search, categories, featured products, "How it works") | `index.php` |
| Register / Login / Logout | `register.php`, `login.php`, `logout.php` |
| Products – search, category filter, sort, live filtering | `products.php` |
| Product details (image, price, rating, stock, Add button) | `product.php` |
| Cart – add, + / −, remove, subtotal, total | `cart.php`, `cart_action.php` |
| Checkout (Online Payment / Cash at Store) | `checkout.php` |
| Demo payment page ("Pay Now") | `payment.php` |
| Order confirmation with Order ID + **QR code** | `order_success.php` |
| My Orders, Order details + tracking, **Quick Reorder**, cancel while Pending | `orders.php`, `order_details.php` |
| Feedback (1–5 stars + text) after Collected / Completed | `feedback.php` |

### Admin side (`/admin/`)
| Page | File |
|------|------|
| Dashboard with **Canvas chart** (orders / sales for the last 7 days) | `admin/dashboard.php` |
| Product management: add, edit, image upload, remove / restore | `admin/products.php`, `product_add.php`, `product_edit.php`, `product_delete.php` |
| Category management | `admin/categories.php` |
| Inventory (set / add / subtract stock, low & out-of-stock views) | `admin/inventory.php` |
| Order management + status buttons | `admin/orders.php`, `admin/order_details.php` |
| **Verify / Collect** – enter Order ID (or scan with a USB scanner) | `admin/verify_order.php` |
| Customer feedback | `admin/feedback.php` |

The shop owner **never edits the database by hand** – everything is done from the admin panel:

```
Admin Panel  →  PHP  →  MySQL  →  customer website shows it automatically
```

Example: change *Lays* from ₹25 to ₹30 in **Edit Product** → the customer site shows ₹30 at once.

---

## 4. Important design decisions (good for the viva!)

**1. Old orders never change (price snapshot).**
When an order is placed, the product name and price are **copied** into `order_items`
(`product_name_snapshot`, `price_at_purchase`, `subtotal`).
If Lays is ₹25 today and ₹30 tomorrow, the old order still shows `Lays × 2 = ₹50`.

**2. Products are never deleted (soft delete).**
"Remove product" only sets `status = 'inactive'`. The product disappears from the shop,
but old orders still point to a valid row. The admin can restore it any time.

**3. Placing an order uses a database transaction** (`place_order()` in `includes/functions.php`):

```
BEGIN
  lock the cart products and re-check the stock on the SERVER
  insert the order
  insert the order items (with price_at_purchase)
  reduce the stock      (only if enough stock is still left)
  insert the payment row and empty the cart
COMMIT          -- if anything fails: ROLLBACK, nothing is half-saved
```

**4. Stock is protected everywhere.**
The customer sees *"Only 5 items available."* when asking for too many.
Stock 0 shows *"Out of Stock"* and the Add button is disabled.
Negative stock is impossible (PHP checks + `UNSIGNED` column + `CHECK` constraints).
Cancelled / rejected orders put their items back into stock.

**5. JavaScript is never the only validation.** Every form is checked in the browser
*and* again in PHP.

---

## 5. Technologies – where each one is used

| Technology | Where |
|------------|-------|
| **HTML5** | All pages, semantic layout, forms |
| **CSS3** | `assets/css/style.css` – responsive grid, product cards, admin layout |
| **JavaScript** | `assets/js/script.js` – search/filter/sort, add-to-cart (AJAX), quantity buttons, validation, confirmation dialogs |
| **PHP** | Sessions, login, CRUD, cart, orders, payments, feedback, admin |
| **MySQL** | `database/prebasket.sql` – 9 tables with primary & foreign keys |
| **XAMPP** | Apache + PHP + MySQL on `localhost` |
| **SVG** | Logo, cart / category / status icons (`includes/icons.php`), discount badge, all product pictures (`assets/images/products/*.svg`) |
| **HTML5 Canvas** | Dashboard chart (`assets/js/chart.js`, hand-written, no library) and the **QR code** (`assets/js/qrcode.js`, works offline) |

### Database tables
`users` · `admins` · `categories` · `products` · `cart` · `orders` · `order_items` · `payments` · `feedback`

---

## 6. Security (basic, understandable)

- Passwords stored with `password_hash()` and checked with `password_verify()` – never plain text
- All SQL uses **PDO prepared statements**
- **CSRF token** in every important POST form
- Output escaped with `htmlspecialchars()` (through the helper `e()`)
- Admin uses a **separate session key** – a customer login can never open `/admin/`
- Image upload accepts only real JPG / PNG / WEBP / GIF up to 2 MB (SVG uploads are rejected because SVG can carry scripts)
- `config/`, `includes/`, `database/` and `logs/` are blocked from the browser with `.htaccess`
- Users see friendly messages; real errors are written to `logs/error.log`

> **Demo payment:** there is **no real payment gateway**. "Pay Now" only pretends to pay,
> so the Online Payment path can be demonstrated. No card details are ever stored.

---

## 7. Folder structure

```
PreBasket/
├── index.php  login.php  register.php  logout.php
├── products.php  product.php  cart.php  cart_action.php
├── checkout.php  payment.php  order_success.php
├── orders.php  order_details.php  feedback.php
│
├── admin/            login, logout, dashboard, products, product_add/edit/delete,
│                     categories, inventory, orders, order_details, verify_order, feedback
├── config/           database.php          ← the ONLY place with DB settings
├── includes/         header, footer, auth, admin_auth, functions, icons,
│                     product_card, product_form, image_upload
├── assets/
│   ├── css/style.css
│   ├── js/           script.js, chart.js (Canvas chart), qrcode.js (QR)
│   ├── icons/        favicon.svg
│   └── images/products/   local SVG product pictures (+ your uploads)
├── database/prebasket.sql
├── logs/             error.log is created here if something goes wrong
└── README.md
```

---

## 8. Quick test checklist (use it for your demo)

1. **Register** a new customer, then log in.
2. Search for `milk`, filter a category, sort by price.
3. Add products to the cart, use **+ / −**, try to exceed the stock (*"Only N items available"*).
4. **Checkout → Cash at Store** → note the Order ID and QR code.
5. Log in to `/admin/login.php` → **Orders** → move the order *Accepted → Preparing → Ready for Collection*.
6. **Verify / Collect** → type the Order ID → mark **Collected** (cash order becomes *Paid*).
7. As the customer, open **My Orders** → the order shows *Collected* → give **feedback**.
8. Admin → **Edit Product** → change a price → refresh the customer site (new price), open the old order (old price unchanged).
9. Admin → **Inventory** → set a product's stock to 0 → customer sees *Out of Stock*.
10. Admin → **Remove product** → it disappears from the shop, old orders still show it.
11. Customer → **My Orders → Quick Reorder** on an old order (removed products are reported, not added).
12. Admin **Dashboard** → the Canvas chart (a new database with no orders shows a friendly empty message).

> The sample database already contains 19 orders spread over the last 7 days
> (dates are relative to the day you import the file), so the chart is never empty.

---

## 9. Troubleshooting

| Problem | Fix |
|---------|-----|
| *"Cannot connect to the database"* | Start **MySQL** in XAMPP and import `database/prebasket.sql`. |
| Apache will not start (port 80 busy) | Close Skype / IIS / other web servers, or change Apache's port in XAMPP and use `http://localhost:8080/PreBasket/`. |
| Page not found | The folder must be exactly `htdocs\PreBasket\` and `index.php` must be directly inside it. |
| MySQL has a password | Put it in `DB_PASS` in `config/database.php`. |
| Image upload fails | Use JPG / PNG / WEBP / GIF under 2 MB. |
| Want to see technical errors | Set `APP_DEBUG` to `true` in `config/database.php` (set it back to `false` for the demo). |
| Want to reset all data | Import `database/prebasket.sql` again (it recreates every table). |

### About the QR code
The QR code is drawn on an HTML5 canvas by a small **local JavaScript file** (`assets/js/qrcode.js`),
so it works with **no internet**. It contains only the **Order ID** (e.g. `PB202609200001`).
At the counter the staff opens **Verify / Collect** and types or scans the Order ID
(a USB barcode/QR scanner works because it simply types into the box).
Webcam scanning was left out on purpose to keep the project simple and reliable.

---

*PreBasket – college web technology project.*


## Performance optimization

This PostgreSQL/Supabase build reuses PHP database connections where supported,
reduces repeated cart-count queries, and consolidates dashboard KPI queries.
After importing the project, run `database/performance_indexes.sql` once in the
Supabase SQL Editor to add indexes used by cart, order, inventory and feedback pages.
