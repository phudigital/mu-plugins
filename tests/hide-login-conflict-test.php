<?php
define( 'ABSPATH', __DIR__ );

$actions = [];
$filters = [];
$options = [
    'active_plugins'      => [ 'woocommerce/woocommerce.php' ],
    'permalink_structure' => '/%postname%/',
];

function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['actions'][] = [ $hook, $callback, $priority ];
}

function add_filter( $hook, $callback, $priority = 10 ) {
    $GLOBALS['filters'][] = [ $hook, $callback, $priority ];
}

function apply_filters( $hook, $value ) {
    return $value;
}

function get_option( $name, $default = false ) {
    return $GLOBALS['options'][ $name ] ?? $default;
}

function update_option( $name, $value ) {
    $GLOBALS['options'][ $name ] = $value;
}

function is_multisite() {
    return false;
}

function sanitize_title_with_dashes( $value ) {
    return strtolower( trim( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $value ), '-' ) );
}

function current_user_can( $capability ) {
    return 'manage_options' === $capability;
}

function esc_html( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

require __DIR__ . '/../pdl-modules/hide-login.php';

function assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . PHP_EOL );
        fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
        fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
        exit( 1 );
    }
}

$registered_hooks = array_map(
    function( $action ) {
        return $action[0];
    },
    $actions
);

assert_same( 'WooCommerce', $GLOBALS['pdl_hide_login_handled_by'] ?? '', 'WooCommerce should make PDL Hide Login yield.' );
assert_same( true, in_array( 'admin_notices', $registered_hooks, true ), 'Conflict should register an admin notice.' );
assert_same( false, in_array( 'plugins_loaded', $registered_hooks, true ), 'Conflict should not register login request rewriting.' );
assert_same( false, in_array( 'wp_loaded', $registered_hooks, true ), 'Conflict should not register login redirects.' );

$GLOBALS['actions'] = [];
$GLOBALS['filters'] = [];
$GLOBALS['options']['active_plugins'] = [ 'jetpack/jetpack.php' ];
unset( $GLOBALS['pdl_hide_login_handled_by'] );

assert_same( true, pdl_hide_login_should_skip_module(), 'Jetpack should make PDL Hide Login yield.' );
assert_same( 'Jetpack', $GLOBALS['pdl_hide_login_handled_by'] ?? '', 'Jetpack conflict should be reported.' );

echo "hide-login-conflict-test OK\n";
