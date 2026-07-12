<?php
/**
 * Flush Rewrite Rules Helper
 * 
 * Run this file ONCE to flush rewrite rules after updating component system
 * Access via: /wp-content/plugins/webinocrm/flush-rewrite.php
 * 
 * @package WebinoCRM
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized' );
}

flush_rewrite_rules();

echo '<h1>✅ Rewrite Rules Flushed Successfully</h1>';
echo '<p>You can now delete this file for security.</p>';
echo '<p><a href="' . esc_url( home_url( '/dashboard' ) ) . '">Go to Dashboard</a></p>';

