# WooCommerce Product Reviews

![WordPress](https://img.shields.io/badge/WordPress-6.x-21759b?logo=wordpress)
![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0+-96588a?logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.2+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

[English](#english) | [Русский](#русский)

---

## English

Review and rating system for WooCommerce with social login (VK, OK, Yandex, Google), threaded replies, editor notes, and avatars from social networks.

### Features

- **Social login only** — VK ID, Odnoklassniki, Yandex, Google OAuth (no guest reviews)
- **Star ratings** (0-5, optional) synced to WooCommerce product ratings
- **Threaded replies** — one-level reply threads with inline AJAX forms
- **Editor notes** — optional TinyMCE note per product, configurable title
- **Editor badge** — comments from editors/admins highlighted with green background and badge
- **Social avatars** — user photos from VK/OK/Google shown in comments
- **AJAX everywhere** — reviews, replies, pagination, votes, editor notes — zero reloads
- **Like/dislike votes** — per-IP deduplication (24h)
- **Admin bar** — quick access to reviews settings from top menu
- **Clean OAuth URLs** — `/nr-auth/vk/` (no query parameters in redirect URIs)
- **Configurable rate limiting** (N reviews per M minutes per IP)
- **Shortcodes** with extended attributes
- **GitHub auto-updater** with object cache support

### Social Login Setup

Each provider requires an app with a redirect URI:

| Provider | Redirect URI | Keys needed |
|----------|-------------|-------------|
| VK ID | `/nr-auth/vk/` | App ID + Protected Key |
| OK | `/nr-auth/ok/` | App ID + App Key + Secret |
| Yandex | `/nr-auth/yandex/` | Client ID + Client Secret |
| Google | `/nr-auth/google/` | Client ID + Client Secret |

### Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[nr_product_reviews]` | Full reviews block (form + list + pagination) |
| `[nr_editor_note]` | Editor note only |
| `[nr_latest_comments count="5"]` | Latest reviews widget |
| `[nr_popular_comments count="5"]` | Popular reviews |
| `[nr_latest_editor_notes count="5"]` | Latest editor notes |
| `[nr_editor_login]` | Editor login form |

### Requirements

- WordPress 5.0+, PHP 7.2+, WooCommerce 3.0+
- Elementor compatible

### Installation

1. Upload to `wp-content/plugins/woocommerce-product-reviews/`
2. Activate in Plugins
3. Go to **WC Отзывы** in admin sidebar to configure
4. Go to Settings → Permalinks → Save (to flush rewrite rules)

---

## Русский

Система отзывов и рейтингов для WooCommerce с авторизацией через соцсети (VK, OK, Яндекс, Google), ветками ответов, примечаниями редактора и аватарками.

### Возможности

- **Только соцлогин** — VK ID, Одноклассники, Яндекс, Google OAuth (без гостевых отзывов)
- **Рейтинг звёздами** (0-5, необязательно) с синхронизацией в WooCommerce
- **Ветки ответов** — один уровень с inline AJAX-формой
- **Примечание редактора** — опционально, TinyMCE, настраиваемый заголовок (Рецензия, О книге и т.д.)
- **Бейдж «Редактор»** — комментарии редакторов/админов с зелёным фоном и бейджем
- **Аватарки из соцсетей** — фото из VK/OK/Google в комментариях (44px, круг)
- **Всё на AJAX** — отзывы, ответы, пагинация, голоса, примечания — без перезагрузок
- **Лайки/дизлайки** — дедупликация по IP (24ч)
- **Верхнее меню** — быстрый доступ к настройкам отзывов из admin bar
- **Чистые OAuth URL** — `/nr-auth/vk/` (без query-параметров в redirect URI)
- **Настраиваемый rate-limit** (N отзывов за M минут на IP)
- **Шорткоды** с расширенными атрибутами
- **Авто-обновление с GitHub** с поддержкой object cache

### Настройка соцсетей

| Провайдер | Redirect URI | Нужные ключи |
|-----------|-------------|--------------|
| VK ID | `/nr-auth/vk/` | App ID + Защищённый ключ |
| OK | `/nr-auth/ok/` | App ID + App Key + Secret |
| Яндекс | `/nr-auth/yandex/` | ClientID + Client secret |
| Google | `/nr-auth/google/` | Client ID + Client Secret |

**Важно для VK ID:** В настройках приложения → Авторизация → Данные для регистрации → включите **«Почта»**.

### Установка

1. Скопируйте в `wp-content/plugins/woocommerce-product-reviews/`
2. Активируйте в «Плагины»
3. Перейдите в **WC Отзывы** в боковом меню для настройки
4. Настройки → Постоянные ссылки → Сохранить (для активации чистых URL)

---

## Author / Автор

**Alexander Nemirov** — [GitHub](https://github.com/al-nemirov)

## License

[MIT](LICENSE)
