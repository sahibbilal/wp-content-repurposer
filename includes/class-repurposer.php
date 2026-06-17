<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCR_Repurposer {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const MODEL   = 'claude-haiku-4-5';

    private $api_key;

    public function __construct() {
        $this->api_key = get_option( 'wcr_claude_api_key', '' );
    }

    /**
     * Repurpose post content into LinkedIn, Twitter, Email, and optionally Blog formats.
     *
     * @param string $title
     * @param string $content  Plain text, tags stripped.
     * @param string $tone     professional | casual | educational
     * @return array|WP_Error  Keys: linkedin, twitter, email
     */
    public function repurpose( $title, $content, $tone = 'professional' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key is not configured. Go to Settings → Content Repurposer.' );
        }

        $tone_desc = $this->tone_description( $tone );
        $prompt    = $this->build_repurpose_prompt( $title, $content, $tone_desc );

        $raw = $this->call_api( $prompt );
        if ( is_wp_error( $raw ) ) return $raw;

        return $this->parse_repurpose_response( $raw );
    }

    /**
     * Generate a full blog post from a title/topic and optional notes.
     *
     * @param string $title  Topic or working title.
     * @param string $notes  Optional bullet points or outline.
     * @param string $tone
     * @return array|WP_Error  Keys: title, content (HTML-ready paragraphs)
     */
    public function generate_blog( $title, $notes = '', $tone = 'professional' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key is not configured. Go to Settings → Content Repurposer.' );
        }

        $tone_desc = $this->tone_description( $tone );
        $prompt    = $this->build_blog_prompt( $title, $notes, $tone_desc );

        $raw = $this->call_api( $prompt, 2000 );
        if ( is_wp_error( $raw ) ) return $raw;

        return $this->parse_blog_response( $raw );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function call_api( $prompt, $max_tokens = 1500 ) {
        $response = wp_remote_post(
            self::API_URL,
            array(
                'timeout' => 60,
                'headers' => array(
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model'      => self::MODEL,
                    'max_tokens' => $max_tokens,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => $prompt ),
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }

        return $body['content'][0]['text'] ?? '';
    }

    private function build_repurpose_prompt( $title, $content, $tone_desc ) {
        $content = wp_trim_words( $content, 600, '' );

        return "You are a professional content repurposing assistant. Your tone should be: {$tone_desc}.

Take the following blog post and repurpose it into three formats. Return ONLY the three sections below with exactly these headers — no intro, no explanation, no extra text.

---LINKEDIN---
(Write a LinkedIn post: 150–250 words, hook in the first line, 3–5 key insights as short paragraphs or bullet points, a clear call to action at the end, 3–5 relevant hashtags)

---TWITTER---
(Write a Twitter/X thread: 6–8 tweets. Start with 🧵 on Tweet 1. Number each tweet \"1/\", \"2/\", etc. Each tweet must be under 280 characters. End with a CTA tweet.)

---EMAIL---
(Write an email newsletter intro: 120–180 words, conversational subject line on the first line prefixed with \"Subject:\", then the body. Hook the reader, tease the main insight, end with a link placeholder [READ MORE →])

BLOG POST TITLE: {$title}

BLOG POST CONTENT:
{$content}";
    }

    private function build_blog_prompt( $title, $notes, $tone_desc ) {
        $notes_section = $notes ? "\n\nOUTLINE / NOTES:\n{$notes}" : '';

        return "You are an expert blog writer. Your tone should be: {$tone_desc}.

Write a complete, well-structured blog post. Return ONLY the two sections below with exactly these headers — no intro, no explanation.

---TITLE---
(Write an optimized, engaging blog post title — clear, specific, benefit-driven)

---CONTENT---
(Write the full blog post body: 600–900 words. Use ## for subheadings. Start with a hook paragraph. Cover the topic thoroughly with 3–4 sections. End with a practical takeaway or call to action. Use plain paragraphs — no bullet point overload. Write for a human reader, not for SEO bots.)

TOPIC / WORKING TITLE: {$title}{$notes_section}";
    }

    private function parse_repurpose_response( $raw ) {
        $sections = array(
            'linkedin' => '',
            'twitter'  => '',
            'email'    => '',
        );

        $parts = preg_split( '/---(?:LINKEDIN|TWITTER|EMAIL)---/', $raw );

        if ( isset( $parts[1] ) ) $sections['linkedin'] = trim( $parts[1] );
        if ( isset( $parts[2] ) ) $sections['twitter']  = trim( $parts[2] );
        if ( isset( $parts[3] ) ) $sections['email']    = trim( $parts[3] );

        if ( empty( $sections['linkedin'] ) && empty( $sections['twitter'] ) ) {
            $sections['linkedin'] = trim( $raw );
        }

        return $sections;
    }

    private function parse_blog_response( $raw ) {
        $result = array( 'title' => '', 'content' => '' );

        $parts = preg_split( '/---(?:TITLE|CONTENT)---/', $raw );

        if ( isset( $parts[1] ) ) $result['title']   = trim( $parts[1] );
        if ( isset( $parts[2] ) ) $result['content'] = trim( $parts[2] );

        if ( empty( $result['title'] ) ) {
            $result['title']   = '';
            $result['content'] = trim( $raw );
        }

        return $result;
    }

    private function tone_description( $tone ) {
        $map = array(
            'professional' => 'professional and authoritative — confident, clear, business-focused',
            'casual'       => 'casual and conversational — friendly, approachable, like talking to a colleague',
            'educational'  => 'educational and insightful — teach the reader something, use examples',
        );
        return $map[ $tone ] ?? $map['professional'];
    }
}
