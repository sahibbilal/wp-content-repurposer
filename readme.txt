=== WP Content Repurposer ===
Contributors: bilalmahmood
Tags: ai, content, repurpose, linkedin, twitter, email, claude, chatgpt
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Repurpose any WordPress post into a LinkedIn post, Twitter/X thread, and email newsletter intro — with one click using Claude AI.

== Description ==

Stop writing the same content four times.

WP Content Repurposer adds a **Repurpose Content** panel directly in your WordPress post editor. Write your blog post once — click one button — and Claude AI instantly generates:

* **LinkedIn post** — 150–250 words, hook, key insights, hashtags
* **Twitter/X thread** — 6–8 tweets, each under 280 characters, with thread numbering
* **Email newsletter intro** — 120–180 words with subject line, ready to paste into Mailchimp

**Three tone options:**
* Professional — confident, business-focused
* Casual & Conversational — friendly, approachable
* Educational — teach the reader, use examples

**Requirements:**
* Claude API key from [console.anthropic.com](https://console.anthropic.com)
* PHP 8.0+
* WordPress 6.0+

== Installation ==

1. Upload the `wp-content-repurposer` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Settings → Content Repurposer**
4. Enter your Claude API key and save
5. Open any post — the **Repurpose Content** panel appears below the editor

== Frequently Asked Questions ==

= Which AI model does this use? =
Claude Haiku (claude-haiku-4-5) — Anthropic's fastest model. Fast, affordable, and excellent at structured content generation.

= Who pays for the API? =
You do, using your own Claude API key. Claude Haiku costs approximately $0.00025 per 1K input tokens — repurposing a typical 800-word post costs less than $0.01.

= Can I edit the generated content? =
Yes. All output fields are editable text areas. Adjust before copying.

= Does it work with the block editor (Gutenberg)? =
Yes. The meta box appears in the block editor sidebar area below the editor.

== Changelog ==

= 1.0.0 =
* Initial release
