# Plugin Enhancement Roadmap

Comprehensive review of the **AI Search Summary** plugin (last reviewed at v2.1.0, 2026-09-11).
Organized by category and priority so items can be tackled incrementally.

---

## 1. Security Updates

### High Priority

- [ ] **1.1 API Key Encryption at Rest**
  Encrypt API keys with `sodium_crypto_secretbox()` before storing in `wp_options`; decrypt on retrieval.
  _Files:_ `riviantrackr-ai-search-summary.php` (sanitize_options, option retrieval)

- [ ] **1.2 Rate Limiter Atomic Lock Fix**
  Replace transient-based spin lock (`usleep` loop) with `wp_cache_add()` for object-cache-aware atomicity, or use `$wpdb` advisory locks.
  _Files:_ `includes/class-rate-limiter.php` (is_ip_rate_limited)

- [ ] **1.3 REST API Schema Validation**
  Add full JSON `schema` definitions to `register_rest_route()` args for automatic WordPress validation and self-documenting endpoints.
  _Files:_ `riviantrackr-ai-search-summary.php` (register_rest_routes)

### Medium Priority

- [ ] **1.4 Content Security Policy Tightening** _(2.1.0 widened `img-src` for Gravatar/emoji; nonce-based inline policy still open)_
  Replace `'unsafe-inline'` with nonce-based CSP (`'nonce-{random}'`) for inline scripts and styles.
  _Files:_ `riviantrackr-ai-search-summary.php` (add_security_headers)

- [ ] **1.5 Subresource Integrity (SRI) Hashes**
  Add integrity hashes to enqueued script/style tags to prevent tampered asset loading.
  _Files:_ `riviantrackr-ai-search-summary.php` (enqueue_frontend_assets, enqueue_admin_assets)

- [ ] **1.6 CORS Headers for REST Endpoints**
  Add restrictive `Access-Control-Allow-Origin` headers scoped to the site's own domain.
  _Files:_ `riviantrackr-ai-search-summary.php` (rest_post_dispatch)

### Low Priority

- [ ] **1.7 Admin Audit Logging**
  Log admin actions (settings changes, cache clears, data purges) with who/what/when to a separate audit table.
  _Files:_ New class or extend `Analytics`

---

## 2. Performance Enhancements

### High Priority

- [ ] **2.1 Object Cache Support**
  Use `wp_cache_get/set` (object cache API) with transient fallback — benefits sites running Redis/Memcached.
  _Files:_ `includes/class-cache-manager.php`

### Medium Priority

- [ ] **2.2 Streaming AI Responses (SSE)**
  Support Server-Sent Events for streaming AI responses to reduce perceived latency.
  _Files:_ `includes/class-api-handler.php`, `assets/riviantrackr.js`, new REST endpoint

- [ ] **2.3 Database Query Optimization for Large Tables**
  Implement query partitioning, use `COUNT` estimates via `SHOW TABLE STATUS` for large tables, add composite indexes for common analytics queries.
  _Files:_ `includes/class-analytics.php`, activation/upgrade routines

- [ ] **2.4 Lazy-Load Analytics Dashboard**
  Use AJAX-powered data tables with server-side pagination for analytics page.
  _Files:_ `riviantrackr-ai-search-summary.php` (analytics page), `assets/riviantrackr-admin.js`

### Low Priority

- [ ] **2.5 Background Processing for Long Queries**
  Return job ID immediately, client polls for result — prevents long-blocking REST requests.
  _Files:_ `riviantrackr-ai-search-summary.php`, `assets/riviantrackr.js`

---

## 3. Feature Additions

### Medium Priority

- [ ] **3.1 Custom Prompt Templates**
  Allow admins to customize the system prompt via settings with placeholder variables (`{site_name}`, `{site_desc}`, `{query}`).
  _Files:_ `includes/class-api-handler.php`, settings page

- [ ] **3.2 Google Gemini Provider Support**
  Add Google Gemini as a third AI provider option.
  _Files:_ `includes/class-api-handler.php`, settings page (provider selection)

- [ ] **3.3 Summary Quality Feedback Loop**
  Surface feedback stats per query in analytics; allow admins to view low-rated summaries; optionally auto-bust cache for low-rated queries.
  _Files:_ `includes/class-analytics.php`, analytics page

- [ ] **3.4 Multi-Language AI Summaries**
  Detect user locale and instruct AI to respond in the appropriate language; ship base .po/.mo translations.
  _Files:_ `includes/class-api-handler.php` (build_system_prompt), `languages/`

### Low Priority

- [ ] **3.5 WooCommerce Product Search Integration**
  Add WooCommerce-aware prompt formatting (price, SKU, availability) and product schema support.
  _Files:_ `includes/class-api-handler.php` (format_posts_for_prompt)

- [ ] **3.6 Related Searches / "People Also Ask"**
  Use AI to generate 3-5 related search suggestions displayed below the summary.
  _Files:_ `includes/class-api-handler.php`, frontend JS/CSS

- [ ] **3.7 Admin Email Alerts**
  Send email alerts when AI error rate exceeds threshold, API key expires, or rate limits are consistently hit.
  _Files:_ New utility, use `wp_mail()`

- [ ] **3.8 Settings Import/Export**
  Add JSON export/import for all plugin settings — useful for staging-to-production workflows.
  _Files:_ `riviantrackr-ai-search-summary.php` (admin page)

---

## 4. Code Quality & Architecture

### High Priority

- [ ] **4.1 Break Up Main Plugin File**
  Extract the ~5,300-line monolith into focused classes: `AdminPage`, `RestController`, `SettingsManager`, `WidgetManager`, `Hooks`. Keep main file as thin bootstrapper.
  _Files:_ `riviantrackr-ai-search-summary.php` → multiple new files in `includes/`

- [ ] **4.2 Add CI/CD Pipeline**
  GitHub Actions workflow for: PHPUnit tests, PHP CodeSniffer (WPCS), PHPStan static analysis, asset minification verification.
  _Files:_ New `.github/workflows/ci.yml`

### Medium Priority

- [ ] **4.3 PHPStan / Static Analysis**
  Add PHPStan at level 6+ with WordPress stubs for type safety.
  _Files:_ New `phpstan.neon`, `composer.json` dev dependency

- [ ] **4.4 WordPress Coding Standards Enforcement**
  Add PHPCS with `WordPress-Extra` ruleset.
  _Files:_ New `.phpcs.xml.dist`, `composer.json` dev dependency

### Low Priority

- [ ] **4.5 Integration Test Coverage**
  Add integration tests using `WP_UnitTestCase` for REST endpoints, settings sanitization, and activation/upgrade flows.
  _Files:_ `tests/` directory, `phpunit.xml`

- [ ] **4.6 Strict Typing Across All Methods**
  Add return types and param types everywhere; leverage PHP 8.4 features where useful.
  _Files:_ All PHP files

---

## 5. UX / Admin Experience

### Medium Priority

- [ ] **5.1 First-Run Setup Wizard**
  Walk new users through: API provider selection → key entry → test connection → enable.
  _Files:_ `riviantrackr-ai-search-summary.php` (new admin page/modal)

- [ ] **5.2 Admin Notification System**
  Show dismissible admin notices for: expired/invalid API key, high error rate, plugin misconfiguration.
  _Files:_ `riviantrackr-ai-search-summary.php` (admin_notices hook)

### Low Priority

- [ ] **5.3 Real-Time API Cost Estimator**
  Track token usage per request, display estimated cost in analytics dashboard based on provider pricing.
  _Files:_ `includes/class-api-handler.php`, `includes/class-analytics.php`, analytics page

- [ ] **5.4 Health Check Dashboard Widget**
  Add health indicators: API connectivity, cache hit rate, error rate trend, rate limit headroom.
  _Files:_ `riviantrackr-ai-search-summary.php` (register_dashboard_widget)

---

## 6. Accessibility & Frontend

### Medium Priority

- [x] **6.1 ARIA Attributes & Keyboard Navigation** _(shipped in 2.1.0)_
  `aria-live`/`aria-busy` on the status region with an announced loading status, `aria-expanded`/`aria-controls` on the sources and advanced-settings toggles, `aria-hidden` on the skeleton and decorative icons, `aria-label` on feedback buttons, dialog semantics with focus trap/return on the CSS reference modal, live regions for admin results, labelled log checkboxes, `:focus-visible` styles, and a `<noscript>` notice.
  _Files:_ `assets/riviantrackr.js`, placeholder HTML in main plugin file

### Low Priority

- [ ] **6.2 Dark Mode Support**
  Add `prefers-color-scheme` media query support with separate dark mode color palette.
  _Files:_ `assets/riviantrackr.css`, settings page

- [x] **6.3 Loading Skeleton Animation** _(shipped)_
  Skeleton placeholder with shimmer animation is rendered while the AI response loads. See `.riviantrackr-skeleton` and `.riviantrackr-skeleton-line` rules in `assets/riviantrackr.css` and the skeleton injection in `assets/riviantrackr.js`.

---

## 7. Compliance & Privacy

### High Priority

- [ ] **7.1 GDPR Personal Data Export/Erase Hooks**
  Register with `wp_register_personal_data_exporter` and `wp_register_personal_data_eraser` for native WordPress privacy tools compliance.
  _Files:_ `riviantrackr-ai-search-summary.php`, `includes/class-analytics.php`

### Medium Priority

- [ ] **7.2 Data Processing Disclosure**
  Add admin notice about data processing implications when sending queries to third-party AI APIs; optionally display user-facing disclosure.
  _Files:_ Settings page, frontend template

### Low Priority

- [ ] **7.3 Cookie Consent Plugin Integration**
  Add filter hooks for consent plugins to conditionally load frontend features.
  _Files:_ `riviantrackr-ai-search-summary.php` (enqueue_frontend_assets)

---

## Priority Summary

| Priority | Count | Items |
|----------|-------|-------|
| **High** | 7 | 1.1, 1.2, 1.3, 2.1, 4.1, 4.2, 7.1 |
| **Medium** | 15 | 1.4, 1.5, 1.6, 2.2, 2.3, 2.4, 3.1-3.4, 4.3, 4.4, 5.1, 5.2, 7.2 |
| **Low** | 12 | 1.7, 2.5, 3.5-3.8, 4.5, 4.6, 5.3, 5.4, 6.2, 7.3 _(6.1, 6.3 shipped)_ |
