<?php
define( 'ABSPATH', __DIR__ );

$tests = [];
$actions = [];
$filters = [];
$options = [
    'pdl_admin_menu_hidden' => [
        'tools.php',
        'submenu:options-general.php|options-permalink.php',
        'missing.php',
    ],
];
$removed_menus = [];
$removed_submenus = [];
$current_user_id = 2;
$super_admin_user_ids = [];

function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['actions'][] = [ $hook, $callback, $priority ];
}

function add_filter( $hook, $callback, $priority = 10 ) {
    $GLOBALS['filters'][] = [ $hook, $callback, $priority ];
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
    return (object) [ 'ID' => $GLOBALS['current_user_id'] ];
}

function is_super_admin( $user_id = false ) {
    $user_id = false === $user_id ? $GLOBALS['current_user_id'] : (int) $user_id;

    return in_array( $user_id, $GLOBALS['super_admin_user_ids'], true );
}

function remove_menu_page( $slug ) {
    $GLOBALS['removed_menus'][] = $slug;
}

function remove_submenu_page( $parent_slug, $slug ) {
    $GLOBALS['removed_submenus'][] = [ $parent_slug, $slug ];
}

function wp_strip_all_tags( $value ) {
    return strip_tags( $value );
}

function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function checked( $checked ) { echo $checked ? ' checked="checked"' : ''; }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce() { return true; }
function wp_nonce_field() {}
function add_options_page() {}
function wp_die( $message ) { throw new RuntimeException( $message ); }

function run_registered_actions( $hook ) {
    $callbacks = array_values(
        array_filter(
            $GLOBALS['actions'],
            function( $action ) use ( $hook ) {
                return $action[0] === $hook;
            }
        )
    );

    usort(
        $callbacks,
        function( $a, $b ) {
            return $a[2] <=> $b[2];
        }
    );

    foreach ( $callbacks as $callback ) {
        call_user_func( $callback[1] );
    }
}

global $menu, $submenu;
$menu = [
    [ 'Dashboard', 'read', 'index.php' ],
    [ '<span>Pages</span>', 'edit_pages', 'edit.php?post_type=page' ],
    [ 'Tools', 'manage_options', 'tools.php' ],
    [ '', 'read', 'separator1' ],
    [ 'Settings', 'manage_options', 'options-general.php' ],
];
$submenu = [
    'options-general.php' => [
        [ 'General', 'manage_options', 'options-general.php' ],
        [ 'Permalinks', 'manage_options', 'options-permalink.php' ],
    ],
];

require __DIR__ . '/../pdl-modules/admin-menu.php';

function assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . PHP_EOL );
        fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
        fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
        exit( 1 );
    }
}

$entries = pdl_admin_menu_get_entries();

assert_same( true, isset( $entries['menu:tools.php'] ), 'Top-level menu key should be discovered.' );
assert_same( true, isset( $entries['submenu:options-general.php|options-permalink.php'] ), 'Submenu key should be discovered.' );
assert_same( 'Pages', $entries['menu:edit.php?post_type=page']['label'], 'Menu labels should be normalized.' );
assert_same( false, pdl_admin_menu_applies_to_super_admins(), 'Super admin hiding should be opt-in by default.' );

assert_same(
    [ 'menu:tools.php', 'submenu:options-general.php|options-permalink.php' ],
    pdl_admin_menu_get_hidden_keys( $entries ),
    'Hidden keys should accept legacy top-level slugs and ignore missing entries.'
);

pdl_admin_menu_apply_hidden();
assert_same( [ 'tools.php' ], $removed_menus, 'Regular users should have hidden top-level menus removed.' );
assert_same( [ [ 'options-general.php', 'options-permalink.php' ] ], $removed_submenus, 'Regular users should have hidden submenus removed.' );

$GLOBALS['current_user_id'] = 1;
$GLOBALS['removed_menus'] = [];
$GLOBALS['removed_submenus'] = [];
pdl_admin_menu_apply_hidden();
assert_same( [], $removed_menus, 'Controller user should keep full menu.' );
assert_same( [], $removed_submenus, 'Controller user should keep full submenu.' );

$GLOBALS['current_user_id'] = 3;
$GLOBALS['super_admin_user_ids'] = [ 3 ];
$GLOBALS['options']['pdl_admin_menu_settings'] = [
    'apply_to_super_admins' => false,
];
$GLOBALS['removed_menus'] = [];
$GLOBALS['removed_submenus'] = [];
pdl_admin_menu_apply_hidden();
assert_same( [], $removed_menus, 'Super admins should keep full menu when the setting is disabled.' );
assert_same( [], $removed_submenus, 'Super admins should keep full submenu when the setting is disabled.' );

$GLOBALS['options']['pdl_admin_menu_settings'] = [
    'apply_to_super_admins' => true,
];
$GLOBALS['removed_menus'] = [];
$GLOBALS['removed_submenus'] = [];
pdl_admin_menu_apply_hidden();
assert_same( [ 'tools.php' ], $removed_menus, 'Super admins should have hidden top-level menus removed when the setting is enabled.' );
assert_same( [ [ 'options-general.php', 'options-permalink.php' ] ], $removed_submenus, 'Super admins should have hidden submenus removed when the setting is enabled.' );

$GLOBALS['current_user_id'] = 1;
$GLOBALS['super_admin_user_ids'] = [ 1 ];
$GLOBALS['options']['pdl_admin_menu_hidden'] = [
    'tools.php',
    'options-general.php',
    'submenu:options-general.php|options-permalink.php',
];
$GLOBALS['removed_menus'] = [];
$GLOBALS['removed_submenus'] = [];
pdl_admin_menu_apply_hidden();
assert_same( [ 'tools.php' ], $removed_menus, 'Controller user should receive hidden menus when super admin hiding is enabled.' );
assert_same( [ [ 'options-general.php', 'options-permalink.php' ] ], $removed_submenus, 'Controller user should receive hidden submenus when super admin hiding is enabled.' );

$GLOBALS['current_user_id'] = 1;
$GLOBALS['super_admin_user_ids'] = [ 1 ];
$GLOBALS['options'][ PDL_ADMIN_MENU_OPTION ] = [];
$GLOBALS['options'][ PDL_ADMIN_MENU_SETTINGS_OPTION ] = [
    'apply_to_super_admins' => true,
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'pdl_admin_menu_nonce' => 'nonce',
    'pdl_admin_menu_hidden' => [
        'menu:tools.php',
        'submenu:options-general.php|options-permalink.php',
    ],
    'pdl_admin_menu_apply_to_super_admins' => '1',
];
$menu = [];
$submenu = [];
run_registered_actions( 'admin_init' );
$menu = [
    [ 'Dashboard', 'read', 'index.php' ],
    [ 'Tools', 'manage_options', 'tools.php' ],
    [ 'Settings', 'manage_options', 'options-general.php' ],
];
$submenu = [
    'options-general.php' => [
        [ 'General', 'manage_options', 'options-general.php' ],
        [ 'Permalinks', 'manage_options', 'options-permalink.php' ],
    ],
];
$GLOBALS['removed_menus'] = [];
$GLOBALS['removed_submenus'] = [];
run_registered_actions( 'admin_menu' );
assert_same(
    [ 'menu:tools.php', 'submenu:options-general.php|options-permalink.php' ],
    $GLOBALS['options'][ PDL_ADMIN_MENU_OPTION ],
    'Saving must validate against the runtime menu after admin_menu has built it, not the empty admin_init state.'
);
assert_same( [ 'tools.php' ], $removed_menus, 'Saved runtime snapshot should be applied during the same request.' );
assert_same( [ [ 'options-general.php', 'options-permalink.php' ] ], $removed_submenus, 'Saved runtime submenu snapshot should be applied during the same request.' );

echo "admin-menu-module-test OK\n";
