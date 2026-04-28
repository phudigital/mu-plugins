<?php
define( 'ABSPATH', __DIR__ );
define( 'WP_ADMIN', true );

$actions = [];
$filters = [];
$removed_meta_boxes = [];
$post_meta = [
    10 => [
        '_thumbnail_id' => [ '44' ],
        'custom_key'    => [ 'A', 'B' ],
    ],
];
$added_post_meta = [];
$post_terms = [
    10 => [
        'category' => [ 3, 7 ],
        'post_tag' => [ 11 ],
    ],
];
$set_terms = [];
$posts = [
    10 => null,
];
$inserted_post = null;

function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['actions'][] = [ $hook, $callback, $priority ];
}

function add_filter( $hook, $callback, $priority = 10 ) {
    $GLOBALS['filters'][] = [ $hook, $callback, $priority ];
}

function apply_filters( $hook, $value ) {
    return $value;
}

function remove_action( $hook, $callback ) {
    $GLOBALS['removed_actions'][] = [ $hook, $callback ];
}

function remove_meta_box( $id, $screen, $context ) {
    $GLOBALS['removed_meta_boxes'][] = [ $id, $screen, $context ];
}

function current_user_can( $capability, $post_id = null ) {
    return in_array( $capability, [ 'edit_posts', 'edit_post' ], true );
}

function post_type_exists( $post_type ) {
    return in_array( $post_type, [ 'post', 'private_type' ], true );
}

function get_post_type_object( $post_type ) {
    return (object) [
        'public' => 'post' === $post_type,
    ];
}

function get_post( $post_id ) {
    if ( 10 !== (int) $post_id ) {
        return null;
    }

    return (object) [
        'ID'             => 10,
        'post_author'    => 5,
        'post_content'   => 'Original content',
        'post_excerpt'   => 'Original excerpt',
        'post_name'      => 'original-post',
        'post_parent'    => 2,
        'post_password'  => '',
        'post_status'    => 'publish',
        'post_title'     => 'Original title',
        'post_type'      => 'post',
        'comment_status' => 'open',
        'ping_status'    => 'open',
        'menu_order'     => 4,
    ];
}

function wp_insert_post( $data ) {
    $GLOBALS['inserted_post'] = $data;
    return 99;
}

function get_post_custom( $post_id ) {
    return $GLOBALS['post_meta'][ $post_id ] ?? [];
}

function maybe_unserialize( $value ) {
    return $value;
}

function add_post_meta( $post_id, $key, $value ) {
    $GLOBALS['added_post_meta'][] = [ $post_id, $key, $value ];
}

function get_object_taxonomies( $post_type ) {
    return [ 'category', 'post_tag' ];
}

function wp_get_object_terms( $post_id, $taxonomy, $args = [] ) {
    return $GLOBALS['post_terms'][ $post_id ][ $taxonomy ] ?? [];
}

function wp_set_object_terms( $post_id, $terms, $taxonomy ) {
    $GLOBALS['set_terms'][] = [ $post_id, $terms, $taxonomy ];
}

function is_wp_error( $value ) {
    return false;
}

function sanitize_text_field( $value ) {
    return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) { return $value; }
function wp_nonce_url( $url ) { return $url . '&_wpnonce=test'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function get_edit_post_link( $post_id, $context = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . $post_id . '&action=edit'; }
function wp_redirect( $url ) { $GLOBALS['redirected_to'] = $url; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function esc_html__( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function add_query_arg( $args, $url ) { return $url . '&' . http_build_query( $args ); }
function size_format( $bytes ) { return ( $bytes / 1024 / 1024 ) . ' MB'; }

require __DIR__ . '/../pdl-modules/admin-utilities.php';

function assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . PHP_EOL );
        fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
        fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
        exit( 1 );
    }
}

class Pdl_Test_Admin_Bar {
    public $removed = [];

    public function remove_node( $id ) {
        $this->removed[] = $id;
    }
}

$bar = new Pdl_Test_Admin_Bar();
pdl_admin_utilities_cleanup_admin_bar( $bar );
assert_same( [ 'wp-logo', 'new-content' ], $bar->removed, 'Only checked admin bar nodes should be removed.' );

pdl_admin_utilities_remove_dashboard_widgets();
assert_same(
    [
        [ 'dashboard_quick_press', 'dashboard', 'side' ],
        [ 'dashboard_primary', 'dashboard', 'side' ],
        [ 'dashboard_site_health', 'dashboard', 'normal' ],
    ],
    $removed_meta_boxes,
    'Only checked dashboard widgets should be removed.'
);

$new_id = pdl_admin_utilities_duplicate_post( 10 );
assert_same( 99, $new_id, 'Duplicate should return inserted post ID.' );
assert_same( 'Original title - Copy', $inserted_post['post_title'], 'Duplicate should suffix copied post title.' );
assert_same( 'draft', $inserted_post['post_status'], 'Duplicate should be saved as draft.' );
assert_same(
    [
        [ 99, '_thumbnail_id', '44' ],
        [ 99, 'custom_key', 'A' ],
        [ 99, 'custom_key', 'B' ],
    ],
    $added_post_meta,
    'Duplicate should copy all post meta values.'
);
assert_same(
    [
        [ 99, [ 3, 7 ], 'category' ],
        [ 99, [ 11 ], 'post_tag' ],
    ],
    $set_terms,
    'Duplicate should copy taxonomy terms.'
);

$actions = pdl_admin_utilities_add_duplicate_row_action( [], (object) [ 'ID' => 10, 'post_type' => 'post' ] );
assert_same( true, isset( $actions['pdl_duplicate_content'] ), 'Public post types should get a duplicate row action.' );

$actions = pdl_admin_utilities_add_duplicate_row_action( [], (object) [ 'ID' => 10, 'post_type' => 'private_type' ] );
assert_same( [], $actions, 'Non-public post types should not get a duplicate row action.' );

$upload = pdl_admin_utilities_validate_image_upload(
    [
        'name' => 'photo.jpg',
        'type' => 'image/jpeg',
        'size' => 1024 * 1024,
    ]
);
assert_same( null, $upload['error'] ?? null, 'Valid images under 2MB should be allowed.' );

$upload = pdl_admin_utilities_validate_image_upload(
    [
        'name' => 'large.jpg',
        'type' => 'image/jpeg',
        'size' => ( 2 * 1024 * 1024 ) + 1,
    ]
);
assert_same( 'PDL chỉ cho phép upload hình ảnh nhỏ hơn 2MB.', $upload['error'], 'Images over 2MB should be blocked.' );

$upload = pdl_admin_utilities_validate_image_upload(
    [
        'name' => 'document.pdf',
        'type' => 'application/pdf',
        'size' => 500,
    ]
);
assert_same( 'PDL chỉ cho phép upload file hình ảnh.', $upload['error'], 'Non-image uploads should be blocked.' );

echo "admin-utilities-module-test OK\n";
