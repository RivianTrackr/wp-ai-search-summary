=== RivianTrackr AI Search Summary ===
Contributors: josecastillo
Tags: search, ai, anthropic, claude, summary
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add AI-powered summaries to your WordPress search results using Anthropic Claude. Enhance search with intelligent, contextual summaries.

== Description ==

RivianTrackr AI Search Summary is a powerful WordPress plugin that adds AI-powered summaries to your search results using Anthropic Claude. Enhance your site's search experience with intelligent, contextual summaries that help users find what they're looking for faster.

= Core AI Functionality =

* **AI-Powered Search Summaries** - Generate intelligent summaries from matching posts using Anthropic Claude
* **Multiple Model Support** - Select from the live Anthropic model list (Claude Haiku, Sonnet, and Opus families)
* **Non-Blocking Search** - AI summaries load asynchronously without delaying normal search results
* **Smart Content Processing** - Automatic text truncation and HTML stripping for optimal API usage
* **Collapsible Sources** - Display the articles used to generate the summary with expandable source list

= Performance & Caching =

* **Multi-Tier Caching** - Server-side transient cache + browser session cache for optimal performance
* **Configurable Cache TTL** - Set cache duration from 1 minute to 24 hours
* **Cache Management** - Manual cache clear button and automatic namespace-based invalidation
* **Smart API Usage** - Automatically skips AI calls when no matching posts exist (saves costs)

= Analytics Dashboard =

* **Comprehensive Analytics** - Track daily statistics, success rates, and cache performance
* **Top Search Queries** - See what users are searching for most
* **Error Tracking** - Monitor and analyze API errors
* **CSV Export** - Export logs, daily stats, and feedback data for external analysis
* **WordPress Dashboard Widget** - Quick stats overview right on your dashboard

= Rate Limiting & Security =

* **IP-Based Rate Limiting** - Configurable requests per minute per IP address
* **Global AI Rate Limiting** - Control maximum AI calls per minute
* **Bot Detection** - Automatically skip AI processing for known bots
* **Security Headers** - X-Content-Type-Options, X-Frame-Options, Referrer-Policy, X-XSS-Protection
* **Secure API Key Storage** - Store API key via wp-config.php constant (recommended)

= Widgets & Shortcodes =

* **Trending Searches Widget** - Display popular search terms in your sidebar
* **[riviantrackr_trending] Shortcode** - Embed trending searches anywhere on your site

= Customization =

* **Color Theming** - Customize background, text, accent, and border colors
* **Custom CSS Editor** - Add your own styles
* **AI Provider Badge** - Optional "Powered by Anthropic" attribution badge
* **Feedback Buttons** - Optional thumbs up/down for user feedback on summaries

= Data Management =

* **Automatic Log Purging** - Configurable retention period (7-365 days) with scheduled cleanup
* **GDPR-Friendly** - No user identification stored, IP hashing for feedback
* **Database Optimization** - Efficient queries with proper indexing

= Third-Party Services =

This plugin connects to the Anthropic API to generate search summaries. When a user performs a search, the search query and relevant post content are sent to Anthropic's servers.

**Anthropic**

* **Service Provider:** Anthropic, PBC
* **API Endpoint:** api.anthropic.com
* **Terms of Service:** [Anthropic Terms of Service](https://www.anthropic.com/legal/consumer-terms)
* **Privacy Policy:** [Anthropic Privacy Policy](https://www.anthropic.com/legal/privacy)

Only the search query and excerpts from matching posts are sent to Anthropic. No personal user data is transmitted.

== Installation ==

1. Upload the `riviantrackr-ai-search-summary` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **RivianTrackr AI Search Summary > Settings**
4. Add your Anthropic API key
5. Click "Test Connection" to verify your API key works
6. Enable RivianTrackr AI Search Summary using the toggle
7. Configure additional settings as needed

= Secure API Key Configuration (Recommended) =

For maximum security, add your API key to `wp-config.php` instead of storing it in the database:

`define( 'RIVIANTRACKR_ANTHROPIC_API_KEY', 'sk-ant-your-anthropic-key-here' );`

Benefits:
* API key not stored in database (protected from SQL injection/database leaks)
* Key not visible in WordPress admin (protected from admin account compromise)
* Easier to manage across environments (staging/production)

Using environment variables:

`define( 'RIVIANTRACKR_ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') );`

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need an Anthropic API key. Sign up at the [Anthropic Console](https://console.anthropic.com/).

= How much does the AI API cost? =

Anthropic charges based on usage (tokens processed). Costs vary by model. The plugin includes multi-tier caching to minimize API calls and reduce costs.

= Will this slow down my search results? =

No! The plugin loads AI summaries asynchronously. Your normal search results appear immediately, and the AI summary loads in the background without blocking the page.

= Is my data sent to Anthropic? =

When a search is performed, the search query and excerpts from matching posts are sent to Anthropic for processing. No personal user data (names, emails, IPs) is sent. See Anthropic's privacy policy for how they handle data.

= Can I customize the appearance? =

Yes! The settings page includes color pickers for background, text, accent, and border colors. You can also add custom CSS for further customization.

= How does caching work? =

The plugin uses a multi-tier caching system:
1. **Server-side cache** - Stores AI responses as WordPress transients
2. **Browser cache** - Stores responses in sessionStorage for instant repeat searches

Cache duration is configurable from 1 minute to 24 hours.

= What models are supported? =

The model dropdown is populated live from the Anthropic API (Claude Haiku, Sonnet, and Opus families), with a curated fallback list when the API is unreachable.

= How do I see analytics? =

Go to **RivianTrackr AI Search Summary > Analytics** in your WordPress admin to view:
* Daily search statistics
* Success rates and cache performance
* Top search queries
* Error analysis
* Export data as CSV

= Is this plugin GDPR compliant? =

The plugin is designed with privacy in mind:
* No user IDs or personal data stored in logs
* IP addresses are hashed (not stored in plain text) for feedback
* Automatic log purging available
* Note: Search queries are logged for analytics - configure retention as needed

== Screenshots ==

1. AI-powered search summary displayed above search results
2. Settings page - Getting Started section with API key configuration
3. Settings page - Appearance customization with color pickers
4. Analytics dashboard showing search statistics and trends
5. Trending Searches widget in sidebar
6. WordPress dashboard widget with quick stats

== Changelog ==

= 2.1.0 =
* Fixed: "Show sources" and the feedback buttons did nothing when a summary was served from the browser session cache.
* Fixed: Repeating a search within five minutes returned a 429 even when the summary was already cached. The duplicate-query throttle now only applies to genuine cache misses.
* Fixed: Cached "no results" responses were logged as successful session cache hits, inflating the success rate.
* Fixed: Feedback errors (stale nonce, duplicate vote) were shown as "Thanks for your feedback".
* Fixed: The trending widget constructed a second plugin instance on every render, registering every hook twice.
* Fixed: An empty model selection (possible after the 2.0 migration) was sent to the API and failed with HTTP 400. A default model now applies.
* Fixed: Timed-out API requests were retried up to three times while the browser had already given up. Timeouts are no longer retried.
* Fixed: Admin AJAX buttons stuck on "Testing..." when admin-ajax returned a non-JSON reply (expired session).
* Fixed: Session cache keys could collide across different queries.
* Fixed: The summary box was injected into RSS search feeds.
* Fixed: A deprecated dynamic property write when repairing the analytics table.
* Fixed: Deactivating and reactivating the plugin stopped the scheduled log purge.
* Security: The analytics-page auto-purge form copied the wp-config.php API key constant into the database on save. The key fields are no longer echoed, and a key defined in wp-config.php is never stored.
* Security: The debug-log API key redaction filter never actually redacted anything and has been removed (WordPress passes request args by value). Keep the key in wp-config.php if debug logging is on.
* Security: Shortcode color attributes are validated as hex colors; the analytics filter link is nonce-protected; the admin CSP allows Gravatar and emoji images.
* Changed: Search result pages are marked DONOTCACHEPAGE with no-cache headers while the plugin is enabled, because the bot challenge token and REST nonce rendered into the page expire.
* Added: Reasoning Effort setting (Low / Medium / High, default Low). Claude Sonnet 5 and Opus 5 think by default and those tokens count against Max Response Tokens; Low keeps summaries fast, cheap, and complete. Sent only to models that accept it.
* Added: Structured outputs. On models that support it the response is constrained to the plugin's JSON schema, removing brace-extraction parse failures. Models that reject it fall back automatically.
* Added: Refusal stop reasons are reported as content-policy filtering instead of a generic error. claude-opus-5 added to the fallback model list.
* Changed: Summary widget inline styles moved into the stylesheet so Custom CSS can override them; the "Default CSS Reference" modal now shows the real stylesheet.
* Changed: Accessibility: loading status is announced, error messages are no longer double-announced, the CSS reference modal is a proper dialog with focus management, admin result messages use live regions, log checkboxes have labels, decorative icons are hidden from assistive tech, and a noscript notice is shown.
* Changed: Dropped the unused languages/ stub; the plugin ships English strings only.
* Dev: `npm run build` rebuilds the minified assets.

= 2.0.1 =
* Fixed: All summary requests failed with HTTP 400 on Claude 4.6+ models (Sonnet 5, Sonnet 4.6, Opus 4.6/4.7/4.8). The assistant-prefill technique introduced in 2.0.0 is not supported on these models and has been removed; JSON output is enforced via the system prompt with brace-extraction fallback as before.
* Changed: The analytics log now records the specific API failure (HTTP 400, rate limit, auth, overload) instead of a generic "AI service encountered an error" message.

= 2.0.0 =
* Removed: OpenAI support. The plugin is now Anthropic Claude only — the provider selector, OpenAI API key field, OpenAI model list, and reasoning-model setting are gone. Existing settings migrate automatically; a saved OpenAI model selection is reset so a Claude model can be chosen.
* Fixed: Truncated AI responses (cut off at the max tokens limit) were reported as "Could not parse AI response". They are now detected and reported as truncation with a pointer to the Max Response Tokens setting, and the raw response is logged when WP_DEBUG is on.
* Improved: Claude requests now use assistant prefill to force pure JSON output, eliminating parse failures from prose preambles or markdown fences.
* Changed: Default Max Response Tokens raised from 1,500 to 4,000 to fit complete summaries with sources.
* Added: Logged-in administrators bypass bot detection, IP rate limiting, and the duplicate-query throttle so site owners can test searches without hitting 429 errors.

= 1.5.1 =
* Fixed: Fatal error ("Call to undefined method ... bump_cache_namespace") when saving settings with a cache-invalidating change (model, sources, max tokens). Latent since the v1.2.0 refactor.

= 1.5.0 =
* Changed: Restyled the summary card to the new "ink & brass" dark theme matching riviantrackr.com — near-neutral charcoal surfaces, warm-neutral text, gold accent unchanged. Color setting defaults updated; installs with previously saved colors keep them until reset.

= 1.4.3 =
* Compatibility: Tested and confirmed compatible with WordPress 7.0. Bumped "Tested up to" to 7.0.

= 1.4.2 =
* Added: "Refresh Models" button now dynamically fetches the live model list from Anthropic's `/v1/models` endpoint (previously showed a static "pre-configured" message). Hardcoded list retained as a fallback if the API is unreachable.
* Added: Anthropic model list is now cached per-provider with the same 7-day TTL as the OpenAI cache.

= 1.4.1 =
* Added: Minified CSS and JS assets — all stylesheets and scripts now ship with `.min` versions (~35% smaller). Production loads minified by default; set `SCRIPT_DEBUG` to `true` for originals.

= 1.4.0 =
* Added: RivianTrackr Design System with CSS custom properties (`--rtg-*`) across frontend and admin
* Added: Dark theme tokens (frontend) and light theme tokens (admin)
* Added: Brand gradient on AI provider badge
* Added: `prefers-reduced-motion` accessibility support
* Changed: Accent color updated to brand gold (`#fba919`)
* Changed: Default border color updated to `#374151`

= 1.3.5 =
* Added: "Hide zero-result queries" filter toggle on the Analytics dashboard — non-destructively excludes zero-result entries from all analytics views without deleting data
* Added: Filter preference is now sticky — remembers your choice across page loads via WordPress user meta (per-admin)

= 1.3.4 =
* Fixed: Automatic one-time cleanup of old off-topic entries from analytics on upgrade — removes junk logged before the filter existed
* Fixed: "Scan & Remove Spam" button now also purges off-topic queries (based on Relevance Keywords)
* Fixed: Session cache hit endpoint (/log-session-hit) now applies off-topic filtering
* Fixed: Off-topic error responses are now cached in the browser so repeat searches don't re-hit the server
* Fixed: Cached off-topic responses no longer trigger session cache hit logging

= 1.3.3 =
* Fixed: Off-topic queries no longer logged to analytics — previously the REST endpoint still called log_search_event() before returning the off-topic error, polluting analytics with the junk it was supposed to block

= 1.3.2 =
* Fixed: Server-side no-results logging now applies bot detection, spam filtering, and off-topic checks — previously bots could pollute analytics by hitting /?s=junk without executing JavaScript

= 1.3.1 =
* Added: Relevance Keywords setting — define your site's topics to automatically block unrelated searches (e.g. "costco credit card", "msi monitor") from cluttering analytics
* Added: Off-topic query filter with configurable keywords (comma or newline separated, case-insensitive)
* Added: RIVIANTRACKR_ERROR_OFF_TOPIC error code for frontend-specific handling
* Added: 12 unit tests for off-topic query detection

= 1.3.0 =
* Security: Progressive IP penalties — repeat rate-limit offenders face escalating bans (5min, 30min, 24hr) instead of a 60-second reset
* Security: JS challenge token now mandatory by default — bots can no longer bypass by omitting the token parameters
* Security: Honeypot field added to search widget — auto-filling bots are instantly rejected
* Added: Duplicate query throttling — same query from same IP blocked for 5 minutes to prevent API hammering
* Added: "Require JavaScript Challenge" admin toggle in Settings (enabled by default)
* Added: RateLimiter unit test suite (27 tests)

= 1.1.0.3 =
* Improved: AI prompts now instruct the model to identify as the site's built-in search assistant, naturally attributing information to the site's coverage and using the site name in fallback messages

= 1.1.0.1 =
* Added: Model column in Recent Events analytics table — see which AI model was used for each search
* Added: ai_model column in logs database table (auto-migrated on upgrade)

= 1.1.0 =
* Added: Anthropic Claude as an alternative AI provider — choose between OpenAI and Anthropic in Settings
* Added: Separate Anthropic API key field with test connection validation and wp-config.php constant support
* Added: Curated Claude model list (Haiku 4.5, Sonnet 4.5, Sonnet 4.6, Opus 4.5, Opus 4.6)
* Added: Dynamic "Powered by" badge that shows the active AI provider name
* Changed: Settings page now includes AI Provider selector with conditional API key fields
* Changed: Cache keys include provider to prevent serving stale cross-provider responses
* Changed: CSP connect-src updated to include api.anthropic.com
* Changed: Debug log redaction now covers Anthropic x-api-key headers

= 1.0.7.1 =
* Changed: Plugin display name simplified to "AI Search Summary" in admin sidebar, menu pages, and plugin header
* Removed: Legacy SQL migration script (sql/transfer-logs-feedback.sql)
* Removed: Automatic migration code (maybe_run_migrations) for legacy searchlens-to-riviantrackr prefix upgrades

= 1.0.7 =
* Changed: Plugin renamed from "SearchLens AI" to "RivianTrackr AI Search Summary" (display name, slug, text domain, main PHP file)
* Changed: All internal prefixes replaced from `searchlens` to `riviantrackr` (constants, options, transients, database tables, REST namespace, AJAX actions, shortcode, cron hook, CSS classes, JS globals, asset filenames)
* Added: Automatic data migration on upgrade — renames database tables and migrates options/transients/cron hooks from searchlens to riviantrackr prefix
* Changed: GitHub repository renamed from searchlens-ai to riviantrackr-ai-search-summary

= 1.0.6 =
* Changed: Plugin renamed from "AI Search Summary" to "SearchLens AI" (display name, slug, text domain, main PHP file)
* Changed: All internal prefixes replaced from `aiss` to `searchlens` (constants, options, transients, database tables, REST namespace, AJAX actions, shortcode, cron hook, CSS classes, JS globals, asset filenames)
* Changed: Moved inline `<script>` and `<style>` tags to properly enqueued JS/CSS files per plugin directory guidelines
* Added: Automatic data migration on upgrade — renames database tables and migrates options/transients to new prefix

= 1.0.5.4 =
* Fixed: Block search queries containing server/CGI variable names (QUERY_STRING, DOCUMENT_ROOT, etc.) used by vulnerability scanners

= 1.0.5.3 =
* Fixed: Switched to phpcs:disable/enable block for bulk-delete query to fully suppress InterpolatedNotPrepared warning

= 1.0.5.2 =
* Fixed: Added ABSPATH direct access guard to main plugin file
* Fixed: Resolved PreparedSQLPlaceholders.ReplacementsWrongNumber phpcs warning on bulk-delete query

= 1.0.5.1 =
* Changed: Show OpenAI badge, sources, and feedback now default to off (opt-in only)
* Fixed: Added direct file access protection to index.php and uninstall.php
* Fixed: Bulk-delete query uses %i placeholder for table name instead of interpolation
* Fixed: Corrected phpcs ignore coverage for prepared SQL and nonce verification warnings

= 1.0.5 =
* Security: Custom CSS url() sanitizer now restricts data: URIs to specific image MIME types only
* Security: Rate limiting uses atomic transient locking to prevent race condition bypass
* Security: Feedback and logging endpoints now rate-limited (60 req/min) to prevent database flooding
* Security: Replaced all MD5 hashing with SHA-256 (cache keys, IP hashing, rate limit keys)
* Security: Added Content-Security-Policy header to plugin admin pages
* Security: API key automatically redacted from WP_DEBUG_LOG output via http_api_debug filter
* Security: JS challenge token (HMAC) added to summary requests for enhanced bot detection
* Security: Bulk delete nonce moved from HTML data-attribute to wp_localize_script
* Added: Anonymize Search Queries setting for GDPR/privacy compliance (stores SHA-256 hashes)
* Added: One-click GDPR purge to retroactively anonymize all stored search query text
* Improved: Advanced settings section now hidden by default with a toggle button and warning banner

= 1.0.4.1 =
* Added tablet responsive breakpoint (768px) and keyboard focus states for feedback buttons
* Sources toggle state now persists across page navigations via localStorage
* Progressive status messages shown during slow AI responses (10s, 20s, 30s)
* Full query text shown on hover for truncated queries in analytics tables
* Bulk delete action for selecting and removing multiple event log entries at once
* Badge threshold legend added to analytics page explaining color-coded indicators
* Extracted 18 magic numbers into named constants for maintainability
* Added PHP type hints to 27 core functions

= 1.0.4 =
* Added hook-based options cache invalidation for robust settings flushing across all update paths
* Added pagination to Top Search Queries and Top AI Errors analytics sections
* Refactored analytics pagination into a shared reusable method, reducing code duplication
* Error code constants are now passed to the JavaScript frontend for consistent error matching
* API retry logic now includes attempt count in error messages and debug logs for better diagnostics
* Cleaned up stale inline comments in the API retry code path

= 1.0.3 =
* Fixed no-results searches not being logged to analytics, giving admins complete search visibility
* Fixed premature logging of unconfigured state before query validation; empty queries no longer logged
* Fixed CSV export silently returning empty data when start date is after end date
* Fixed stale cached summaries being served after changing content length setting
* Fixed corrupted cache transients not being cleaned up, causing repeated decode failures
* Fixed rate-limit retry_after header potentially returning negative values
* Removed redundant options loading in the summary REST endpoint
* Session cache hit logging no longer counts against the per-IP rate limit
* Fixed saving auto-purge settings clearing selected post types

= 1.0.1 =
* Added post type filtering - choose which post types are included in AI search results
* Added configurable max sources displayed beneath summaries (1-20)
* Added configurable content length per post sent to the AI (100-2000 characters)
* Added option to preserve plugin data on uninstall for easy reinstallation

= 1.0.0 =
* Initial release
* AI-powered search summaries using OpenAI GPT models
* Support for GPT-4o, GPT-4, GPT-3.5-turbo, and reasoning models
* Non-blocking async loading for fast search results
* Multi-tier caching system (server + browser)
* Comprehensive analytics dashboard
* IP-based and global rate limiting
* Bot detection to prevent API abuse
* Trending Searches widget and shortcode
* Color theming and custom CSS support
* CSV export for logs and statistics
* GDPR-friendly data handling
* Security headers and prepared statements

== Upgrade Notice ==

= 2.1.0 =
Bug-fix and hardening release. Adds a Reasoning Effort setting (default Low) and structured JSON outputs. If your search pages were being page-cached, note they are now sent with no-cache headers.

= 2.0.0 =
Breaking: OpenAI support removed — the plugin now requires an Anthropic API key. Settings migrate automatically, but if you were using an OpenAI model you must pick a Claude model in Settings after upgrading. Also fixes truncated-response errors and lets admins test without rate limits.

= 1.3.5 =
New "Hide zero-result queries" filter on the Analytics dashboard. Click to toggle zero-result entries out of all views — non-destructive, data stays in the DB and reappears when you turn it off. The filter now remembers your preference across page loads. Great for seeing clean analytics without spam noise.

= 1.3.4 =
Closes the last spam vector: the session cache hit logging endpoint now applies the same off-topic filter. Off-topic errors are also cached in the browser to avoid repeat REST calls.

= 1.3.3 =
Off-topic queries are now fully silent — they are blocked and not logged to analytics at all. Previously the off-topic filter still recorded the junk query before returning the error.

= 1.3.2 =
Closes the server-side logging gap that allowed bots to pollute analytics by requesting /?s=junk directly. No-results searches now go through the same bot detection, spam filtering, and off-topic checks as the REST API endpoint.

= 1.3.1 =
Off-topic query filtering: configure Relevance Keywords in Settings to block unrelated searches from cluttering your analytics. Leave empty to keep existing behavior (all queries allowed).

= 1.3.0 =
Spam prevention release: progressive IP bans for repeat offenders, mandatory JS challenge token (configurable), honeypot bot trap, and duplicate query throttling. The "Require JavaScript Challenge" setting defaults to enabled — disable it in Settings only if you have custom API integrations.

= 1.1.0.3 =
AI summaries now feel native to your site — the AI identifies as your site's search assistant and naturally references your site's coverage in responses.

= 1.1.0.1 =
Analytics now shows which AI model was used for each search event, making it easy to compare response times across models.

= 1.1.0 =
Multi-provider support: Choose between OpenAI and Anthropic Claude for AI-powered search summaries. Existing OpenAI setups continue working unchanged — just upgrade and optionally switch to Anthropic in Settings.

= 1.0.7.1 =
Simplified plugin display name to "AI Search Summary". Removed legacy SQL migration script and automatic migration code.

= 1.0.7 =
Plugin renamed from SearchLens AI to RivianTrackr AI Search Summary. All internal prefixes changed from `searchlens` to `riviantrackr`. Database tables, options, transients, and cron hooks are migrated automatically on upgrade. GitHub repository renamed to riviantrackr-ai-search-summary.

= 1.0.6 =
WordPress plugin directory compliance: plugin renamed to SearchLens AI, all internal prefixes changed from `aiss` to `searchlens`, inline scripts/styles moved to enqueued files. Database tables and options are migrated automatically on activation.

= 1.0.5.4 =
Blocks vulnerability scanner probe queries (QUERY_STRING, DOCUMENT_ROOT, etc.) from triggering AI summaries and wasting API calls.

= 1.0.5.3 =
Fixes remaining phpcs InterpolatedNotPrepared warning on bulk-delete prepared query.

= 1.0.5.2 =
Fixes direct file access protection on main plugin file and resolves prepared SQL placeholder phpcs warning.

= 1.0.5.1 =
Credits and badges now default to hidden — enable them in Settings > SearchLens AI if desired. Includes direct access protection and prepared SQL fixes for plugin directory compliance.

= 1.0.5 =
Security hardening release: stricter CSS sanitization, atomic rate limiting, SHA-256 hashing, CSP headers, API key log redaction, JS bot challenge tokens, GDPR query anonymization, and nonce handling improvements.

= 1.0.4.1 =
Polish update: tablet responsive layout, sources toggle persistence, slow-response progress indicator, bulk log deletion, badge legend, named constants, and PHP type hints.

= 1.0.4 =
Improved analytics with paginated Top Queries and Top Errors sections, more robust options caching, and better API retry diagnostics.

= 1.0.3 =
Bug fixes for analytics logging, cache correctness, CSV export validation, rate limiting fairness, and settings persistence.

= 1.0.1 =
New settings: post type filtering, configurable max sources and content length per post, and an option to preserve data on uninstall.

= 1.0.0 =
Initial release. Requires WordPress 6.9+, PHP 8.4+, and an OpenAI API key.
