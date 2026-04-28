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
$is_pdl_super_admin = false;

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
function wp_json_encode( $value ) { return json_encode( $value ); }
function add_query_arg( $args, $url ) { return $url . '&' . http_build_query( $args ); }
function size_format( $bytes ) { return ( $bytes / 1024 / 1024 ) . ' MB'; }
function pdl_hidden_plugins_is_super_admin() { return $GLOBALS['is_pdl_super_admin']; }

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
assert_same( true, in_array( 'updates', $bar->removed, true ), 'Regular admins should not see admin bar update notifications.' );
assert_same( true, in_array( 'wp-logo', $bar->removed, true ), 'Regular admins should still get configured admin bar cleanup.' );
assert_same( true, in_array( 'new-content', $bar->removed, true ), 'Regular admins should still get configured admin bar cleanup.' );

ob_start();
pdl_admin_utilities_hide_admin_notices_styles();
$notice_styles = ob_get_clean();
assert_same(
    true,
    false !== strpos( $notice_styles, '.update-nag' ) && false !== strpos( $notice_styles, '#adminmenu .update-plugins' ),
    'Regular admins should get CSS that hides notices and update badges.'
);

$GLOBALS['is_pdl_super_admin'] = true;
$bar = new Pdl_Test_Admin_Bar();
pdl_admin_utilities_cleanup_admin_bar( $bar );
assert_same( [ 'wp-logo', 'new-content' ], $bar->removed, 'Super admins should still see admin bar update notifications.' );

ob_start();
pdl_admin_utilities_hide_admin_notices_styles();
$notice_styles = ob_get_clean();
assert_same( '', trim( $notice_styles ), 'Super admins should see admin notices and update badges.' );
$GLOBALS['is_pdl_super_admin'] = false;

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

assert_same(
    3,
    pdl_admin_utilities_limit_revisions( 10, (object) [ 'post_type' => 'post' ] ),
    'WordPress should keep only 3 revisions per post.'
);

assert_same(
    2 * 1024 * 1024,
    pdl_admin_utilities_upload_size_limit( 100 * 1024 * 1024 ),
    'Media uploader should advertise the 2MB limit before upload starts.'
);
assert_same( '2Mb', pdl_admin_utilities_upload_limit_label(), 'Upload limit label should be explicit.' );
assert_same(
    'Tệp vượt quá kích thước tải lên tối đa cho trang web này (tối đa 2Mb). Vui lòng giảm dung lượng ảnh trước khi upload.',
    pdl_admin_utilities_upload_size_error_message(),
    'Uploader size error should include the maximum size.'
);

ob_start();
pdl_admin_utilities_upload_limit_admin_script();
$upload_script = ob_get_clean();
assert_same(
    true,
    false !== strpos( $upload_script, 'file_exceeds_size_limit' ) && false !== strpos( $upload_script, '2Mb' ),
    'Uploader script should override the default max-size message with an explicit limit.'
);

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
        'name' => 'exact.jpg',
        'type' => 'image/jpeg',
        'size' => 2 * 1024 * 1024,
    ]
);
assert_same( null, $upload['error'] ?? null, 'Images at exactly 2MB should be allowed.' );

$upload = pdl_admin_utilities_validate_image_upload(
    [
        'name' => 'large.jpg',
        'type' => 'image/jpeg',
        'size' => ( 2 * 1024 * 1024 ) + 1,
    ]
);
assert_same( 'Website được thiết kế để tối ưu trải nghiệm người dùng, chỉ cho phép upload ảnh tối đa 2Mb, vui lòng giảm dung lượng ảnh trước khi upload.', $upload['error'], 'Images over 2MB should be blocked.' );

$upload = pdl_admin_utilities_validate_image_upload(
    [
        'name' => 'document.pdf',
        'type' => 'application/pdf',
        'size' => 500,
    ]
);
assert_same( 'PDL chỉ cho phép upload file hình ảnh.', $upload['error'], 'Non-image uploads should be blocked.' );

echo "admin-utilities-module-test OK\n";
