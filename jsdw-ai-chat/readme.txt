=== JSDW AI Chat ===
Contributors: jsdw
Tags: chat, knowledge, assistant, rest-api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.1.0
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

== Changelog ==

= 1.1.0 =
* Initial packaged release for WordPress distribution.
