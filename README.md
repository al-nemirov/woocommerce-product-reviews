# Smart Product Reviews

WooCommerce product reviews plugin with star ratings, emoji editor, editor notes, and shortcodes.

## Features

### Reviews & Ratings
- Star rating (1–5) with automatic WooCommerce sync
- Review editor with emoji support (toggleable)
- Configurable reviews per page
- Replaces standard WooCommerce reviews tab

### Editor Notes
- Separate note field for editors/authors on each product
- TinyMCE editor with HTML and media support
- Protected editor login page (auto-generated on activation)
- IP blocking after 3 failed attempts (admin reset)
- Cache-safe status bar via AJAX

### Shortcodes
- `[nr_product_reviews]` — review form + list
- `[nr_editor_note]` — editor note block
- `[nr_latest_comments count="5" title="Latest Reviews"]` — latest reviews
- `[nr_popular_comments count="5" title="Popular Reviews"]` — popular reviews
- `[nr_editor_login]` — editor login form

### Theme Functions
- `<?php nr_product_reviews(); ?>` — render reviews block
- `<?php nr_product_reviews(123); ?>` — for specific product
- `do_action('nr_single_product_reviews')` — hook

## Installation

1. Copy folder to `wp-content/plugins/`
2. Activate the plugin
3. Settings: **Smart Product Reviews** in the sidebar menu

## Editor Notes Setup

1. Create a WordPress user (Users → Add New) with **Author** or **Editor** role
2. Create a page, insert `[nr_editor_login]` shortcode
3. Share the page link with your editor
4. Editor logs in → goes to any product → sees "Edit Note" button

## Requirements

- WordPress 5.0+
- PHP 7.2+
- WooCommerce 3.0+

## Compatibility

- Elementor (doesn't render in editor mode)
- Cache-safe (status bar via AJAX)

## Author

Alexander Nemirov
