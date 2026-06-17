# WP Content Repurposer

**One click. Three formats. Powered by Claude AI.**

Turn any WordPress post into a LinkedIn post, Twitter/X thread, and email newsletter intro — directly from the post editor.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php)
![Claude AI](https://img.shields.io/badge/Claude-Haiku-D97706?style=flat-square)
![License](https://img.shields.io/badge/License-GPLv2-green?style=flat-square)

---

## What It Does

You write a blog post once. You click **✨ Repurpose This Post**. Claude AI reads your content and generates:

| Format | Output |
|--------|--------|
| **LinkedIn** | 150–250 words · hook · key insights · hashtags |
| **Twitter/X Thread** | 6–8 tweets · each ≤280 chars · numbered · CTA |
| **Email Newsletter** | 120–180 words · subject line · teaser · [READ MORE →] |

Everything appears in a tabbed panel inside the WordPress editor — no context-switching, no copy-pasting between tabs.

---

## Architecture

```
Post Editor
    │
    ▼ (click "Repurpose")
AJAX → wcr_repurpose handler
    │
    ▼
WCR_Repurposer::repurpose()
    │  - Strips HTML tags from content
    │  - Truncates to ~600 words (token budget)
    │  - Builds structured prompt with ---SECTION--- markers
    │
    ▼
Claude API (claude-haiku-4-5)
    │  POST https://api.anthropic.com/v1/messages
    │  model: claude-haiku-4-5, max_tokens: 1500
    │
    ▼
parse_response()
    │  Splits on ---LINKEDIN--- / ---TWITTER--- / ---EMAIL---
    │
    ▼
Meta Box (JS)
    - LinkedIn tab: textarea + char count
    - Twitter tab: rendered tweet cards + raw textarea
    - Email tab: textarea with subject line
    - Copy to clipboard buttons on all tabs
```

---

## File Structure

```
wp-content-repurposer/
├── wp-content-repurposer.php    # Bootstrap: defines constants, loads classes
├── includes/
│   ├── class-settings.php       # Settings page (API key, tone, post types)
│   ├── class-repurposer.php     # Claude API integration + prompt builder
│   └── class-meta-box.php       # Meta box render + AJAX handler
├── assets/
│   ├── repurposer.css           # Tab UI, tweet cards, textarea styles
│   └── repurposer.js            # Tab switching, AJAX, renderThread(), clipboard
└── readme.txt                   # WordPress plugin repo readme
```

---

## Installation

**Option A — Manual upload**
1. Download or clone this repo
2. Upload the `wp-content-repurposer` folder to `/wp-content/plugins/`
3. Activate in **Plugins → Installed Plugins**

**Option B — Clone directly**
```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/sahibbilal/wp-content-repurposer.git
```

---

## Configuration

1. Go to **Settings → Content Repurposer**
2. Enter your **Claude API key** from [console.anthropic.com](https://console.anthropic.com)
3. Choose a **default tone** (Professional / Casual / Educational)
4. Select which **post types** show the repurposer panel
5. Save

---

## Usage

1. Open any post (or create a new one)
2. Write your content — at least a few paragraphs
3. **Save as Draft** first (so the post ID exists)
4. Scroll down to the **✍️ Repurpose Content** panel
5. Choose a tone and click **✨ Repurpose This Post**
6. Switch between the LinkedIn / Twitter / Email tabs
7. Edit if needed, then click **📋 Copy** to copy to clipboard

---

## API & Cost

- **Model:** `claude-haiku-4-5` — Anthropic's fastest model
- **Tokens per call:** ~800 input + ~600 output = ~1400 tokens
- **Approximate cost:** $0.006 per repurpose (at $4.00 / 1M input tokens)
- **Your key, your billing** — this plugin never proxies through any server

---

## Tone Options

| Tone | Style |
|------|-------|
| **Professional** | Confident, business-focused, authoritative |
| **Casual** | Friendly, approachable, like talking to a colleague |
| **Educational** | Teach the reader, use examples, insightful |

---

## Security

- API key stored in WordPress options table (not hardcoded, not exposed to frontend)
- AJAX calls protected by WordPress nonces
- `current_user_can('edit_posts')` checked before processing
- Content sanitized with `wp_strip_all_tags()` before sending to API

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Claude API key ([get one here](https://console.anthropic.com))

---

## Part of the 30-Day WordPress AI Plugin Series

This is **Day 2** of an open-source series where I build one WordPress + AI plugin every day.

- Day 1: [WP RAG FAQ](https://github.com/sahibbilal/wp-rag-faq) — Upload docs, visitors ask questions, GPT answers from your content only
- Day 2: **WP Content Repurposer** ← you are here
- More coming daily…

Follow along: [bilalmahmood.dev](https://bilalmahmood.dev) · [LinkedIn](https://linkedin.com/in/bilalmahmood)

---

## License

GPLv2 or later — see [LICENSE](LICENSE)
