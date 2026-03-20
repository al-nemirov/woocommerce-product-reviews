# Smart Product Reviews

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress)
![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0%2B-96588A?logo=woocommerce)
![PHP](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

WooCommerce product reviews plugin with star ratings, emoji editor, editor notes, and shortcodes.

---

## Table of Contents / Содержание

- [Features / Возможности](#features--возможности)
- [Screenshots / Скриншоты](#screenshots--скриншоты)
- [Installation / Установка](#installation--установка)
- [Configuration / Настройка](#configuration--настройка)
- [Shortcodes / Шорткоды](#shortcodes--шорткоды)
- [Theme Functions / Функции для темы](#theme-functions--функции-для-темы)
- [Editor Notes Setup / Настройка заметок редактора](#editor-notes-setup--настройка-заметок-редактора)
- [Requirements / Требования](#requirements--требования)
- [Compatibility / Совместимость](#compatibility--совместимость)
- [Contributing / Участие в разработке](#contributing--участие-в-разработке)
- [License / Лицензия](#license--лицензия)

---

## Features / Возможности

### Reviews & Ratings / Отзывы и рейтинг
- Star rating (1-5) with automatic WooCommerce sync / Рейтинг звёздами (1-5) с синхронизацией WooCommerce
- Review editor with emoji support (toggleable) / Редактор отзывов с эмодзи (отключаемый)
- Configurable reviews per page / Настраиваемое количество отзывов на странице
- Replaces standard WooCommerce reviews tab / Заменяет стандартную вкладку отзывов WooCommerce

### Editor Notes / Заметки редактора
- Separate note field for editors/authors on each product / Отдельное поле заметки для редакторов на каждом товаре
- TinyMCE editor with HTML and media support / Редактор TinyMCE с поддержкой HTML и медиа
- Protected editor login page (auto-generated on activation) / Защищённая страница входа редактора (создаётся автоматически)
- IP blocking after 3 failed attempts (admin reset) / Блокировка IP после 3 неудачных попыток (сброс в админке)
- Cache-safe status bar via AJAX / Статус-бар через AJAX, безопасный при кэшировании

---

## Screenshots / Скриншоты

> Screenshots will be added in a future release.
> Скриншоты будут добавлены в следующих версиях.

<!-- Uncomment and replace with actual paths when available:
![Admin Settings](screenshots/admin-settings.png)
![Review Form](screenshots/review-form.png)
![Editor Note](screenshots/editor-note.png)
![Editor Login](screenshots/editor-login.png)
-->

---

## Installation / Установка

### EN

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/al-nemirov/smart-product-reviews.git
   ```
2. Activate the plugin in **Plugins** menu in WordPress admin.
3. Go to **Smart Product Reviews** in the sidebar menu to configure settings.

### RU

1. Скачайте или клонируйте репозиторий в директорию `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/al-nemirov/smart-product-reviews.git
   ```
2. Активируйте плагин в меню **Плагины** в админке WordPress.
3. Перейдите в **Smart Product Reviews** в боковом меню для настройки.

---

## Configuration / Настройка

### EN

After activation, navigate to **Smart Product Reviews** in the WordPress admin sidebar. Available settings:

| Setting | Description |
|---------|-------------|
| **Emoji picker** | Enable/disable emoji picker in the review editor |
| **Reviews per page** | Number of reviews displayed per page (5-50) |
| **Redirect after login** | URL to redirect editor after login (default: homepage) |
| **Login blocking** | Reset IP blocks for editors locked out after failed attempts |

### RU

После активации перейдите в **Smart Product Reviews** в боковом меню админки WordPress. Доступные настройки:

| Настройка | Описание |
|-----------|----------|
| **Эмодзи** | Включить/выключить эмодзи в редакторе отзывов |
| **Отзывов на странице** | Количество отзывов на странице (5-50) |
| **Редирект после входа** | URL для редиректа редактора после входа (по умолчанию: главная) |
| **Блокировка входа** | Сброс блокировки IP для редакторов после неудачных попыток |

---

## Shortcodes / Шорткоды

| Shortcode | Description / Описание |
|-----------|------------------------|
| `[nr_product_reviews]` | Review form + list on product page / Форма отзыва + список на странице товара |
| `[nr_product_reviews id="123"]` | Reviews for a specific product / Отзывы для конкретного товара |
| `[nr_editor_note]` | Editor note block / Блок заметки редактора |
| `[nr_latest_comments count="5" title="Latest Reviews"]` | Latest reviews widget / Виджет последних отзывов |
| `[nr_popular_comments count="5" title="Popular Reviews"]` | Popular reviews widget / Виджет популярных отзывов |
| `[nr_editor_login]` | Editor login form / Форма входа редактора |
| `[nr_editor_login redirect="https://site.com/shop/"]` | Editor login with custom redirect / Форма входа с редиректом |

---

## Theme Functions / Функции для темы

You can render the reviews block directly in theme templates:

```php
// Render reviews for the current product
<?php nr_product_reviews(); ?>

// Render reviews for a specific product
<?php nr_product_reviews( 123 ); ?>

// Use the action hook
<?php do_action( 'nr_single_product_reviews' ); ?>
```

---

## Editor Notes Setup / Настройка заметок редактора

### EN

1. Create a WordPress user (**Users > Add New**) with **Author** or **Editor** role.
2. Create a page, insert the `[nr_editor_login]` shortcode.
3. Share the page link with your editor.
4. Editor logs in, goes to any product page, and sees the "Edit Note" button.

### RU

1. Создайте пользователя WordPress (**Пользователи > Добавить нового**) с ролью **Автор** или **Редактор**.
2. Создайте страницу, вставьте шорткод `[nr_editor_login]`.
3. Отправьте ссылку на страницу вашему редактору.
4. Редактор входит, переходит на любую страницу товара и видит кнопку "Edit Note".

---

## Requirements / Требования

| Requirement | Version |
|-------------|---------|
| WordPress   | 5.0+    |
| PHP         | 7.2+    |
| WooCommerce | 3.0+    |

---

## Compatibility / Совместимость

- **Elementor** -- does not render in editor mode (placeholder shown instead) / не рендерится в режиме редактора (показывается заглушка)
- **Page caching** -- cache-safe (status bar loaded via AJAX) / безопасно при кэшировании (статус-бар через AJAX)

---

## Contributing / Участие в разработке

### EN

Contributions are welcome! To contribute:

1. Fork this repository.
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "feat: add my feature"`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request.

Please follow the existing code style and add PHPDoc comments to any new methods.

### RU

Мы приветствуем вклад в развитие проекта! Чтобы принять участие:

1. Сделайте форк репозитория.
2. Создайте ветку: `git checkout -b feature/my-feature`
3. Сделайте коммит: `git commit -m "feat: add my feature"`
4. Отправьте ветку: `git push origin feature/my-feature`
5. Откройте Pull Request.

Пожалуйста, следуйте существующему стилю кода и добавляйте PHPDoc-комментарии к новым методам.

---

## License / Лицензия

This project is licensed under the [MIT License](LICENSE).

## Author / Автор

Alexander Nemirov
