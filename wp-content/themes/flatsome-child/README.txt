Fika Flatsome Child 3.10.5
=========================

Compatibility target (verify after installation):
- Flatsome 3.20.9
- WordPress 7.1
- WooCommerce 11.1
- PHP 8.2
- Polylang Pro + Polylang for WooCommerce
- Rank Math SEO
- LiteSpeed Cache

Main modules
------------
- WooCommerce transaction disabling with real 404 responses for disabled pages.
- Product-card View Now / Xem Ngay CTA and empty-rating control.
- Paginated product-archive description control.
- Faceted-navigation normalization, noindex and crawl-graph controls.
- Base-free multilingual product-category URLs and sitemap inclusion.
- Native Product Brand bottom-content editor.
- Language switcher, Brand list and first-page shortcodes.
- Deferred rewrite/sitemap maintenance with route-collision diagnostics.
- Responsive Single Post, Blog Archive and homepage Blog presentation.
- Classic Editor and Classic Widgets without standalone plugins.

Changes in 3.10.5
-----------------
- Normalizes legacy first-party http://fikapouches.com and www variants to the
  canonical HTTPS origin in public front-end HTML.
- Updates the historical XFN profile reference to its supported HTTPS URL.
- Does not alter external links or standards-based HTTP namespace identifiers,
  including the required SVG namespace.
- Removes nonessential XMP metadata from the bundled logo image.

Installation and verification
-----------------------------
Read HUONG-DAN-3.10.5.txt before replacing the active child theme, then complete
KIEM-THU-3.10.5.txt after purging all page/CDN/browser caches.

Important filter rule
---------------------
FIKA_BLOCK_FILTER_CRAWL is false unless explicitly enabled in wp-config.php.
Do not block faceted URLs in robots.txt while search engines still need to read
their noindex directive.

Rollback
--------
Restore the previous child-theme ZIP, save Permalinks once and purge all caches.
Theme rollback does not alter stored posts, products, terms or Rank Math metadata.
