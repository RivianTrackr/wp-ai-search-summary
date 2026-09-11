# Changelog

All notable changes to RivianTrackr AI Search Summary will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-09-11

### Fixed
- **Session-cache hits disabled the sources toggle and feedback buttons.** The cache-hit branch in `riviantrackr.js` returned before the click handlers were registered. Handlers are now bound once, before any content renders.
- **Repeat searches got a 429 even when cached.** The duplicate-query throttle ran in the REST permission callback, ahead of the cache lookup. It now runs in `rest_get_summary()` only on a genuine cache miss, so a repeat of a cached search (new tab, refresh) is served from cache.
- **Cached "no results" responses were logged as successful session hits**, inflating the success rate. Only a real summary is logged as a session cache hit.
- **Feedback errors rendered as "Thanks for your feedback".** The feedback handler now checks the HTTP status and `success` flag; a stale nonce or server error re-enables the buttons with the server's message, and a duplicate vote is reported as such.
- **The trending widget created a second plugin instance on every render**, re-registering every hook. The main instance is now assigned to `$GLOBALS['riviantrackr_instance']` and the widget uses it.
- **Empty model after the 2.0 migration.** A non-Claude model was reset to `''` and sent to the API (HTTP 400). `RIVIANTRACKR_DEFAULT_MODEL` now applies whenever the option is empty, in the migration, in `get_options()`, and in `sanitize_options()`.
- **Retries outlived the browser.** A timed-out API request was retried up to three times with the full timeout each, while the frontend aborts after one. Timeouts are no longer retryable.
- **Admin AJAX handlers crashed on non-JSON replies** (`0` / `-1` from admin-ajax on an expired session), leaving buttons stuck on "Testing...". All handlers now guard the response shape and render messages as text.
- **Session cache keys could collide** (base64 with `+/=` stripped). Keys now use `encodeURIComponent` directly.
- The summary box was injected into RSS search feeds (`is_feed()` checks added).
- `ensure_logs_table()` wrote a dynamic property (deprecated in PHP 8.2+) that did not reset the analytics table check; it now calls `Analytics::reset_table_check()`.
- Deactivate/reactivate stopped the scheduled log purge; `activate()` reschedules it when auto-purge is enabled.
- Uninstall now also removes the `riviantrackr_version` option, the per-user analytics filter preference, and the scheduled purge event.
- The "Default CSS Reference" modal documented a class that does not exist (`.riviantrackr-summary-content`); it now shows the actual `assets/riviantrackr.css`.

### Security
- **The analytics-page auto-purge form copied the wp-config.php API key into the database.** Its hidden fields echoed the full options array, including the constant-substituted key, and saving stored the secret in `wp_options`. The key fields are no longer echoed, `sanitize_options()` keeps the stored key when the field is absent, and a key defined via `RIVIANTRACKR_ANTHROPIC_API_KEY` is never written to the database.
- **Removed the no-op debug-log redaction filter.** `http_api_debug` passes request args by value, so `redact_api_key_in_debug()` never redacted anything. Documentation no longer claims it.
- Shortcode `color` / `font_color` attributes are validated with `sanitize_hex_color()` before being placed in `style` attributes.
- The analytics "hide zero-result queries" link is nonce-protected before persisting the preference to user meta.
- The plugin-page CSP `img-src` now allows `*.gravatar.com` and `s.w.org` so the admin bar avatar and emoji fallbacks load.

### Changed
- **Search result pages are no longer page-cacheable while the plugin is enabled.** The bot challenge token (10-minute lifetime) and REST nonce are rendered into the page; a cached copy served later handed every visitor a 403. `template_redirect` now defines `DONOTCACHEPAGE` and sends `nocache_headers()` on search pages.
- The REST preconnect hint is added through `wp_resource_hints` instead of an `echo` inside `wp_enqueue_scripts`.
- `WP_Query` for the AI context sets `no_found_rows`, skips meta/term cache fills, ignores sticky posts, and strips shortcodes from post content before truncation.
- Summary widget inline styles moved into `assets/riviantrackr.css` (`.riviantrackr-summary-inner`, `.riviantrackr-feedback*`, `.riviantrackr-disclaimer`, `.riviantrackr-error`, `.riviantrackr-skeleton-status`) so Custom CSS overrides work without `!important`.
- Design tokens cleaned up in both stylesheets: unused variables removed, `--rtg-radius-pill` is `20px` per the design system (with `--rtg-radius-xs: 4px` for what was actually using it), `--rtg-text-muted` is `#9ca3af`, modal overlay/shadow and the info result colors follow CLAUDE.md, duplicated hardcoded button rules use the tokens, dead selectors removed.
- Accessibility: loading state uses `aria-busy` and a status paragraph outside the `aria-hidden` skeleton; error paragraphs no longer stack `role="alert"` inside the polite live region; the CSS reference modal has `role="dialog"`, `aria-modal`, focus trap and focus return; the advanced-settings disclosure has `aria-expanded`/`aria-controls`; admin result containers are live regions; log-row checkboxes have accessible names and the select-all box reflects partial selection; trending icons are `aria-hidden`; sources toggle and trending links have `:focus-visible` styles; a `<noscript>` notice replaces the endless spinner; jQuery slide animations respect `prefers-reduced-motion`.
- Font Awesome detection for the trending widget uses `document.fonts.check()` (falls back to computed style) and re-checks once when fonts are ready.
- The frontend uses the server's own message for 429/403 responses instead of a hardcoded string. Unused `errorCodes` entries and the unused `window.riviantrackrShowFeedback` global were removed.
- Dropped the `languages/` stub and `Domain Path` header: the plugin has never wrapped strings in translation functions, and the `.pot` was an empty placeholder.
- `package.json` gained `build`, `build:js`, `build:css` scripts for regenerating the minified assets.

### Added
- **Reasoning Effort setting** (AI Configuration → Low / Medium / High, default Low). Sent as `output_config.effort` to models that accept it (Claude Opus 4.5+, Sonnet 4.6+, Fable/Mythos); Haiku ignores it. Claude Sonnet 5 and Opus 5 think by default and those tokens count against Max Response Tokens, so Low keeps search summaries fast, cheap, and complete. Changing it invalidates the summary cache.
- **Structured outputs.** On models documented as supporting `output_config.format` (Opus 4.1/4.5/4.8/5+, Sonnet 5+, Haiku 4.5+, Fable/Mythos) the response is constrained to the plugin's JSON schema, so brace-extraction parse failures go away. If a model returns HTTP 400 for `output_config`, the request is retried once without it and the model is remembered as unsupported for a day.
- `stop_reason: "refusal"` is mapped to a content-policy message instead of the generic "not available".
- `claude-opus-5` added to the fallback model list; `RIVIANTRACKR_DEFAULT_MODEL` (`claude-opus-5`) is used when no model has been saved.
- `ApiHandler::test_anthropic_key()` is the single API key tester (the duplicate in the main file delegates to it).

## [2.0.1] - 2026-07-13

### Fixed
- **Every summary request failed with HTTP 400 on Claude 4.6+ models.** The assistant-turn `{` prefill introduced in 2.0.0 is rejected by Sonnet 5, Sonnet 4.6, and Opus 4.6/4.7/4.8 — last-turn prefills return a 400 on those models, so all generations failed within milliseconds. The prefill (and its brace-restore in response normalization) is removed; JSON output is requested via the system prompt and recovered through the existing brace-extraction fallback, as in pre-2.0 releases.

### Changed
- **API errors now surface their real cause in the analytics log.** `get_ai_data_for_search()` previously collapsed every API failure to "The AI service encountered an error." — which hid this bug. The ApiHandler's user-safe error messages (HTTP 400 / rate limit / auth / overload) now pass through, and the 400 message names the provider and points at model/settings.

## [2.0.0] - 2026-07-13

### Removed
- **OpenAI support.** The plugin is now Anthropic Claude only. The provider selector, OpenAI API key field (`api_key` option and `RIVIANTRACKR_API_KEY` constant), OpenAI model fetching/caching, the reasoning-models setting, and `ApiHandler::call_openai()` / `is_reasoning_model()` are all removed. A one-time migration renames `show_openai_badge` to `show_badge`, drops the removed option keys and the OpenAI model cache, clears any non-Claude model selection, and bumps the summary cache namespace. The badge CSS classes are renamed from `.riviantrackr-openai-*` to `.riviantrackr-ai-*` (update any custom CSS targeting them).

### Fixed
- **Truncated responses were misreported as parse failures.** A Claude response cut off at the `max_tokens` limit produces invalid JSON, which surfaced as "Could not parse AI response. The service may be experiencing issues." — hiding the real cause. `parse_ai_content()` now checks the finish reason on decode failure and reports "The AI response was truncated… Increase the Max Response Tokens setting." instead, and logs the first 2000 chars of the raw content when `WP_DEBUG` is on.

### Added
- **Assistant prefill for guaranteed JSON.** Anthropic requests now prefill the assistant turn with `{`, the Claude-native equivalent of OpenAI's `response_format: json_object` — the model continues with pure JSON instead of possibly wrapping it in prose or markdown fences. The brace is restored during response normalization.
- **Admin bypass for anti-abuse throttles.** Logged-in users with `manage_options` skip bot detection, the honeypot/JS-challenge checks, per-IP rate limiting, and the 5-minute duplicate-query throttle on the `/summary` endpoint, so site owners can test repeatedly without self-inflicted 429s (and without risking the progressive 24-hour IP ban).

### Changed
- **Default Max Response Tokens raised from 1,500 to 4,000.** The old default was tuned for the OpenAI path and routinely truncated Claude's summary-plus-sources JSON.

## [1.5.1] - 2026-06-12

### Fixed
- **Fatal error when saving settings with a cache-invalidating change.** `sanitize_options()` still called `$this->bump_cache_namespace()`, a method that moved to `CacheManager::bump_namespace()` in the v1.2.0 class refactor — so changing the model, the sources toggle, or max tokens on the settings page crashed with an undefined-method error instead of saving. The call now goes through the cache manager. Latent since v1.2.0; surfaced when re-saving settings after the v1.5.0 theme update.

## [1.5.0] - 2026-06-12

### Changed
- **Restyled to the "ink & brass" dark theme**, matching the sitewide refresh on riviantrackr.com. Navy/slate values in the design tokens, skeleton shimmer, spinner track, and OpenAI-style badge are replaced with near-neutral charcoal surfaces (`#16191e` card, `#121418` deep, `#3a3e45` borders/inputs) and warm-neutral text (`#ece9e4`); star-empty becomes `#2c2f34`. The gold accent and the signature brand gradient are unchanged; `--rtg-accent-hover` now uses the spec hover value `#ffbe4a`.
- **Color setting defaults updated** (background, text, border) in the settings page and the inline CSS generator fallbacks. Installs with previously saved custom colors keep their saved values until reset on the settings page.
- Updated `CLAUDE.md` dark-theme primitives to document the new palette.

## [1.4.3] - 2026-05-25

### Changed

- **WordPress 7.0 compatibility** — Tested and confirmed compatible with WordPress 7.0. Bumped `Tested up to` header to 7.0. No code changes required; the plugin uses no APIs affected by 7.0's dev notes (no block registration, no `the_author_posts_link` / `get_the_author_link` calls, no Abilities or Sync providers).

---

## [1.4.2] - 2026-05-14

### Added

- **Dynamic Anthropic model list** — The "Refresh Models" button on the AI Configuration settings page now calls Anthropic's `/v1/models` endpoint when the active provider is Anthropic, replacing the previous static "pre-configured" message. The fetched list is cached per-provider in `riviantrackr_anthropic_models_cache` with the same 7-day TTL as the OpenAI cache. The original hardcoded curated list is retained as a fallback when the API is unreachable or no key is set.
- **Per-provider "Last updated" timestamp** — The timestamp shown below the Refresh Models button now reflects whichever provider's cache is active (OpenAI or Anthropic).

### Changed

- **Anthropic model dropdown** — Now displays the dated snapshot IDs returned by Anthropic's API (e.g. `claude-sonnet-4-5-20250929`) instead of the short aliases. Previously saved aliases are preserved as valid options in the dropdown.

---

## [1.4.1] - 2026-03-16

### Added

- **Minified CSS and JS assets** — All frontend and admin stylesheets and scripts now ship with pre-built `.min.css` / `.min.js` versions generated by clean-css and terser. Production sites load the minified files by default (~35% smaller), while developers can load the unminified originals by setting `SCRIPT_DEBUG` to `true` in `wp-config.php`.

### Changed

- **Version bumped to 1.4.1** — Performance release with minified assets.

---

## [1.4.0] - 2026-03-06

### Added

- **RivianTrackr Design System** — Introduced CSS custom properties (`--rtg-*`) across both frontend and admin stylesheets, creating a centralized, token-based design system sourced from the RivianTrackr branding guide.
- **Dark theme tokens (frontend)** — `riviantrackr.css` now defines `:root` variables for accent (`#fba919`), backgrounds (`#121e2b`, `#0f1a26`), text (`#e5e7eb`), border (`#374151`), star rating colors, border radii, and font stacks. All component styles reference these tokens.
- **Light theme tokens (admin)** — `riviantrackr-admin.css` now defines `:root` variables for action colors (`#0071e3`), success/error/warning/info states, text hierarchy, backgrounds, border radii, shadows, and font stacks. All admin component styles reference these tokens.
- **Brand gradient on provider badge** — The AI provider badge mark now uses the full RivianTrackr signature gradient (12-color sweep) instead of the previous green-to-blue gradient.
- **`prefers-reduced-motion` support** — Both frontend and admin stylesheets now disable all transitions and animations when the user has reduced motion enabled, improving accessibility.

### Changed

- **Accent color updated** — Frontend accent changed from green (`#22c55e`) to brand gold (`#fba919`) for spinner, source links, hover states, and focus rings.
- **Default border color updated** — Changed from `#94a3b8` to `#374151` across PHP defaults, admin form placeholders, and generated CSS to match the dark theme design system.
- **Widget border-radius** — Updated from `10px` to `12px` to match `--rtg-radius-card` token.
- **Plugin author** — Updated to "RivianTrackr".

---

## [1.3.5] - 2026-03-01

### Added

- **"Hide zero-result queries" filter on Analytics dashboard** — A toggle button at the top of the Analytics page that filters zero-result entries out of all views (overview stats, daily stats, top queries, top errors, and recent events). Non-destructive — data stays in the database and reappears when the filter is toggled off. Uses the existing indexed `results_count` column so there is no performance cost. Useful for seeing clean analytics without spam, while preserving the ability to analyze content gaps when needed.
- **Sticky filter preference** — The "Hide zero-result queries" toggle now remembers your choice via WordPress user meta. Once enabled, the filter stays active across page loads, navigation, and browser sessions — no need to re-enable it every time you visit Analytics. Each admin user's preference is stored independently.

---

## [1.3.4] - 2026-03-01

### Fixed

- **Automatic cleanup of old off-topic log entries on upgrade** — All junk queries logged before the off-topic filter existed are now automatically purged from the `riviantrackr_logs` and `riviantrackr_feedback` tables the first time an admin loads a page after upgrading. Uses a stored `riviantrackr_version` option to ensure it runs exactly once.
- **"Scan & Remove Spam" button now also purges off-topic queries** — The existing spam purge in Settings previously only checked for spam patterns and SQL injection. It now also applies the off-topic relevance filter, so clicking the button removes old entries like "scrub daddy" or "costco credit card" that aren't spam per se but don't match any configured relevance keywords.
- **Session cache hit endpoint now filters off-topic queries** — The `/log-session-hit` endpoint (`rest_log_session_cache_hit()`) had no off-topic check, allowing junk queries to be logged to analytics via this path. A bot could POST directly to the endpoint, or the frontend JS could log a cached error response. The endpoint now applies `is_off_topic_query()` and silently drops matching queries.
- **Off-topic errors now cached in browser** — The frontend JS previously only cached `no_results` errors in `sessionStorage`. Off-topic errors were not cached, so navigating back to the same off-topic search page would re-fire the REST endpoint every time. Off-topic errors are now cached alongside no-results, preventing repeat requests.
- **Cached off-topic responses skip session cache hit logging** — When serving a cached off-topic error from `sessionStorage`, the JS no longer calls `logSessionCacheHit()`, preventing the junk query from reaching the `/log-session-hit` endpoint.

---

## [1.3.3] - 2026-03-01

### Fixed

- **Off-topic queries no longer logged to analytics** — The off-topic filter in `rest_get_summary()` was calling `log_search_event()` before returning the error response, which meant every junk query like "scrub daddy" or "costco credit card" still ended up in the analytics database even though it was correctly blocked from reaching the AI API. The log call has been removed so off-topic queries are silently dropped — they never appear in analytics, never consume database space, and never clutter the admin dashboard.

---

## [1.3.2] - 2026-03-01

### Fixed

- **Server-side no-results logging now validates input** — The `log_no_results_search()` method (hooked on `template_redirect`) previously logged every zero-result search query directly to the database with no validation. Bots could pollute analytics simply by requesting `/?s=junk` without ever executing JavaScript, completely bypassing all REST endpoint protections (bot detection, spam filtering, off-topic checks, rate limiting). The method now applies bot detection via `is_likely_bot()`, input validation via `validate_search_query()` (covers SQL injection, spam patterns, blocklist, length limits), and `is_off_topic_query()` before logging — matching the same checks enforced on the REST API summary endpoint.

---

## [1.3.1] - 2026-03-01

### Added

- **Relevance Keywords (off-topic filter)** — New admin setting that lets you define the topics your site covers (e.g. "rivian, r1t, r1s, ev, electric vehicle"). Search queries that don't match any keyword are rejected early with a friendly message, preventing completely unrelated searches (e.g. "costco credit card", "msi monitor amazon portugal") from cluttering analytics and wasting server resources. Comma or newline separated, case-insensitive. Leave empty to allow all queries (backwards compatible).
- **`RIVIANTRACKR_ERROR_OFF_TOPIC` error code** — New error code returned when a query is blocked by the relevance filter, enabling frontend-specific handling.
- **`InputValidator::is_off_topic_query()` method** — Checks queries against configured relevance keywords using both substring and exact word matching.
- **12 unit tests** for off-topic detection covering keyword matching, case insensitivity, comma/newline separators, empty configuration, and various junk query patterns.

---

## [1.3.0] - 2026-03-01

### Added

- **Progressive IP penalties** — Repeat rate-limit offenders now face escalating bans instead of a simple 60-second reset. 2nd strike within 10 minutes triggers a 5-minute ban, 3rd within 30 minutes triggers a 30-minute ban, and 4th+ within 1 hour triggers a 24-hour ban. Strike history tracked via WordPress transients.
- **Mandatory JS challenge token** — New "Require JavaScript Challenge" setting (enabled by default) makes the `bt`/`bts` bot challenge token mandatory on the summary endpoint. Previously, bots could bypass the check by omitting the token parameters entirely.
- **Honeypot field** — A hidden input field is rendered in the search summary widget. Legitimate JavaScript-driven requests send it empty; bots that auto-fill form fields are instantly rejected with a 403.
- **Duplicate query throttling** — The same search query from the same IP is blocked for 5 minutes to prevent bots from repeatedly hammering the AI API with identical requests. Legitimate users are unaffected since the frontend session cache already handles repeat queries.
- **RateLimiter unit tests** — 27 new PHPUnit tests covering progressive IP bans, duplicate query detection, bot token validation, IP rate limiting, log rate limiting, and client IP resolution.

### Changed

- **Version bumped to 1.3.0** — Security and spam prevention release.

---

## [1.2.0] - 2026-02-28

### Added

- **PHP namespaces** — All extracted classes are namespaced under `RivianTrackr\AISearchSummary` with a PSR-4 style autoloader (`includes/class-autoloader.php`). This prevents class name collisions and aligns with modern PHP standards.
- **PHPUnit test suite** — 87 unit tests covering SQL injection detection, spam filtering, custom CSS sanitization, text truncation, API content parsing, cache key generation, reasoning model detection, prompt building, and autoloader resolution. Tests run without a full WordPress environment using lightweight function stubs.

### Changed

- **Architecture: Monolithic file split into focused classes** — The 6,300-line main plugin file has been refactored into five dedicated component classes:
  - `includes/class-api-handler.php` — OpenAI and Anthropic API communication, prompt construction, retry logic with exponential backoff, response normalization, and content parsing.
  - `includes/class-cache-manager.php` — Server-side transient caching, namespace-based invalidation, cache key generation, and OpenAI model list caching.
  - `includes/class-rate-limiter.php` — IP-based rate limiting with atomic locking, global AI call rate limiting, bot detection heuristics, JS challenge token validation, and client IP resolution.
  - `includes/class-analytics.php` — Search event logging, user feedback recording, log purging, feedback statistics, success rate calculation, and trending keyword queries.
  - `includes/class-input-validator.php` — Search query validation, SQL injection pattern detection, spam/scanner probe filtering, custom CSS sanitization (XSS prevention), and smart text truncation.
- **Main plugin file delegates to components** — All extracted logic in the main `RivianTrackr_AI_Search_Summary` class now delegates to the namespaced component classes. Hooks, admin UI rendering, and WordPress integration remain in the main class.
- **Version bumped to 1.2.0** — First minor release with architectural improvements and test coverage.

---

## [1.1.0.3] - 2026-02-28

### Improved

- **AI prompts: Site-aware branding** — System prompts for both OpenAI and Anthropic now instruct the AI to identify as the site's built-in search assistant rather than a generic external AI. Responses naturally attribute information to the site's coverage (e.g. "Based on RivianTrackr's reporting…") and use the site name in fallback messages, making summaries feel native to the platform.

---

## [1.1.0.2] - 2026-02-20

### Security

- **CSS sanitizer: SVG data URIs blocked** — Removed `svg+xml` from the allowed MIME types in `data:` URI validation within the custom CSS sanitizer. SVG data URIs can contain embedded JavaScript and were a potential XSS vector.
- **CSV export: Formula injection prevention** — Cell values in analytics CSV exports that begin with `=`, `+`, `-`, or `@` are now prefixed with a tab character to prevent formula injection when opened in spreadsheet applications.
- **Frontend requests: Nonce authentication** — The frontend JavaScript now sends the `X-WP-Nonce` header with summary and session cache hit REST API requests, ensuring requests are authenticated against the logged-in user's session.
- **Source links: `rel="noopener noreferrer"` added** — External source article links now include `rel="noopener noreferrer"` to prevent reverse tabnapping attacks.
- **Model fetch: `wp_safe_remote_get` used** — The OpenAI model list fetch now uses `wp_safe_remote_get()` instead of `wp_remote_get()`, enforcing WordPress's safe URL validation.

### Fixed

- **`hex_to_rgb()` return type corrected** — The method's return type declaration was `array` but it could return `false` on invalid input. Now correctly typed as `array|false`.
- **Duplicate ABSPATH guard removed** — Removed a redundant `defined('ABSPATH')` check that was dead code.

---

## [1.1.0.1] - 2026-02-20

### Added

- **Model column in analytics** — The Recent Events table now displays which AI model (e.g. `gpt-4o`, `claude-sonnet-4-6`) was used for each search, making it easy to compare response times across models and providers.
- **`ai_model` column in logs table** — New nullable `ai_model` column stores the model ID for each logged search event. Existing rows show a dash. The column is added automatically via `dbDelta` on upgrade.

---

## [1.1.0] - 2026-02-20

### Added

- **Anthropic Claude support** — Choose between OpenAI and Anthropic as the AI provider for generating search summaries. New "AI Provider" dropdown in Settings lets you switch between providers.
- **Anthropic API key management** — Separate API key field for Anthropic with "Test Connection" validation. Supports secure storage via `RIVIANTRACKR_ANTHROPIC_API_KEY` constant in wp-config.php.
- **Anthropic Claude model selection** — Curated list of Claude models: Claude Haiku 4.5, Claude Sonnet 4.5, Claude Sonnet 4.6, Claude Opus 4.5, and Claude Opus 4.6.
- **Provider-aware "Powered by" badge** — The attribution badge dynamically displays "Powered by OpenAI" or "Powered by Anthropic" based on the active provider.

### Changed

- **Settings page restructured** — Getting Started section now includes an AI Provider selector with conditional API key fields that show/hide based on the selected provider.
- **Cache keys include provider** — Switching providers automatically invalidates cached summaries to prevent serving stale responses from a different AI.
- **CSP header updated** — `connect-src` now includes `api.anthropic.com` alongside `api.openai.com`.
- **Debug log redaction** — API key redaction now covers both OpenAI Bearer tokens and Anthropic `x-api-key` headers.
- **Plugin description updated** — Reflects support for both OpenAI and Anthropic Claude.
- **Version bumped to 1.1.0** — First minor release with multi-provider support.

---

## [1.0.7.1] - 2026-02-20

### Changed

- **Plugin sidebar renamed to "AI Search"** — Simplified the admin sidebar menu label and submenu page titles from "RivianTrackr AI Search Summary" to "AI Search" for a cleaner look. Plugin header updated to "AI Search Summary".

### Removed

- **Legacy SQL migration script deleted** — Removed `sql/transfer-logs-feedback.sql` (manual phpMyAdmin script for transferring data from old `searchlens_` tables).
- **Automatic migration code removed** — Removed the `maybe_run_migrations()` method and its `admin_init` hook. This handled legacy schema upgrades (adding columns, creating the feedback table, and renaming `searchlens_` tables/options/transients/cron hooks to `riviantrackr_` prefix). No longer needed now that the rename migration has been applied.

---

## [1.0.7] - 2026-02-20

### Changed

- **Plugin renamed from "SearchLens AI" to "RivianTrackr AI Search Summary"** — Display name, slug, text domain, and main PHP file updated.
- Main plugin file renamed from `searchlens-ai.php` to `riviantrackr-ai-search-summary.php`
- Text domain changed from `searchlens-ai` to `riviantrackr-ai-search-summary`
- Admin menu label changed from "SearchLens AI" to "RivianTrackr AI Search Summary"
- Dashboard widget title updated to "RivianTrackr AI Search Summary"
- Translation template renamed to `riviantrackr-ai-search-summary.pot`
- **All internal prefixes replaced: `searchlens` → `riviantrackr`** — Every internal identifier updated:
  - Constants: `SEARCHLENS_*` → `RIVIANTRACKR_*`
  - Options: `searchlens_options` → `riviantrackr_options`
  - Transients: `searchlens_*` → `riviantrackr_*`
  - Database tables: `wp_searchlens_logs` / `wp_searchlens_feedback` → `wp_riviantrackr_logs` / `wp_riviantrackr_feedback`
  - AJAX actions: `wp_ajax_searchlens_*` → `wp_ajax_riviantrackr_*`
  - REST namespace: `searchlens/v1` → `riviantrackr/v1`
  - Shortcode: `[searchlens_trending]` → `[riviantrackr_trending]`
  - Cron hook: `searchlens_daily_log_purge` → `riviantrackr_daily_log_purge`
  - Script/style handles: `searchlens-*` → `riviantrackr-*`
  - JS globals: `SearchLensAI` → `RivianTrackrAI`, `SearchLensAdmin` → `RivianTrackrAdmin`
  - Widget class: `SearchLens_Trending_Widget` → `RivianTrackr_Trending_Widget`
  - CSS classes: `.searchlens-*` → `.riviantrackr-*`
  - Asset filenames: `searchlens.js`/`searchlens.css`/`searchlens-admin.css` → `riviantrackr.js`/`riviantrackr.css`/`riviantrackr-admin.css`
- **Automatic data migration on upgrade** — Activating 1.0.7 renames the old database tables and migrates stored options/transients/cron hooks from `searchlens` to `riviantrackr` prefix. No manual SQL needed.
- GitHub repository renamed from `searchlens-ai` to `riviantrackr-ai-search-summary`

---

## [1.0.6] - 2026-02-16

### Changed

- **Plugin renamed from "AI Search Summary" to "SearchLens AI"** — Display name, slug, text domain, and main PHP file updated to comply with WordPress plugin directory naming guidelines.
- Main plugin file renamed from `ai-search-summary.php` to `searchlens-ai.php`
- Text domain changed from `aiss-ai-search-summary` to `searchlens-ai`
- Admin menu label changed from "AI Search" to "SearchLens AI"
- Dashboard widget title updated to "SearchLens AI"
- Translation template renamed to `searchlens-ai.pot`
- **All internal prefixes replaced: `aiss` → `searchlens`** — WordPress plugin review flagged "ai" as a common-word prefix. Every internal identifier has been updated to use the unique `searchlens` prefix:
  - Constants: `AISS_*` → `SEARCHLENS_*`
  - Options: `aiss_options` → `searchlens_options`
  - Transients: `aiss_*` → `searchlens_*`
  - Database tables: `rv_aiss_logs` / `rv_aiss_feedback` → `rv_searchlens_logs` / `rv_searchlens_feedback`
  - AJAX actions: `wp_ajax_aiss_*` → `wp_ajax_searchlens_*`
  - REST namespace: `aiss/v1` → `searchlens/v1`
  - Shortcode: `[aiss_trending]` → `[searchlens_trending]`
  - Cron hook: `aiss_daily_log_purge` → `searchlens_daily_log_purge`
  - Script/style handles: `aiss-*` → `searchlens-*`
  - JS globals: `AISSearch` → `SearchLensAI`, `AISSAdmin` → `SearchLensAdmin`
  - Widget class: `AISS_Trending_Widget` → `SearchLens_Trending_Widget`
  - CSS classes: `.aiss-*` → `.searchlens-*`
  - Asset filenames: `aiss.js`/`aiss.css`/`aiss-admin.css` → `searchlens.js`/`searchlens.css`/`searchlens-admin.css`
- **Automatic data migration on upgrade** — Activating 1.0.6 renames the old database tables and migrates stored options/transients to the new prefix. No manual SQL needed.
- **Moved inline `<script>` and `<style>` tags to enqueued files** — WordPress plugin review requires using `wp_enqueue_script`/`wp_enqueue_style` instead of inline HTML tags:
  - Extracted admin settings JavaScript into `assets/searchlens-admin.js`
  - Extracted trending widget Font Awesome detection into `assets/searchlens-trending.js`
  - Replaced trending widget inline `<style>` with `wp_add_inline_style()`

---

## [1.0.5.4] - 2026-02-16

### Fixed

- **Block scanner probe queries** — Searches containing CGI/server environment variable names (`QUERY_STRING`, `DOCUMENT_ROOT`, `SERVER_NAME`, `REMOTE_ADDR`, etc.) are now rejected by the spam filter. Vulnerability scanners and bots commonly send these as search terms to test for server information disclosure; blocking them prevents wasted OpenAI API calls and keeps analytics clean.

---

## [1.0.5.3] - 2026-02-16

### Changed

- **Credits are now opt-in** — The OpenAI badge, source links, and feedback buttons are now disabled by default. You must turn them on in **Settings → SearchLens AI** to display them, in line with WordPress plugin directory guidelines.

### Fixed

- **Direct file access protection** — Added security checks to plugin files (`searchlens-ai.php`, `index.php`, `uninstall.php`) so they cannot be loaded directly outside of WordPress.
- **Safer database queries** — The bulk-delete query now uses WordPress's built-in escaping for the table name instead of inserting it directly, eliminating a plugin-check warning.
- **Code-quality warnings** — Corrected the placement and scope of several code-analysis suppression rules so they cover the intended lines and resolve false-positive warnings.

---

## [1.0.5] - 2026-02-12

### Security

- **Custom CSS: Strict data URI MIME type filtering** - The `url()` sanitizer now only allows `data:image/(png|jpeg|gif|webp|svg+xml)` URIs. Previously `data:text/html` and `data:application/javascript` could bypass the filter, enabling potential XSS via the custom CSS field.
- **Rate limiting: Atomic locking** - The per-IP rate limiter now acquires a short-lived transient lock before read-modify-write, preventing concurrent requests from bypassing the limit via a race condition.
- **Rate limiting: Feedback/logging endpoints** - The `/log-session-hit` and `/feedback` REST endpoints now enforce a separate, higher-threshold rate limit (60/min) to prevent database flooding. Previously these had no rate limit.
- **Hashing: MD5 replaced with SHA-256** - All internal hashing (cache keys, IP rate-limit keys, feedback IP hashes) now uses `hash('sha256', …)` instead of `md5()`.
- **CSP header on admin pages** - Plugin admin pages now include a `Content-Security-Policy` header restricting scripts, styles, images, and connect sources to same-origin plus required OpenAI API domain.
- **API key redaction in debug logs** - A filter on `http_api_debug` automatically strips the `Authorization: Bearer` header from OpenAI request data before WordPress writes it to `WP_DEBUG_LOG`.
- **JS challenge token for bot detection** - The frontend now sends an HMAC-based challenge token (`bt`/`bts` parameters) with summary requests. Tokens are generated server-side in `enqueue_frontend_assets()` and validated in the REST permission check, blocking bots that skip JavaScript execution. Valid for 10 minutes.
- **Bulk delete nonce moved to `wp_localize_script`** - The AJAX nonce for bulk-deleting log entries is no longer embedded as an HTML `data-` attribute. It is now passed via `wp_localize_script` through the `AISSAdmin` JavaScript object, reducing DOM exposure of security tokens.

### Added

- **Privacy: Anonymize Search Queries setting** - New toggle in Advanced settings. When enabled, search queries are stored as SHA-256 hashes instead of plain text, preserving aggregate analytics while removing personally-identifiable search history.
- **Privacy: GDPR Purge Existing Queries** - One-click button to retroactively replace all stored search query text with SHA-256 hashes. Includes confirmation prompt and AJAX handler with full security checks.

### Improved

- **Settings: Collapsible Advanced section** - The Advanced settings section is now hidden by default behind a "Show Advanced Settings" toggle button. Expanding it reveals a warning banner advising caution, reducing the chance of accidental changes to sensitive options like reasoning models, spam blocklists, and data retention.

---

## [1.0.4.1] - 2026-02-11

### Improved

- **CSS: Tablet responsive breakpoint** - Added missing `768px` media query so the summary widget properly adapts for tablets in portrait mode (641-768px range was previously unstyled).
- **CSS: Keyboard focus states** - Feedback buttons now show a visible `focus-visible` outline for keyboard navigation, improving WCAG 2.1 AA compliance.
- **JS: Sources toggle persistence** - The expanded/collapsed state of the sources list is now remembered across page navigations via `localStorage`.
- **JS: Slow response progress indicator** - When the AI summary takes longer than 10 seconds, progressive status messages ("Still working...", "Taking a bit longer...", "Almost there...") are shown inside the skeleton loader with an ARIA live region for screen reader support.
- **Analytics: Query cell tooltips** - Truncated search queries in the Top Queries and Recent Events tables now show the full text on hover via `title` attribute.
- **Analytics: Bulk delete for event logs** - Admins can now select multiple log entries via checkboxes and delete them in bulk with a single click, with confirmation prompt and AJAX handling.
- **Analytics: Badge threshold legend** - A visual legend above the Daily Stats table now explains what the green/yellow/red badge colors mean for AI success, cache hit, and helpfulness rates.
- **Code: Named constants for magic numbers** - Extracted 18 hardcoded values (pagination sizes, validation limits, badge thresholds, table size threshold, CSS max length, error max length) into named `define()` constants for maintainability.
- **Code: PHP type hints** - Added parameter and return type declarations to 27 core functions, improving IDE support and leveraging the existing `declare(strict_types=1)`.

---

## [1.0.4] - 2026-02-11

### Improved

- **Analytics: Top Queries pagination** - The "Top Search Queries" section now supports pagination instead of being hard-limited to 20 entries, allowing admins to browse the full list of unique queries on high-traffic sites.
- **Analytics: Top Errors pagination** - The "Top AI Errors" section now supports pagination instead of being hard-limited to 10 entries.
- **Analytics: Shared pagination component** - All analytics table pagination (recent events, top queries, top errors) now uses a single reusable method with consistent styling and cross-section state preservation.
- **Options cache: Hook-based invalidation** - The in-memory options cache is now automatically flushed via the `update_option_{$option}` WordPress hook, ensuring consistency even when options are updated outside of the settings sanitization flow.
- **JS error codes from PHP** - Error code constants (`AISS_ERROR_NO_RESULTS`, etc.) are now passed to the frontend via `wp_localize_script`, replacing the previously hardcoded string comparison in the JavaScript.
- **API retry diagnostics** - When API retries occur, the attempt count is now included in error messages logged to analytics and in debug log entries, helping admins diagnose intermittent API issues.

---

## [1.0.3] - 2026-02-10

### Fixed

- **Analytics: No-results searches now logged** - Searches that match zero posts are now recorded in analytics, giving admins complete visibility into what users are searching for.
- **Analytics: Premature logging removed** - Empty/missing queries are no longer logged to the analytics table; the not-configured state now sanitizes the query before logging.
- **CSV Export: Date range validation** - Exporting with a start date after the end date now shows a clear error instead of silently returning an empty file.
- **Cache: Content length included in cache key** - Changing the "Content Length Per Post" setting now correctly invalidates stale cached summaries instead of serving results generated with the old length.
- **Cache: Corrupted transients cleaned up** - If a cached transient contains invalid JSON, it is now deleted immediately rather than failing on every subsequent request until expiry.
- **Rate Limiting: retry_after header clamped** - The `Retry-After` value in 429 responses is now guaranteed to be at least 1 second, preventing invalid zero or negative values.
- **Rate Limiting: Session cache logging decoupled** - The `/log-session-hit` endpoint now uses a lightweight permission check (bot detection only) so browser cache hit logging no longer counts against the per-IP rate limit.
- **Settings: Auto-purge form preserves post types** - Saving the automatic purging settings no longer clears selected post types (array-valued options were dropped by the hidden-field loop).

### Changed

- Removed redundant `get_options()` calls in the summary REST endpoint for cleaner code flow.

---

## [1.0.1] - 2026-02-09

### Added

- **Post Type Filtering** - Choose which post types (posts, pages, custom types) are included in AI search results. When none are selected, all public post types are included (previous default behavior).
- **Max Sources Displayed** - Configure how many source articles appear beneath the AI summary (1–20, default 5). Previously hardcoded to 5.
- **Content Length Per Post** - Control how many characters of post content are sent to the AI per article (100–2,000, default 400). Allows tuning the balance between summary quality and API token cost.
- **Preserve Data on Uninstall** - Option in Advanced settings to keep all plugin data (settings, analytics logs, feedback) when the plugin is deleted, so data is retained if you reinstall later.

---

## [1.0.0] - 2026-02-08

### Added

#### Core Features
- AI-powered search summaries using OpenAI's GPT models (GPT-4o, GPT-4, GPT-3.5-turbo)
- Support for OpenAI reasoning models (o1, o3) with configurable toggle
- Non-blocking async loading - search results display immediately while AI summary loads
- Collapsible sources section showing articles used for summary generation
- Smart content truncation for optimal API usage

#### Admin Interface
- Comprehensive settings page with organized sections
- API key validation with test connection button
- Dynamic model selection populated from OpenAI API
- Custom CSS editor with syntax highlighting
- Color theming (background, text, accent, border colors)

#### Analytics & Monitoring
- Full analytics dashboard with daily statistics
- Success rate and cache performance tracking
- Top search queries ranking
- Error analysis and tracking
- CSV export for logs, daily stats, and feedback
- WordPress dashboard widget for quick stats overview

#### Performance & Caching
- Multi-tier caching system (server-side transients + browser session cache)
- Configurable cache TTL (1 minute to 24 hours)
- Namespace-based cache invalidation
- Manual cache clear functionality
- Smart API usage - skips calls when no matching posts exist

#### Rate Limiting & Security
- IP-based rate limiting (configurable requests per minute)
- Global AI call rate limiting
- Bot detection to prevent unnecessary API calls
- Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, X-XSS-Protection)
- Secure API key storage via wp-config.php constant
- SQL injection prevention with prepared statements
- XSS prevention with proper output escaping
- Nonce verification for all admin actions

#### Widgets & Shortcodes
- Trending Searches sidebar widget with customizable appearance
- `[aiss_trending]` shortcode for embedding trending searches anywhere
- Configurable time periods, limits, colors, and titles

#### REST API
- `/wp-json/aiss/v1/summary` - Get AI summary for search queries
- `/wp-json/aiss/v1/log-session-hit` - Log frontend cache hits
- `/wp-json/aiss/v1/feedback` - Submit user feedback

#### Data Management
- Automatic log purging with configurable retention (7-365 days)
- Scheduled cleanup via WP-Cron
- GDPR-friendly design - no user identification stored
- IP hashing for feedback (not full IP storage)

#### User Experience
- Optional "Powered by OpenAI" badge
- Optional thumbs up/down feedback buttons
- Configurable sources display
- Responsive design
- Smooth loading animations

---

This is the first official release, consolidating all development work into a stable 1.0.0 version.
