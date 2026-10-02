-- PreBasket PostgreSQL performance indexes for Supabase
-- Run this ONCE in Supabase SQL Editor against the existing project database.
-- Safe to run repeatedly because every index uses IF NOT EXISTS.

CREATE INDEX IF NOT EXISTS idx_cart_product_id
    ON public.cart (product_id);

CREATE INDEX IF NOT EXISTS idx_orders_created_at
    ON public.orders (created_at DESC);

CREATE INDEX IF NOT EXISTS idx_orders_user_created_at
    ON public.orders (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_orders_status_created_at
    ON public.orders (order_status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_order_items_product_id
    ON public.order_items (product_id);

CREATE INDEX IF NOT EXISTS idx_feedback_rating_created_at
    ON public.feedback (rating, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_products_status_stock
    ON public.products (status, stock_quantity);

CREATE INDEX IF NOT EXISTS idx_products_category_status
    ON public.products (category_id, status);
