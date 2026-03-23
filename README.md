# Smart Product Reviews

Плагин отзывов и рейтинга для WooCommerce с авторизацией через VK ID и Яндекс.

## Возможности

### Отзывы и рейтинг
- Рейтинг звёздами (1–5) с автосинхронизацией в WooCommerce
- Редактор отзывов с поддержкой смайлов (вкл/выкл)
- Пагинация отзывов (настраиваемое количество на странице)
- Заменяет стандартную вкладку отзывов WooCommerce

### Авторизация
- Вход через профиль сайта (WordPress)
- Вход через VK ID (OAuth)
- Вход через Яндекс (OAuth)
- Автоматическое создание пользователя при первом входе через соцсеть

### Редактор примечаний
- Отдельное поле примечания для редактора/автора на каждом товаре
- TinyMCE-редактор с поддержкой HTML и медиа
- Защищённая страница входа редактора (автогенерация при активации)
- IP-блокировка при 3 неудачных попытках (сброс из админки)
- Статус-бар для авторизованного редактора

### Шорткоды
- `[nr_product_reviews]` — форма отзывов + список на любой странице
- `[nr_editor_note]` — блок примечания редактора
- `[nr_latest_comments count="5" title="Последние отзывы"]` — последние отзывы
- `[nr_popular_comments count="5" title="Популярные отзывы"]` — популярные отзывы
- `[nr_editor_login]` — форма входа для редактора

### Функции для темы
- `<?php nr_product_reviews(); ?>` — вывод блока отзывов
- `<?php nr_product_reviews(123); ?>` — для конкретного товара
- `do_action('nr_single_product_reviews')` — хук

### Совместимость
- WordPress 5.0+, PHP 7.2+, WooCommerce 3.0+
- Корректная работа с Elementor (не рендерится в редакторе)
- Кэш-устойчивость (статус-бар через AJAX)

## Установка

1. Скопируйте папку плагина в `wp-content/plugins/`
2. Активируйте в меню «Плагины»
3. Настройки: **Smart Product Reviews** в боковом меню

## Настройка VK ID

1. [Создайте приложение VK ID](https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/create-app)
2. Redirect URI: `https://ваш-сайт.ru/wp-admin/admin-ajax.php?action=nr_social_callback&provider=vk`
3. ID приложения — в настройки плагина

## Настройка Яндекс

1. [Создайте OAuth-приложение](https://oauth.yandex.ru/)
2. Callback URI: `https://ваш-сайт.ru/wp-admin/admin-ajax.php?action=nr_social_callback&provider=yandex`
3. ID и секрет — в настройки плагина

## Структура

```
smart-product-reviews/
├── smart-product-reviews.php  — точка входа
├── fix-comments-display.php   — хелпер для принудительного включения отзывов
├── admin/                     — страница настроек
├── includes/                  — ядро, комментарии, рейтинг, соцсети, шорткоды
├── templates/                 — шаблон блока отзывов
└── assets/                    — CSS и JS
```

## Автор

Alexander Nemirov
