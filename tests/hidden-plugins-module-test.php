<?php
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/fixtures/plugins' );
define( 'WP_ADMIN', true );

$actions = [];
$filters = [];
$options = [];
$current_user_id = 2;
$is_multisite = false;
$super_admin = false;

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

function get_users( $args = [] ) {
    return [ (object) [ 'ID' => 1 ] ];
}

function wp_get_current_user() {
    return (object) [
        'ID' => $GLOBALS['current_user_id'],
        'user_email' => 'user@example.com',
    ];
}

function is_user_logged_in() {
    return $GLOBALS['current_user_id'] > 0;
}

function is_multisite() {
    return $GLOBALS['is_multisite'];
}

function is_super_admin( $user_id = false ) {
    return $GLOBALS['super_admin'];
}

function current_user_can( $capability ) {
    return 'manage_options' === $capability;
}

function sanitize_text_field( $value ) {
    return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) { return $value; }
function wp_verify_nonce() { return true; }
function wp_nonce_field() {}
function add_options_page() {}
function wp_die( $message ) { throw new RuntimeException( $message ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function checked( $checked ) { echo $checked ? ' checked="checked"' : ''; }

require __DIR__ . '/../pdl-modules/hidden-plugins.php';

function assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . PHP_EOL );
        fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
        fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
        exit( 1 );
    }
}

$plugins = [
    'contact-form-7/wp-contact-form-7.php' => [ 'Name' => 'Contact Form 7' ],
    'generateblocks/plugin.php' => [ 'Name' => 'GenerateBlocks' ],
    'woocommerce/woocommerce.php' => [ 'Name' => 'WooCommerce' ],
];

$filtered = pdl_hidden_plugins_filter_all_plugins( $plugins );
assert_same(
    [ 'woocommerce/woocommerce.php' => [ 'Name' => 'WooCommerce' ] ],
    $filtered,
    'Regular users should not see default hidden plugins.'
);

$GLOBALS['current_user_id'] = 1;
assert_same(
    $plugins,
    pdl_hidden_plugins_filter_all_plugins( $plugins ),
    'Root single-site admin should see every plugin.'
);

$GLOBALS['current_user_id'] = 2;
$GLOBALS['options'][ PDL_HIDDEN_PLUGINS_OPTION ] = [ 'woocommerce/woocommerce.php' ];
assert_same(
    [
        'contact-form-7/wp-contact-form-7.php' => [ 'Name' => 'Contact Form 7' ],
        'generateblocks/plugin.php' => [ 'Name' => 'GenerateBlocks' ],
    ],
    pdl_hidden_plugins_filter_all_plugins( $plugins ),
    'Saved option should override default hidden plugins.'
);

$GLOBALS['is_multisite'] = true;
$GLOBALS['super_admin'] = true;
assert_same(
    true,
    pdl_hidden_plugins_is_super_admin(),
    'Multisite super admin should be allowed to configure hidden plugins.'
);

$GLOBALS['is_multisite'] = false;
$GLOBALS['super_admin'] = false;
$GLOBALS['current_user_id'] = 2;
$GLOBALS['options'][ PDL_HIDDEN_PLUGINS_OPTION ] = [ 'woocommerce/woocommerce.php' ];
$updates = (object) [
    'response' => [
        'woocommerce/woocommerce.php' => (object) [ 'new_version' => '9.0.0' ],
        'akismet/akismet.php' => (object) [ 'new_version' => '6.0.0' ],
    ],
    'no_update' => [
        'woocommerce/woocommerce.php' => (object) [ 'new_version' => '8.0.0' ],
        'hello.php' => (object) [ 'new_version' => '1.7.2' ],
    ],
];
$filtered_updates = pdl_hidden_plugins_filter_plugin_updates( $updates );
assert_same(
    [ 'akismet/akismet.php' ],
    array_keys( $filtered_updates->response ),
    'Regular users should not see update-core rows for hidden plugins.'
);
assert_same(
    [ 'hello.php' ],
    array_keys( $filtered_updates->no_update ),
    'Regular users should not see no-update rows for hidden plugins.'
);

$GLOBALS['current_user_id'] = 1;
assert_same(
    $updates,
    pdl_hidden_plugins_filter_plugin_updates( $updates ),
    'Root single-site admin should see plugin update data for hidden plugins.'
);

echo "hidden-plugins-module-test OK\n";
