<?php

/**
 * Edit Posts Administration Screen - Grid
 */

require_once plugin_dir_path(__FILE__)."../class-wp-list-grid.php";
require_once plugin_dir_path(__FILE__)."../class-wp-posts-list-grid.php";

function override_edit_post_per_page($per_page) {
	
	$user = wp_get_current_user();

	$pp = get_option('cheritto-admin-grid-per-page-' . $user->user_nicename);

	if (!$pp) $pp=12;

	return (int) $pp;
}
add_filter( 'edit_post_per_page', 'override_edit_post_per_page');

function redirect_on_headers_sent($url)
{
    $string = '<script type="text/javascript">';
    $string .= 'window.location = "' . $url . '"';
    $string .= '</script>';

    echo $string;
}

$user = wp_get_current_user();

$typenow="post";

/**
 * @global string       $post_type
 * @global WP_Post_Type $post_type_object
 */
global $post_type, $post_type_object;

$current_screen = convert_to_screen( 'edit-post' );

$wp_list_table = new WP_Posts_List_Grid( ['screen' => $current_screen] );
$pagenum       = $wp_list_table->get_pagenum();

$post_type        = $typenow;
$post_type_object = get_post_type_object( $post_type );

if ( ! $post_type_object ) {
	wp_die( __( 'Invalid post type.' ) );
}

if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to edit posts in this post type.' ) . '</p>',
		403
	);
}

// Back-compat for viewing comments of an entry.
foreach ( array( 'p', 'attachment_id', 'page_id' ) as $_redirect ) {
	if ( ! empty( $_REQUEST[ $_redirect ] ) ) {
		wp_redirect( admin_url( 'edit-comments.php?p=' . absint( $_REQUEST[ $_redirect ] ) ) );
		exit;
	}
}
unset( $_redirect );

if ( 'post' !== $post_type ) {
	$parent_file   = "edit.php?post_type=$post_type";
	$submenu_file  = "edit.php?post_type=$post_type";
	$post_new_file = "post-new.php?post_type=$post_type";
} else {
	$parent_file   = 'edit.php';
	$submenu_file  = 'edit.php';
	$post_new_file = 'post-new.php';
}

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-posts' );

	$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), wp_get_referer() );
	if ( ! $sendback ) {
		$sendback = admin_url( $parent_file );
	}
	$sendback = add_query_arg( 'paged', $pagenum, $sendback );
	if ( strpos( $sendback, 'post.php' ) !== false ) {
		$sendback = admin_url( $post_new_file );
	}

	$post_ids = array();

	if ( 'delete_all' === $doaction ) {
		// Prepare for deletion of all posts with a specified post status (i.e. Empty Trash).
		$post_status = preg_replace( '/[^a-z0-9_-]+/i', '', sanitize_text_field($_REQUEST['post_status']) );
		// Validate the post status exists.
		if ( get_post_status_object( $post_status ) ) {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", $post_type, $post_status ) );
		}
		$doaction = 'delete';
	} elseif ( isset( $_REQUEST['ids'] ) ) {
		$post_ids = explode( ',', sanitize_text_field($_REQUEST['ids']));
		// Further sanitization
		$post_ids = array_map( 'intval', $post_ids );
	} elseif ( ! empty( $_REQUEST['post'] ) ) {
		$post_ids = array_map( 'intval', $_REQUEST['post'] );
	}

	if ( empty( $post_ids ) ) {
		wp_redirect( $sendback );
		exit;
	}

	switch ( $doaction ) {
		case 'trash':
			$trashed = 0;
			$locked  = 0;

			foreach ( (array) $post_ids as $post_id ) {
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					wp_die( __( 'Sorry, you are not allowed to move this item to the Trash.' ) );
				}

				if ( wp_check_post_lock( $post_id ) ) {
					$locked++;
					continue;
				}

				if ( ! wp_trash_post( $post_id ) ) {
					wp_die( __( 'Error in moving the item to Trash.' ) );
				}

				$trashed++;
			}

			$sendback = add_query_arg(
				array(
					'trashed' => $trashed,
					'ids'     => implode( ',', $post_ids ),
					'locked'  => $locked,
				),
				$sendback
			);
			break;
		case 'untrash':
			$untrashed = 0;

			if ( isset( $_GET['doaction'] ) && ( 'undo' === $_GET['doaction'] ) ) {
				add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
			}

			foreach ( (array) $post_ids as $post_id ) {
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					wp_die( __( 'Sorry, you are not allowed to restore this item from the Trash.' ) );
				}

				if ( ! wp_untrash_post( $post_id ) ) {
					wp_die( __( 'Error in restoring the item from Trash.' ) );
				}

				$untrashed++;
			}
			$sendback = add_query_arg( 'untrashed', $untrashed, $sendback );

			remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );

			break;
		case 'delete':
			$deleted = 0;
			foreach ( (array) $post_ids as $post_id ) {
				$post_del = get_post( $post_id );

				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					wp_die( __( 'Sorry, you are not allowed to delete this item.' ) );
				}

				if ( 'attachment' === $post_del->post_type ) {
					if ( ! wp_delete_attachment( $post_id ) ) {
						wp_die( __( 'Error in deleting the attachment.' ) );
					}
				} else {
					if ( ! wp_delete_post( $post_id ) ) {
						wp_die( __( 'Error in deleting the item.' ) );
					}
				}
				$deleted++;
			}
			$sendback = add_query_arg( 'deleted', $deleted, $sendback );
			break;
		case 'edit':
			if ( isset( $_REQUEST['bulk_edit'] ) ) {
				$done = bulk_edit_posts( $_REQUEST );

				if ( is_array( $done ) ) {
					$done['updated'] = count( $done['updated'] );
					$done['skipped'] = count( $done['skipped'] );
					$done['locked']  = count( $done['locked'] );
					$sendback        = add_query_arg( $done, $sendback );
				}
			}
			break;
		default:
			$screen = get_current_screen()->id;

			$sendback = apply_filters( "handle_bulk_actions-{$screen}", $sendback, $doaction, $post_ids ); 
			break;
	}

	$sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );
	
	redirect_on_headers_sent( $sendback );
	
	exit;
} 

$wp_list_table->prepare_items();

wp_enqueue_script( 'tags-suggest' );
wp_enqueue_script( 'cheritto-admin-grid-inline-edit-post', plugin_dir_url( __FILE__ ) . '../js/cheritto-admin-grid-inline-edit-post.js' );
wp_enqueue_script( 'heartbeat' );


if ( 'wp_block' === $post_type ) {
	wp_enqueue_script( 'wp-list-reusable-blocks' );
	wp_enqueue_style( 'wp-list-reusable-blocks' );
}

// Used in the HTML title tag.
$title = $post_type_object->labels->name;

if ( 'post' === $post_type ) {
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'overview',
			'title'   => __( 'Overview' ),
			'content' =>
					'<p>' . __( 'This screen provides access to all of your posts. You can customize the display of this screen to suit your workflow.' ) . '</p>',
		)
	);
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'screen-content',
			'title'   => __( 'Screen Content' ),
			'content' =>
					'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:' ) . '</p>' .
					'<ul>' .
						'<li>' . __( 'You can hide/display columns based on your needs and decide how many posts to list per screen using the Screen Options tab.' ) . '</li>' .
						'<li>' . __( 'You can filter the list of posts by post status using the text links above the posts list to only show posts with that status. The default view is to show all posts.' ) . '</li>' .
						'<li>' . __( 'You can view posts in a simple title list or with an excerpt using the Screen Options tab.' ) . '</li>' .
						'<li>' . __( 'You can refine the list to show only posts in a specific category or from a specific month by using the dropdown menus above the posts list. Click the Filter button after making your selection. You also can refine the list by clicking on the post author, category or tag in the posts list.' ) . '</li>' .
					'</ul>',
		)
	);
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'action-links',
			'title'   => __( 'Available Actions' ),
			'content' =>
					'<p>' . __( 'Hovering over a row in the posts list will display action links that allow you to manage your post. You can perform the following actions:' ) . '</p>' .
					'<ul>' .
						'<li>' . __( '<strong>Edit</strong> takes you to the editing screen for that post. You can also reach that screen by clicking on the post title.' ) . '</li>' .
						'<li>' . __( '<strong>Quick Edit</strong> provides inline access to the metadata of your post, allowing you to update post details without leaving this screen.' ) . '</li>' .
						'<li>' . __( '<strong>Trash</strong> removes your post from this list and places it in the Trash, from which you can permanently delete it.' ) . '</li>' .
						'<li>' . __( '<strong>Preview</strong> will show you what your draft post will look like if you publish it. View will take you to your live site to view the post. Which link is available depends on your post&#8217;s status.' ) . '</li>' .
					'</ul>',
		)
	);
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'bulk-actions',
			'title'   => __( 'Bulk actions' ),
			'content' =>
					'<p>' . __( 'You can also edit or move multiple posts to the Trash at once. Select the posts you want to act on using the checkboxes, then select the action you want to take from the Bulk actions menu and click Apply.' ) . '</p>' .
							'<p>' . __( 'When using Bulk Edit, you can change the metadata (categories, author, etc.) for all selected posts at once. To remove a post from the grouping, just click the x next to its name in the Bulk Edit area that appears.' ) . '</p>',
		)
	);

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://wordpress.org/support/article/posts-screen/">Documentation on Managing Posts</a>' ) . '</p>' .
		'<p>' . __( '<a href="https://wordpress.org/support/">Support</a>' ) . '</p>'
	);

} elseif ( 'page' === $post_type ) {
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'overview',
			'title'   => __( 'Overview' ),
			'content' =>
					'<p>' . __( 'Pages are similar to posts in that they have a title, body text, and associated metadata, but they are different in that they are not part of the chronological blog stream, kind of like permanent posts. Pages are not categorized or tagged, but can have a hierarchy. You can nest pages under other pages by making one the &#8220;Parent&#8221; of the other, creating a group of pages.' ) . '</p>',
		)
	);
	get_current_screen()->add_help_tab(
		array(
			'id'      => 'managing-pages',
			'title'   => __( 'Managing Pages' ),
			'content' =>
					'<p>' . __( 'Managing pages is very similar to managing posts, and the screens can be customized in the same way.' ) . '</p>' .
					'<p>' . __( 'You can also perform the same types of actions, including narrowing the list by using the filters, acting on a page using the action links that appear when you hover over a row, or using the Bulk actions menu to edit the metadata for multiple pages at once.' ) . '</p>',
		)
	);

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://wordpress.org/support/article/pages-screen/">Documentation on Managing Pages</a>' ) . '</p>' .
		'<p>' . __( '<a href="https://wordpress.org/support/">Support</a>' ) . '</p>'
	);

}

get_current_screen()->set_screen_reader_content(
	array(
		'heading_views'      => $post_type_object->labels->filter_items_list,
		'heading_pagination' => $post_type_object->labels->items_list_navigation,
		'heading_list'       => $post_type_object->labels->items_list,
	)
);

add_screen_option(
	'per_page',
	array(
		'default' => 20,
		'option'  => 'edit_' . $post_type . '_per_page',
	)
);

$bulk_counts = array(
	'updated'   => isset( $_REQUEST['updated'] ) ? absint( $_REQUEST['updated'] ) : 0,
	'locked'    => isset( $_REQUEST['locked'] ) ? absint( $_REQUEST['locked'] ) : 0,
	'deleted'   => isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0,
	'trashed'   => isset( $_REQUEST['trashed'] ) ? absint( $_REQUEST['trashed'] ) : 0,
	'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
);

$bulk_messages             = array();
$bulk_messages['post']     = array(
	/* translators: %s: Number of posts. */
	'updated'   => _n( '%s post updated.', '%s posts updated.', $bulk_counts['updated'] ),
	'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 post not updated, somebody is editing it.' ) :
					/* translators: %s: Number of posts. */
					_n( '%s post not updated, somebody is editing it.', '%s posts not updated, somebody is editing them.', $bulk_counts['locked'] ),
	/* translators: %s: Number of posts. */
	'deleted'   => _n( '%s post permanently deleted.', '%s posts permanently deleted.', $bulk_counts['deleted'] ),
	/* translators: %s: Number of posts. */
	'trashed'   => _n( '%s post moved to the Trash.', '%s posts moved to the Trash.', $bulk_counts['trashed'] ),
	/* translators: %s: Number of posts. */
	'untrashed' => _n( '%s post restored from the Trash.', '%s posts restored from the Trash.', $bulk_counts['untrashed'] ),
);
$bulk_messages['page']     = array(
	/* translators: %s: Number of pages. */
	'updated'   => _n( '%s page updated.', '%s pages updated.', $bulk_counts['updated'] ),
	'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 page not updated, somebody is editing it.' ) :
					/* translators: %s: Number of pages. */
					_n( '%s page not updated, somebody is editing it.', '%s pages not updated, somebody is editing them.', $bulk_counts['locked'] ),
	/* translators: %s: Number of pages. */
	'deleted'   => _n( '%s page permanently deleted.', '%s pages permanently deleted.', $bulk_counts['deleted'] ),
	/* translators: %s: Number of pages. */
	'trashed'   => _n( '%s page moved to the Trash.', '%s pages moved to the Trash.', $bulk_counts['trashed'] ),
	/* translators: %s: Number of pages. */
	'untrashed' => _n( '%s page restored from the Trash.', '%s pages restored from the Trash.', $bulk_counts['untrashed'] ),
);
$bulk_messages['wp_block'] = array(
	/* translators: %s: Number of blocks. */
	'updated'   => _n( '%s block updated.', '%s blocks updated.', $bulk_counts['updated'] ),
	'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 block not updated, somebody is editing it.' ) :
					/* translators: %s: Number of blocks. */
					_n( '%s block not updated, somebody is editing it.', '%s blocks not updated, somebody is editing them.', $bulk_counts['locked'] ),
	/* translators: %s: Number of blocks. */
	'deleted'   => _n( '%s block permanently deleted.', '%s blocks permanently deleted.', $bulk_counts['deleted'] ),
	/* translators: %s: Number of blocks. */
	'trashed'   => _n( '%s block moved to the Trash.', '%s blocks moved to the Trash.', $bulk_counts['trashed'] ),
	/* translators: %s: Number of blocks. */
	'untrashed' => _n( '%s block restored from the Trash.', '%s blocks restored from the Trash.', $bulk_counts['untrashed'] ),
);

$bulk_messages = apply_filters( 'bulk_post_updated_messages', $bulk_messages, $bulk_counts );
$bulk_counts   = array_filter( $bulk_counts );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<?php // Set theme ?>
<script type="text/javascript">
	jQuery("#wpcontent").addClass("cheritto-admin-grid-bg-<?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename); ?>" );
</script>

<div class="wrap">
<h1 class="wp-heading-inline">
<?php
echo esc_html( $post_type_object->labels->name );
?>
</h1>

<?php
if ( current_user_can( $post_type_object->cap->create_posts ) ) {
	echo ' <a href="' . esc_url( admin_url( $post_new_file ) ) . '" class="page-title-action">' . esc_html( $post_type_object->labels->add_new ) . '</a>';
}

if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	echo '<span class="subtitle">';
	printf(
		/* translators: %s: Search query. */
		__( 'Search results for: %s' ),
		'<strong>' . get_search_query() . '</strong>'
	);
	echo '</span>';
}
?>

<hr class="wp-header-end">

<?php
// If we have a bulk message to issue:
$messages = array();
foreach ( $bulk_counts as $message => $count ) {
	if ( isset( $bulk_messages[ $post_type ][ $message ] ) ) {
		$messages[] = sprintf( $bulk_messages[ $post_type ][ $message ], number_format_i18n( $count ) );
	} elseif ( isset( $bulk_messages['post'][ $message ] ) ) {
		$messages[] = sprintf( $bulk_messages['post'][ $message ], number_format_i18n( $count ) );
	}

	if ( 'trashed' === $message && isset( $_REQUEST['ids'] ) ) {
		$ids        = preg_replace( '/[^0-9,]/', '', $_REQUEST['ids'] );
		$messages[] = '<a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", 'bulk-posts' ) ) . '">' . __( 'Undo' ) . '</a>';
	}

	if ( 'untrashed' === $message && isset( $_REQUEST['ids'] ) ) {
		$ids = explode( ',', sanitize_text_field($_REQUEST['ids']) );
		// Further sanitization
		$ids = array_map( 'intval', $ids );
		if ( 1 === count( $ids ) && current_user_can( 'edit_post', $ids[0] ) ) {
			$messages[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( get_edit_post_link( $ids[0] ) ),
				esc_html( get_post_type_object( get_post_type( $ids[0] ) )->labels->edit_item )
			);
		}
	}
}

if ( $messages ) {
	echo '<div id="message" class="updated notice is-dismissible"><p>' . implode( ' ', $messages ) . '</p></div>';
}
unset( $messages );

$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'locked', 'skipped', 'updated', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
?>

<?php $wp_list_table->views(); ?>

<div class="tablenav top cheritto-admin-grid-options-container">
	<form method="post" action="options.php">

		<?php 
			settings_fields( 'cheritto-admin-grid' ); 
			do_settings_sections( 'cheritto-admin-grid' );
		?>

		<div class="alignleft" style="margin-right:15px;">
			<label for="cheritto-admin-grid-columns-count-<?php echo esc_attr($user->user_nicename);  ?>"></label>
			<select name="cheritto-admin-grid-columns-count-<?php echo esc_attr($user->user_nicename);  ?>" value="<?php echo esc_attr( get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename) ); ?>">
				<option value="1" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==1?'selected':'' ?> >1 column</option>
				<option value="2" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==2?'selected':'' ?> >2 columns</option>
				<option value="3" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==3?'selected':'' ?> >3 columns</option>
				<option value="4" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==4?'selected':'' ?> >4 columns</option>
				<option value="5" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==5?'selected':'' ?> >5 columns</option>
				<option value="6" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==6?'selected':'' ?> >6 columns</option>
				<option value="7" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==7?'selected':'' ?> >7 columns</option>
				<option value="8" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==8?'selected':'' ?> >8 columns</option>
				<option value="9" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==9?'selected':'' ?> >9 columns</option>
				<option value="10" <?php echo get_option('cheritto-admin-grid-columns-count-' . $user->user_nicename)==10?'selected':'' ?> >10 columns</option>
			</select>
		</div>

		<div class="alignleft" style="margin-right:15px;">
			<label for="cheritto-admin-grid-per-page-<?php echo esc_attr($user->user_nicename);  ?>"><?php echo __("Items per page: "); ?></label>
			<input style="width:50px;" type="number"  name="cheritto-admin-grid-per-page-<?php echo esc_attr($user->user_nicename);  ?>" value="<?php echo (int) esc_attr( get_option('cheritto-admin-grid-per-page-' . $user->user_nicename) ); ?>">
		</div>

		<div class="alignleft" style="margin-right:15px;line-height:30px;">
			<label for="cheritto-admin-grid-show-image-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-image-<?php echo esc_attr($user->user_nicename); ?>" <?php echo get_option('cheritto-admin-grid-show-image-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Image"); ?>
			</label>
			<label for="cheritto-admin-grid-show-title-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-title-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-title-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Title"); ?>
			</label>
			<label for="cheritto-admin-grid-show-author-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-author-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-author-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Authors"); ?>
			</label>
			<label for="cheritto-admin-grid-show-categories-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-categories-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-categories-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Categories"); ?>
			</label>
			<label for="cheritto-admin-grid-show-tags-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-tags-<?php echo esc_attr($user->user_nicename); ?>" <?php echo get_option('cheritto-admin-grid-show-tags-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Tags"); ?>
			</label>
			<label for="cheritto-admin-grid-show-date-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-date-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-date-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Status / Date"); ?>
			</label>
			<label for="cheritto-admin-grid-show-actions-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-actions-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-actions-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Actions"); ?>
			</label>
			<label for="cheritto-admin-grid-show-cb-<?php echo esc_attr($user->user_nicename);  ?>" style="margin-right:10px;">
				<input type="checkbox"  name="cheritto-admin-grid-show-cb-<?php echo esc_attr($user->user_nicename);  ?>" <?php echo get_option('cheritto-admin-grid-show-cb-' . $user->user_nicename)==1 ? 'checked' : '' ?> value="1" />
				<?php echo __("Bulk checkbox"); ?>
			</label>
			
			<input type="submit" id="doaction" class="button action" value="Apply">
		</div>

		<div class="alignleft">
			<label for="cheritto-admin-grid-theme-<?php echo esc_attr($user->user_nicename);  ?>"><?php echo __("Theme "); ?></label>
			<select name="cheritto-admin-grid-theme-<?php echo esc_attr($user->user_nicename);  ?>" value="<?php echo esc_attr( get_option('cheritto-admin-grid-theme-' . $user->user_nicename) ); ?>">
				<option value="wordpress" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='wordpress' || !get_option('cheritto-admin-grid-theme-' . $user->user_nicename) ? 'selected':'' ?> >Wordpress</option>
				<option value="sand" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='sand' ? 'selected':'' ?> >Sand</option>
				<option value="marble" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='marble' ? 'selected':'' ?> >Marble</option>
				<option value="lunar" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='lunar' ? 'selected':'' ?> >Lunar</option>
				<option value="spring" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='spring' ? 'selected':'' ?> >Spring</option>
				<option value="ice" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='ice' ? 'selected':'' ?> >Ice</option>
				<option value="glass" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='glass' ? 'selected':'' ?> >Glass</option>
				<option value="calm-sea" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='calm-sea' ? 'selected':'' ?> >Calm Sea</option>
				<option value="after-dawn" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='after-dawn' ? 'selected':'' ?> >After Dawn</option>
				<option value="elegance" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='elegance' ? 'selected':'' ?> >Elegance</option>
				<option value="aqua" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='aqua' ? 'selected':'' ?> >Aqua</option>
				<option value="snow" <?php echo get_option('cheritto-admin-grid-theme-' . $user->user_nicename)=='snow' ? 'selected':'' ?> >Snow</option>
			</select>
			<input type="submit" id="doaction" class="button action" value="Apply">
		</div>

	</form>
</div>

<form id="posts-filter" method="get" action="<?php echo admin_url( 'admin.php' ); ?>">

<?php $wp_list_table->search_box( $post_type_object->labels->search_items, 'post' ); ?>

<input type="hidden" name="page" value="admin-posts-grid/includes/wp-admin/edit.php" />
<input type="hidden" name="post_status" class="post_status_page" value="<?php echo ! empty( $_REQUEST['post_status'] ) ? esc_attr( $_REQUEST['post_status'] ) : 'all'; ?>" />
<input type="hidden" name="post_type" class="post_type_page" value="<?php echo esc_attr($post_type); ?>" />

<?php if ( ! empty( $_REQUEST['author'] ) ) { ?>
<input type="hidden" name="author" value="<?php echo esc_attr( $_REQUEST['author'] ); ?>" />
<?php } ?>

<?php if ( ! empty( $_REQUEST['show_sticky'] ) ) { ?>
<input type="hidden" name="show_sticky" value="1" />
<?php } ?>

<?php $wp_list_table->display(); ?>

</form>

<?php
if ( $wp_list_table->has_items() ) {
	$wp_list_table->inline_edit();
}
?>

<div id="ajax-response"></div>
<div class="clear"></div>
</div>

<div class="cheritto-admin-grid-modal" id="inlinemodal"></div>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
