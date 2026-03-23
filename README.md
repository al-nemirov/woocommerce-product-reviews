# WooCommerce Product Reviews

WooCommerce review engine with social login, threaded replies, star ratings, editor notes, and flexible shortcodes.

## Features

- **Star ratings** (1-5) synced to WooCommerce product ratings
- **Threaded replies** — one-level reply threads with inline AJAX forms
- **Social login** — VK, Odnoklassniki (OK), Yandex, Google OAuth
- **Editor notes** — separate TinyMCE-powered note per product, role-restricted
- **AJAX everywhere** — reviews, replies, pagination, editor login, editor notes — zero reloads
- **Shortcodes** with extended attributes: `orderby`, `template`, `show_rating`, `cache_ttl`, etc.
- **Configurable rate limiting** in admin (N reviews per M minutes per IP)
- **Transient caching** for widget shortcodes with versioned invalidation
- **Full i18n** — all strings localizable via `woocommerce-product-reviews` text domain

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[nr_product_reviews]` | Full reviews block (form + list + pagination) |
| `[nr_editor_note]` | Editor note only |
| `[nr_latest_comments count="5" orderby="latest"]` | Latest reviews widget |
| `[nr_popular_comments count="5" orderby="popular"]` | Popular reviews (by reply count) |
| `[nr_latest_comments orderby="rating" show_rating="1"]` | Top rated reviews |
| `[nr_latest_editor_notes count="5"]` | Latest editor notes |
| `[nr_editor_login]` | Editor login form (AJAX) |

**Extended attributes:** `count`, `title`, `orderby` (latest/popular/rating), `order` (ASC/DESC), `product_id`, `template` (compact/full), `show_author`, `show_product`, `show_rating`, `cache_ttl`.

## Requirements

- WordPress 5.0+, PHP 7.2+, WooCommerce 3.0+
- Elementor compatible (no render in editor mode)

## Installation

1. Upload to `wp-content/plugins/woocommerce-product-reviews/`
2. Activate in Plugins menu
3. Configure: **WooCommerce Product Reviews** in admin sidebar

## Author

Alexander Nemirov

---

# WooCommerce Product Reviews (RU)

Система отзывов и рейтингов для WooCommerce с соцсетями, ветками ответов, редакторскими примечаниями и гибкими шорткодами.

## Возможности

- **Рейтинг звёздами** (1-5) с синхронизацией в WooCommerce
- **Ветки ответов** — один уровень с inline AJAX-формой
- **Соцсети** — VK, Одноклассники, Яндекс, Google OAuth
- **Примечания редактора** — TinyMCE-поле на каждом товаре, доступ по роли
- **Всё на AJAX** — отзывы, ответы, пагинация, логин, примечания — без перезагрузок
- **Шорткоды** с расширенными атрибутами: `orderby`, `template`, `show_rating`, `cache_ttl` и др.
- **Настраиваемый rate-limit** в админке (N отзывов за M минут на IP)
- **Transient-кэш** виджетных шорткодов с версионной инвалидацией
- **Полная локализация** — все строки через text domain `woocommerce-product-reviews`

## Установка

1. Скопируйте в `wp-content/plugins/woocommerce-product-reviews/`
2. Активируйте в меню «Плагины»
3. Настройки: **WooCommerce Product Reviews** в боковом меню

## Автор

Alexander Nemirov
