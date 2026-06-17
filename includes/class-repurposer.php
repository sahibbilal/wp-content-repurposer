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
     * Repurpose post content into LinkedIn, Twitter, and Email formats.
     *
     * @param string $title    Post title.
     * @param string $content  Post content (plain text, tags stripped).
     * @param string $tone     professional | casual | educational
     * @return array|WP_Error
     */
    public function repurpose( $title, $content, $tone = 'professional' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key is not configured. Go to Settings → Content Repurposer.' );
        }

        $tone_desc = $this->tone_description( $tone );
        $prompt    = $this->build_prompt( $title, $content, $tone_desc );

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
                    'max_tokens' => 1500,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => $prompt ),
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }

        $raw = $body['content'][0]['text'] ?? '';
        return $this->parse_response( $raw );
    }

    private function build_prompt( $title, $content, $tone_desc ) {
        // Truncate content to ~3000 chars to stay within token budget.
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

    private function parse_response( $raw ) {
        $sections = array(
            'linkedin' => '',
            'twitter'  => '',
            'email'    => '',
        );

        // Split on the section markers.
        $parts = preg_split( '/---(?:LINKEDIN|TWITTER|EMAIL)---/', $raw );

        if ( isset( $parts[1] ) ) $sections['linkedin'] = trim( $parts[1] );
        if ( isset( $parts[2] ) ) $sections['twitter']  = trim( $parts[2] );
        if ( isset( $parts[3] ) ) $sections['email']    = trim( $parts[3] );

        // Fallback: if parsing failed, put everything in linkedin.
        if ( empty( $sections['linkedin'] ) && empty( $sections['twitter'] ) ) {
            $sections['linkedin'] = trim( $raw );
        }

        return $sections;
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
