<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {

    // Danh sách menu muốn ẩn — comment lại dòng nào không muốn ẩn
    $hidden_menus = [
        'edit-comments.php',       // Comments
        'tools.php',               // Tools
        'edit.php?post_type=page', // Pages (nếu không dùng)
    ];

    foreach ( $hidden_menus as $menu ) {
        remove_menu_page( $menu );
    }

}, 999 );