<?php

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Cheritto_Admin_Grid
 * @subpackage Cheritto_Admin_Grid/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */

class Cheritto_Admin_Grid {

    protected $version;
    protected $user;

    public function __construct( $version ) {

        $this->version = $version;
        
    }

    public function run() {

        add_action( 'admin_menu', [ $this, 'cheritto_admin_grid_page' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        add_action( 'admin_init', [ $this, 'register_settings' ] );

    }

    public function cheritto_admin_grid_page() {
        $slug = add_menu_page(
            'Posts Grid',
            'Posts Grid',
            'edit_posts',
            plugin_dir_path(__FILE__) . 'wp-admin/edit.php',
            null,
            'dashicons-layout',
            7
        );
        
    }

    public function enqueue_styles($hook)
	{
        if ("admin-posts-grid/includes/wp-admin/edit.php"!=$hook) 
        {
            return;
        }
        
		wp_enqueue_style( 'CHERITTO_ADMIN_GRID', plugin_dir_url( __FILE__ ) . 'css/cheritto-admin-grid.css', array(), $this->version, 'all' );
	}

    public function register_settings()
    {
        $this->user = wp_get_current_user();
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-columns-count-' . $this->user->user_nicename, ['default' => 6] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-container-bg-' . $this->user->user_nicename );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-item-bg-' . $this->user->user_nicename );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-theme-' . $this->user->user_nicename, ['default' => 'ice'] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-per-page-' . $this->user->user_nicename, ['default' => 12] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-image-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-title-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-author-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-categories-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-tags-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-actions-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-date-' . $this->user->user_nicename, ['default' => 1] );
        register_setting( 'cheritto-admin-grid', 'cheritto-admin-grid-show-cb-' . $this->user->user_nicename, ['default' => 0] );
    }
    
}