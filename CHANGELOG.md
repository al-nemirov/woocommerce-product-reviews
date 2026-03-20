# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-01

### Added
- Star rating system (1-5) with automatic WooCommerce sync.
- Review submission via AJAX with validation.
- Emoji picker toggle in review editor.
- Configurable reviews per page (5-50).
- Editor notes system with TinyMCE editor, HTML, and media support.
- Protected editor login page (auto-generated on plugin activation).
- IP blocking after 3 failed login attempts (1-hour cooldown, admin reset).
- Cache-safe editor status bar loaded via AJAX.
- Admin settings page under **Smart Product Reviews** menu.
- Shortcodes: `[nr_product_reviews]`, `[nr_editor_note]`, `[nr_latest_comments]`, `[nr_popular_comments]`, `[nr_editor_login]`.
- Theme function `nr_product_reviews()` and action hook `nr_single_product_reviews`.
- Elementor compatibility (placeholder in editor mode).
- Replaces standard WooCommerce reviews tab.
- `fix-comments-display.php` helper for themes that disable product comments.
- Noindex meta tag for secret editor login page.
