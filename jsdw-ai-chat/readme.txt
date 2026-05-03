=== JSDW AI Chat ===
Contributors: jsdw
Tags: chat, knowledge, assistant, rest-api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first site knowledge assistant with admin indexing, REST API, optional AI phrase refinement (OpenAI when configured), and a front-end widget.

== Description ==

Answers are built from your WordPress content (indexed sources). Optional phrase assist can refine wording of high-confidence local answers only; it does not replace retrieval or invent facts.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via **Plugins → Add New → Upload Plugin** using the distribution zip.
2. Activate the plugin through the **Plugins** menu.
3. Configure settings under the JSDW AI Chat admin menu.

== Frequently Asked Questions ==

= Does this send all chat to an external AI? =

No. Retrieval and answers are local-first. External APIs are used only when AI features, a configured provider, and phrase assist are enabled—and only for the final answer text under strict gates.

= Do API keys stay readable in the database? =

From 1.11.0 onward, provider keys are stored encrypted when PHP’s sodium extension is available (typical on PHP 8.0+). If sodium is missing, keys behave as in earlier versions until the host enables it.

= Why would I need to re-enter API keys after an update or migration? =

Keys are tied to this site’s `wp-config.php` salts. After cloning the database to another install or changing `AUTH_KEY` / related salts, old ciphertext cannot be decrypted. Open Settings and save keys again.

= Why do rendered URL sources require the exact page URL? =

Outbound fetches use strict safety checks and do not follow HTTP redirects. Use the canonical URL (correct scheme and hostname) in your source rules.

== Changelog ==

= 1.11.0 =
* Security: Provider API keys encrypted at rest with libsodium (when available); one-time migration encrypts existing plaintext keys in settings.
* Security: Public chat query throttle keyed by client IP to prevent unlimited requests via rotating session keys.
* Security: SSRF protections for server-side URL fetches (rendered sources); redirects are not followed—use canonical URLs in source configuration.
* Security: Sensitive fields redacted from logged context payloads before storage.
* Note: If keys appear missing after changing WordPress salts or moving the database, re-save provider keys under plugin Settings.

= 1.1.0 =
* Initial packaged release for WordPress distribution.
