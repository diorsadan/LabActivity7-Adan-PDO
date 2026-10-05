<?php
/**
 * Session handling, access guards, and output/CSRF helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

/** Only logged-in users may continue; others go to the login page. */
function require_auth(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** Only guests may continue; logged-in users go to the newsfeed. */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect('index.php');
    }
}

/** Escape a value for safe HTML output (XSS protection). */
function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape(csrf_token()) . '">';
}

/** Reject POST requests that do not carry a valid CSRF token. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
}

/** Store a one-time message to show on the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// Shared length limits used by both client-side (maxlength) and server-side validation.
const POST_CONTENT_MAX = 10000;
const COMMENT_MAX      = 2000;
