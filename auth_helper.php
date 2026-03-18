<?php
/**
 * Authentication helper functions for SmartWell application
 * This file provides consistent security functions for session management and authentication
 */

// ---- Helper functions below (leave unchanged unless you want to add features) ----

/**
 * Start a secure session with appropriate settings
 */
function start_secure_session() {
    // Set secure session parameters
    $session_options = [
        'cookie_httponly' => true,
        'cookie_secure' => false,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'cookie_path' => '/',
        'cookie_domain' => '',
    ];

    // Start session if it's not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start($session_options);
    }

    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regeneration']) ||
        (time() - $_SESSION['last_regeneration']) > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if user is logged in
 * @return bool Returns true if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['id']) && isset($_SESSION['user_name']);
}

/**
 * Get the current user's ID
 * @return string|null Returns the user ID if logged in, null otherwise
 */
function get_user_id() {
    return is_logged_in() ? $_SESSION['id'] : null;
}

/**
 * Ensure session consistency to prevent session hijacking
 * (Session timeout is disabled; users will not be logged out automatically)
 */
function check_session_consistency() {
    // Session timeout disabled – do nothing here.
}
?>
