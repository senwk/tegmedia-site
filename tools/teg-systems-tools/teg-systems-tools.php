<?php
/**
 * Plugin Name:       TEG Systems Tools
 * Description:       Lets TEG Systems read this site and draft pages through the MCP Adapter. It cannot delete anything or see customer orders. Every change is listed under Tools, TEG Systems activity. Deactivate to remove the tools.
 * Version:           1.0.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            TEG Media
 * License:           GPL-2.0-or-later
 * Text Domain:       teg-systems-tools
 *
 * Claude connects to the site through this plugin (the application password is named TEGSystems).
 *
 * How it fits together: WordPress's Abilities API holds the tools, the MCP Adapter plugin serves
 * them to Claude, and the application password decides whose permissions they run with. Each
 * tool below checks that account's capabilities before it does anything.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types Claude may read, and the smaller sets it may change.
 */
function teg_ct_types( $set = 'read' ) {
	$sets = array(
		'read'   => array( 'page', 'post', 'product', 'wp_block', 'elementor_library' ),
		'save'   => array( 'page', 'post' ),
		'status' => array( 'page', 'post', 'product' ),
	);
	return $sets[ $set ];
}

/* ------------------------------------------------------------------------------------------ */
/* Registration                                                                               */
/* ------------------------------------------------------------------------------------------ */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'teg',
			array(
				'label'       => 'TEG Media',
				'description' => 'Tools for working on the TEG Media website.',
			)
		);
	}
);

add_action( 'wp_abilities_api_init', 'teg_ct_register_abilities' );

/**
 * Registers one tool with the settings every TEG tool shares.
 */
function teg_ct_ability( $name, array $args, $readonly, $idempotent = true ) {
	$args = array_merge(
		array(
			'category'      => 'teg',
			'output_schema' => array( 'type' => 'object' ),
			'meta'          => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => false,
					'idempotent'  => $idempotent,
				),
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		),
		$args
	);
	wp_register_ability( $name, $args );
}

function teg_ct_register_abilities() {
	$paging = array(
		'page'     => array(
			'type'    => 'integer',
			'minimum' => 1,
			'default' => 1,
		),
		'per_page' => array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 100,
			'default' => 50,
		),
	);

	teg_ct_ability(
		'teg/site-overview',
		array(
			'label'               => 'Site overview',
			'description'         => 'WordPress, PHP, theme and plugin versions with any pending updates; reading, permalink and search engine settings; and for WooCommerce: store pages, site visibility, payment methods (test-mode flags only, never keys), guest checkout, and which emails are on and who they go to.',
			'execute_callback'    => 'teg_ct_site_overview',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/list-content',
		array(
			'label'               => 'List pages, posts or products',
			'description'         => 'Lists pages, posts, products, reusable blocks or Elementor templates, including drafts and private ones, with slug, status, template, link and whether Elementor builds it. Products also show price, SKU and stock.',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
				'properties'           => array_merge(
					array(
						'post_type' => array(
							'type'    => 'string',
							'enum'    => teg_ct_types(),
							'default' => 'page',
						),
						'status'    => array(
							'type'        => 'string',
							'enum'        => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
							'default'     => 'any',
							'description' => 'Any means everything except the bin.',
						),
						'search'    => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'parent'    => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Only children of this page. 0 for top-level pages.',
						),
					),
					$paging
				),
			),
			'execute_callback'    => 'teg_ct_list_content',
			'permission_callback' => function ( $input ) {
				$in = (array) $input;
				return teg_ct_can_type( isset( $in['post_type'] ) ? $in['post_type'] : 'page', 'edit_posts' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/get-content',
		array(
			'label'               => 'Read one page, post or product',
			'description'         => 'Returns one item in full: raw content, excerpt, template, featured image and the names of its custom fields. For Elementor pages it also returns the words, images and links on the page (elementor: text), or the full Elementor layout (elementor: raw). Ask for specific custom field values with meta.',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'id' ),
				'properties'           => array(
					'id'        => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'elementor' => array(
						'type'    => 'string',
						'enum'    => array( 'text', 'raw', 'none' ),
						'default' => 'text',
					),
					'meta'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'maxItems'    => 30,
						'description' => 'Custom field names whose values to return.',
					),
				),
			),
			'execute_callback'    => 'teg_ct_get_content',
			'permission_callback' => function ( $input ) {
				$in = (array) $input;
				return teg_ct_can_post( isset( $in['id'] ) ? (int) $in['id'] : 0, 'read' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/list-menus',
		array(
			'label'               => 'List menus',
			'description'         => 'The theme menu locations, and every menu with its items, links and nesting.',
			'execute_callback'    => 'teg_ct_list_menus',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/list-media',
		array(
			'label'               => 'Search the media library',
			'description'         => 'Finds files in the media library with their address, size in pixels, alt text and upload date. Newest first.',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
				'properties'           => array_merge(
					array(
						'search' => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'type'   => array(
							'type'    => 'string',
							'enum'    => array( 'image', 'video', 'audio', 'application', 'any' ),
							'default' => 'image',
						),
					),
					$paging
				),
			),
			'execute_callback'    => 'teg_ct_list_media',
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/activity-log',
		array(
			'label'               => 'Activity log',
			'description'         => 'Every change made through these tools, newest first: when, which tool, which item and what changed.',
			'execute_callback'    => function () {
				return array( 'entries' => teg_ct_get_log() );
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
		),
		true
	);

	teg_ct_ability(
		'teg/save-page',
		array(
			'label'               => 'Create or update a page or post',
			'description'         => 'Creates a page or post (as a draft unless a status is given), or updates one by id. Only the fields you send are changed. Every save keeps a revision, so it can be undone from the editor. Elementor pages ignore plain content, and the result warns when that happens.',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'        => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Leave out to create a new one.',
					),
					'post_type' => array(
						'type'    => 'string',
						'enum'    => teg_ct_types( 'save' ),
						'default' => 'page',
					),
					'title'     => array(
						'type'      => 'string',
						'maxLength' => 300,
					),
					'slug'      => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
					'content'   => array( 'type' => 'string' ),
					'excerpt'   => array( 'type' => 'string' ),
					'status'    => array(
						'type' => 'string',
						'enum' => array( 'draft', 'pending', 'private', 'publish' ),
					),
					'parent'    => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'order'     => array( 'type' => 'integer' ),
					'template'  => array(
						'type'        => 'string',
						'maxLength'   => 200,
						'description' => 'A page template file from the active theme, or default.',
					),
				),
			),
			'execute_callback'    => 'teg_ct_save_page',
			'permission_callback' => 'teg_ct_can_save',
		),
		false,
		false
	);

	teg_ct_ability(
		'teg/set-status',
		array(
			'label'               => 'Change the status of a page, post or product',
			'description'         => 'Sets a page, post or product to draft, pending, private or published, for example to take a product off sale. Returns the old status so the change can be reversed. It cannot move anything to the bin.',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'id', 'status' ),
				'properties'           => array(
					'id'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'draft', 'pending', 'private', 'publish' ),
					),
				),
			),
			'execute_callback'    => 'teg_ct_set_status',
			'permission_callback' => function ( $input ) {
				$in    = (array) $input;
				$id    = isset( $in['id'] ) ? (int) $in['id'] : 0;
				$check = teg_ct_can_post( $id, 'status' );
				if ( true !== $check ) {
					return $check;
				}
				$status = isset( $in['status'] ) ? $in['status'] : '';
				return in_array( $status, array( 'publish', 'private' ), true ) ? teg_ct_can_type( get_post_type( $id ), 'publish_posts' ) : true;
			},
		),
		false
	);
}

/**
 * Keeps WooCommerce's order and customer tools, and anything that deletes, out of Claude's reach.
 * They stay available to WordPress itself; they are only hidden from the MCP connection.
 */
add_filter(
	'wp_register_ability_args',
	function ( $args, $name ) {
		if ( 0 === strpos( $name, 'teg/' ) ) {
			return $args;
		}
		if ( preg_match( '#(^|/)(orders?|customers?|refunds?)(-|/|$)#', $name ) || false !== strpos( $name, 'delete' ) ) {
			$meta          = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
			$mcp           = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : array();
			$mcp['public'] = false;
			$meta['mcp']   = $mcp;
			$args['meta']  = $meta;
		}
		return $args;
	},
	99,
	2
);

/* ------------------------------------------------------------------------------------------ */
/* Permission helpers                                                                         */
/* ------------------------------------------------------------------------------------------ */

/**
 * Whether the account holds a post type's capability (edit_posts, publish_posts, create_posts).
 */
function teg_ct_can_type( $type, $cap ) {
	$object = $type ? get_post_type_object( $type ) : null;
	if ( ! $object || ! in_array( $type, teg_ct_types(), true ) ) {
		return new WP_Error( 'teg_ct_type', sprintf( 'There are no %s items on this site that these tools can reach.', $type ) );
	}
	return current_user_can( $object->cap->$cap );
}

/**
 * Whether the account may read or change one item, and whether this tool may touch its type.
 */
function teg_ct_can_post( $id, $set ) {
	$post = $id ? get_post( $id ) : null;
	if ( ! $post ) {
		return new WP_Error( 'teg_ct_missing', sprintf( 'Nothing with id %d.', $id ) );
	}
	if ( ! in_array( $post->post_type, teg_ct_types( $set ), true ) ) {
		return new WP_Error( 'teg_ct_type', sprintf( 'Item %d is a %s, which this tool does not handle.', $id, $post->post_type ) );
	}
	return current_user_can( 'edit_post', $id );
}

function teg_ct_can_save( $input ) {
	$in = (array) $input;
	if ( isset( $in['id'] ) ) {
		$check = teg_ct_can_post( (int) $in['id'], 'save' );
		$type  = get_post_type( (int) $in['id'] );
	} else {
		$type  = isset( $in['post_type'] ) ? $in['post_type'] : 'page';
		$check = teg_ct_can_type( $type, 'create_posts' );
	}
	if ( true !== $check ) {
		return $check;
	}
	if ( isset( $in['status'] ) && in_array( $in['status'], array( 'publish', 'private' ), true ) ) {
		return teg_ct_can_type( $type, 'publish_posts' );
	}
	return true;
}

/* ------------------------------------------------------------------------------------------ */
/* Read tools                                                                                 */
/* ------------------------------------------------------------------------------------------ */

function teg_ct_site_overview() {
	global $wpdb;
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_updates = get_site_transient( 'update_plugins' );
	$plugins        = array();
	foreach ( get_plugins() as $file => $data ) {
		$row = array(
			'name'    => $data['Name'],
			'version' => $data['Version'],
			'active'  => is_plugin_active( $file ),
			'file'    => $file,
		);
		if ( isset( $plugin_updates->response[ $file ]->new_version ) ) {
			$row['update_to'] = $plugin_updates->response[ $file ]->new_version;
		}
		$plugins[] = $row;
	}

	$theme_updates = get_site_transient( 'update_themes' );
	$themes        = array();
	foreach ( wp_get_themes() as $slug => $theme ) {
		$row = array(
			'slug'    => $slug,
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'active'  => get_stylesheet() === $slug,
		);
		if ( $theme->parent() ) {
			$row['parent'] = $theme->get_template();
		}
		if ( isset( $theme_updates->response[ $slug ]['new_version'] ) ) {
			$row['update_to'] = $theme_updates->response[ $slug ]['new_version'];
		}
		$themes[] = $row;
	}

	$core_updates = get_site_transient( 'update_core' );
	$core_update  = null;
	if ( isset( $core_updates->updates[0]->response ) && 'upgrade' === $core_updates->updates[0]->response ) {
		$core_update = $core_updates->updates[0]->current;
	}

	$front = (int) get_option( 'page_on_front' );
	$posts = (int) get_option( 'page_for_posts' );
	$out   = array(
		'site'     => array(
			'name'           => get_bloginfo( 'name' ),
			'tagline'        => get_bloginfo( 'description' ),
			'home'           => home_url( '/' ),
			'environment'    => wp_get_environment_type(),
			'search_engines' => get_option( 'blog_public' ) ? 'allowed' : 'discouraged',
			'permalinks'     => get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'plain',
			'timezone'       => wp_timezone_string(),
		),
		'software' => array(
			'wordpress'           => get_bloginfo( 'version' ),
			'wordpress_update_to' => $core_update,
			'php'                 => PHP_VERSION,
			'database'            => $wpdb->db_version(),
			'server'              => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'memory_limit'        => WP_MEMORY_LIMIT,
			'debug'               => (bool) WP_DEBUG,
			'wp_cron_disabled'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		),
		'reading'  => array(
			'front_shows' => get_option( 'show_on_front' ),
			'front_page'  => $front ? array(
				'id'    => $front,
				'title' => get_post_field( 'post_title', $front ),
			) : null,
			'posts_page'  => $posts ? array(
				'id'    => $posts,
				'title' => get_post_field( 'post_title', $posts ),
			) : null,
		),
		'themes'   => $themes,
		'plugins'  => $plugins,
	);

	if ( function_exists( 'WC' ) ) {
		$out['woocommerce'] = teg_ct_store_overview();
	}
	return $out;
}

function teg_ct_store_overview() {
	$pages = array();
	foreach ( array( 'shop', 'cart', 'checkout', 'myaccount', 'terms' ) as $key ) {
		$id = wc_get_page_id( $key );
		if ( $id <= 0 || ! get_post( $id ) ) {
			$pages[ $key ] = null;
			continue;
		}
		$content = get_post_field( 'post_content', $id );
		$built   = 'classic';
		if ( 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
			$built = 'elementor';
		} elseif ( has_block( 'woocommerce/' . $key, $content ) || has_block( 'woocommerce/classic-shortcode', $content ) ) {
			$built = has_block( 'woocommerce/classic-shortcode', $content ) ? 'classic shortcode block' : 'woocommerce block';
		} elseif ( false !== strpos( $content, '[woocommerce_' ) ) {
			$built = 'shortcode';
		}
		$pages[ $key ] = array(
			'id'       => $id,
			'title'    => get_post_field( 'post_title', $id ),
			'link'     => get_permalink( $id ),
			'built_as' => $built,
		);
	}

	// Payment methods: only the switches that say whether each runs in test mode. Keys and secrets never leave the site.
	$gateways = array();
	foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
		$row = array(
			'id'      => $gateway_id,
			'title'   => wp_strip_all_tags( $gateway->get_title() ),
			'enabled' => 'yes' === $gateway->enabled,
		);
		foreach ( (array) $gateway->settings as $key => $value ) {
			if ( preg_match( '/^(test_?mode|test|sandbox(_?mode)?|environment|mode|debug)$/i', $key ) && is_scalar( $value ) && preg_match( '/^[a-z_]{0,20}$/i', (string) $value ) ) {
				$row['flags'][ $key ] = $value;
			}
		}
		$gateways[] = $row;
	}

	$emails = array();
	foreach ( WC()->mailer()->get_emails() as $email ) {
		$emails[] = array(
			'id'      => $email->id,
			'title'   => $email->get_title(),
			'enabled' => $email->is_enabled(),
			'to'      => $email->is_customer_email() ? 'customer' : $email->get_recipient(),
		);
	}

	$visibility = 'live';
	if ( 'yes' === get_option( 'woocommerce_coming_soon' ) ) {
		$visibility = 'yes' === get_option( 'woocommerce_store_pages_only' ) ? 'store coming soon' : 'coming soon';
	}

	return array(
		'version'              => WC()->version,
		'site_visibility'      => $visibility,
		'currency'             => get_woocommerce_currency(),
		'pages'                => $pages,
		'guest_checkout'       => get_option( 'woocommerce_enable_guest_checkout' ),
		'accounts_at_checkout' => get_option( 'woocommerce_enable_signup_and_login_from_checkout' ),
		'order_storage'        => 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'posts',
		'email_from'           => array(
			'name'    => get_option( 'woocommerce_email_from_name' ),
			'address' => get_option( 'woocommerce_email_from_address' ),
		),
		'payment_methods'      => $gateways,
		'emails'               => $emails,
	);
}

function teg_ct_editor( WP_Post $post ) {
	if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
		return 'elementor';
	}
	return has_blocks( $post->post_content ) ? 'blocks' : 'classic';
}

function teg_ct_summary( WP_Post $post ) {
	$row = array(
		'id'       => $post->ID,
		'type'     => $post->post_type,
		'title'    => $post->post_title,
		'slug'     => $post->post_name,
		'status'   => $post->post_status,
		'parent'   => $post->post_parent,
		'order'    => $post->menu_order,
		'date'     => $post->post_date,
		'modified' => $post->post_modified,
		'link'     => get_permalink( $post ),
		'editor'   => teg_ct_editor( $post ),
	);
	$template = get_page_template_slug( $post );
	if ( $template ) {
		$row['template'] = $template;
	}
	if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $post );
		if ( $product ) {
			$row['product'] = array(
				'type'          => $product->get_type(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'sku'           => $product->get_sku(),
				'stock_status'  => $product->get_stock_status(),
				'visibility'    => $product->get_catalog_visibility(),
				'virtual'       => $product->is_virtual(),
			);
		}
	}
	return $row;
}

function teg_ct_list_content( $input ) {
	$in   = wp_parse_args(
		(array) $input,
		array(
			'post_type' => 'page',
			'status'    => 'any',
			'page'      => 1,
			'per_page'  => 50,
		)
	);
	$type = $in['post_type'];
	$args = array(
		'post_type'      => $type,
		'post_status'    => $in['status'],
		'posts_per_page' => min( 100, max( 1, (int) $in['per_page'] ) ),
		'paged'          => max( 1, (int) $in['page'] ),
		'orderby'        => in_array( $type, array( 'post', 'product' ), true ) ? 'date' : 'menu_order title',
		'order'          => in_array( $type, array( 'post', 'product' ), true ) ? 'DESC' : 'ASC',
	);
	if ( ! empty( $in['search'] ) ) {
		$args['s'] = $in['search'];
	}
	if ( isset( $in['parent'] ) ) {
		$args['post_parent'] = (int) $in['parent'];
	}
	$query = new WP_Query( $args );
	return array(
		'total' => (int) $query->found_posts,
		'pages' => (int) $query->max_num_pages,
		'page'  => (int) $args['paged'],
		'items' => array_map( 'teg_ct_summary', $query->posts ),
	);
}

function teg_ct_get_content( $input ) {
	$in   = (array) $input;
	$post = get_post( (int) $in['id'] );
	$out  = teg_ct_summary( $post );

	$out['content'] = $post->post_content;
	$out['excerpt'] = $post->post_excerpt;
	$image          = get_the_post_thumbnail_url( $post, 'full' );
	if ( $image ) {
		$out['featured_image'] = $image;
	}
	$out['meta_keys'] = array_keys( get_post_meta( $post->ID ) );

	if ( ! empty( $in['meta'] ) ) {
		$out['meta'] = array();
		foreach ( (array) $in['meta'] as $key ) {
			if ( metadata_exists( 'post', $post->ID, $key ) ) {
				$value = get_post_meta( $post->ID, $key, true );
				if ( is_string( $value ) && strlen( $value ) > 50000 ) {
					$value = substr( $value, 0, 50000 ) . ' [cut at 50,000 characters]';
				}
				$out['meta'][ $key ] = $value;
			}
		}
	}

	$mode = isset( $in['elementor'] ) ? $in['elementor'] : 'text';
	if ( 'elementor' === $out['editor'] && 'none' !== $mode ) {
		$raw = get_post_meta( $post->ID, '_elementor_data', true );
		if ( 'raw' === $mode ) {
			$out['elementor_data'] = $raw;
		} else {
			$out['elementor_text'] = teg_ct_elementor_text( $raw );
		}
	}

	if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $post );
		if ( $product ) {
			$out['product']['gallery']    = array_values( array_filter( array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() ) ) );
			$terms                        = get_the_terms( $post->ID, 'product_cat' );
			$out['product']['categories'] = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
			if ( $product->is_type( 'variable' ) ) {
				$out['product']['variations'] = array_map(
					function ( $variation ) {
						return array(
							'id'         => $variation['variation_id'],
							'attributes' => $variation['attributes'],
							'price'      => $variation['display_price'],
						);
					},
					$product->get_available_variations()
				);
			}
		}
	}
	return $out;
}

/**
 * The words, images and links on an Elementor page, in page order, without the styling.
 */
function teg_ct_elementor_text( $raw ) {
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $data ) ) {
		return null;
	}
	$out = array(
		'text'   => array(),
		'images' => array(),
		'links'  => array(),
	);
	teg_ct_walk_elementor( $data, $out );
	foreach ( $out as $key => $values ) {
		$out[ $key ] = array_values( array_unique( $values ) );
	}
	return $out;
}

function teg_ct_walk_elementor( array $nodes, array &$out ) {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( ! empty( $node['settings'] ) && is_array( $node['settings'] ) ) {
			teg_ct_collect_settings( $node['settings'], $out );
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			teg_ct_walk_elementor( $node['elements'], $out );
		}
	}
}

function teg_ct_collect_settings( array $settings, array &$out ) {
	$wanted = '/(title|text|editor|content|description|heading|caption|label|name|html|quote|price|subtitle|excerpt)/i';
	$styles = '/(color|typography|font|size|align|width|height|padding|margin|border|shadow|animation|css|class|_id$|position|icon|background|hover|transition|tag|display|style|view|layout|target|source|format|gap|spacing|weight|transform|decoration|letter|opacity|radius|z_index|overflow|blend|filter|motion|duration|delay)/i';
	foreach ( $settings as $key => $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) && is_string( $value['url'] ) && '' !== $value['url'] ) {
				if ( preg_match( '/\.(jpe?g|png|gif|webp|svg|avif)(\?|$)/i', $value['url'] ) ) {
					$out['images'][] = $value['url'];
				} else {
					$out['links'][] = $value['url'];
				}
				continue;
			}
			teg_ct_collect_settings( $value, $out );
			continue;
		}
		if ( ! is_string( $value ) || is_int( $key ) || ! preg_match( $wanted, $key ) || preg_match( $styles, $key ) ) {
			continue;
		}
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' !== $text && preg_match( '/\pL{2}/u', $text ) && ! preg_match( '#^(https?:|/|\#|\{)#', $text ) ) {
			$out['text'][] = $text;
		}
	}
}

function teg_ct_list_menus() {
	$assigned = get_nav_menu_locations();
	$menus    = array();
	foreach ( wp_get_nav_menus() as $menu ) {
		$items = array();
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
			$items[] = array(
				'id'        => $item->ID,
				'title'     => $item->title,
				'url'       => $item->url,
				'type'      => $item->type,
				'object'    => $item->object,
				'object_id' => (int) $item->object_id,
				'parent'    => (int) $item->menu_item_parent,
				'order'     => (int) $item->menu_order,
			);
		}
		$menus[] = array(
			'id'        => $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => array_keys( $assigned, $menu->term_id ),
			'items'     => $items,
		);
	}
	$locations = array();
	foreach ( get_registered_nav_menus() as $location => $label ) {
		$locations[] = array(
			'location' => $location,
			'label'    => $label,
			'menu'     => isset( $assigned[ $location ] ) ? (int) $assigned[ $location ] : null,
		);
	}
	return array(
		'locations' => $locations,
		'menus'     => $menus,
	);
}

function teg_ct_list_media( $input ) {
	$in   = wp_parse_args(
		(array) $input,
		array(
			'type'     => 'image',
			'page'     => 1,
			'per_page' => 50,
		)
	);
	$args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => min( 100, max( 1, (int) $in['per_page'] ) ),
		'paged'          => max( 1, (int) $in['page'] ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( 'any' !== $in['type'] ) {
		$args['post_mime_type'] = $in['type'];
	}
	if ( ! empty( $in['search'] ) ) {
		$args['s'] = $in['search'];
	}
	$query = new WP_Query( $args );
	$items = array();
	foreach ( $query->posts as $file ) {
		$meta    = wp_get_attachment_metadata( $file->ID );
		$items[] = array(
			'id'       => $file->ID,
			'title'    => $file->post_title,
			'url'      => wp_get_attachment_url( $file->ID ),
			'mime'     => $file->post_mime_type,
			'width'    => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'   => isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'bytes'    => isset( $meta['filesize'] ) ? (int) $meta['filesize'] : null,
			'alt'      => get_post_meta( $file->ID, '_wp_attachment_image_alt', true ),
			'uploaded' => $file->post_date,
			'used_on'  => $file->post_parent ? $file->post_parent : null,
		);
	}
	return array(
		'total' => (int) $query->found_posts,
		'pages' => (int) $query->max_num_pages,
		'page'  => (int) $args['paged'],
		'items' => $items,
	);
}

/* ------------------------------------------------------------------------------------------ */
/* Change tools                                                                               */
/* ------------------------------------------------------------------------------------------ */

function teg_ct_save_page( $input ) {
	$in     = (array) $input;
	$id     = isset( $in['id'] ) ? (int) $in['id'] : 0;
	$type   = $id ? get_post_type( $id ) : ( isset( $in['post_type'] ) ? $in['post_type'] : 'page' );
	$before = $id ? get_post( $id ) : null;

	if ( ! $id && ( ! isset( $in['title'] ) || '' === trim( $in['title'] ) ) ) {
		return new WP_Error( 'teg_ct_title', 'A new page needs a title.' );
	}
	if ( isset( $in['template'] ) && '' !== $in['template'] && 'default' !== $in['template'] ) {
		$templates = wp_get_theme()->get_page_templates( null, $type );
		if ( ! isset( $templates[ $in['template'] ] ) ) {
			return new WP_Error( 'teg_ct_template', sprintf( 'The active theme has no template called %s. It has: %s.', $in['template'], implode( ', ', array_keys( $templates ) ) ?: 'none' ) );
		}
	}

	$fields = array(
		'title'    => 'post_title',
		'slug'     => 'post_name',
		'content'  => 'post_content',
		'excerpt'  => 'post_excerpt',
		'status'   => 'post_status',
		'parent'   => 'post_parent',
		'order'    => 'menu_order',
		'template' => 'page_template',
	);
	$data   = $id ? array( 'ID' => $id ) : array(
		'post_type'   => $type,
		'post_status' => 'draft',
	);
	foreach ( $fields as $from => $to ) {
		if ( isset( $in[ $from ] ) ) {
			$data[ $to ] = $in[ $from ];
		}
	}
	if ( isset( $data['post_name'] ) ) {
		$data['post_name'] = sanitize_title( $data['post_name'] );
	}
	if ( isset( $data['page_template'] ) && '' === $data['page_template'] ) {
		$data['page_template'] = 'default';
	}

	$result = $id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$after    = get_post( $result );
	$changed  = array_values( array_intersect( array_keys( $fields ), array_keys( $in ) ) );
	$warnings = array();
	if ( isset( $in['content'] ) && 'elementor' === teg_ct_editor( $after ) ) {
		$warnings[] = 'This page is built with Elementor, which ignores this content. The change will not show on the page until it stops using Elementor.';
	}
	if ( $before && $before->post_status !== $after->post_status ) {
		$what = sprintf( 'status from %s to %s; changed %s', $before->post_status, $after->post_status, implode( ', ', $changed ) );
	} else {
		$what = ( $id ? 'changed ' . implode( ', ', $changed ) : 'created as ' . $after->post_status );
	}
	teg_ct_log( 'save-page', $result, $what );

	return array(
		'id'        => $result,
		'created'   => ! $id,
		'status'    => $after->post_status,
		'link'      => get_permalink( $after ),
		'edit_link' => get_edit_post_link( $result, 'raw' ),
		'revisions' => count( wp_get_post_revisions( $result, array( 'fields' => 'ids' ) ) ),
		'warnings'  => $warnings,
	);
}

function teg_ct_set_status( $input ) {
	$in     = (array) $input;
	$id     = (int) $in['id'];
	$status = $in['status'];
	$post   = get_post( $id );
	$from   = $post->post_status;

	if ( $from === $status ) {
		return array(
			'id'      => $id,
			'title'   => $post->post_title,
			'status'  => $status,
			'changed' => false,
		);
	}

	if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $id );
		$product->set_status( $status );
		$product->save();
	} else {
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$now = get_post_status( $id );
	teg_ct_log( 'set-status', $id, sprintf( 'status from %s to %s', $from, $now ) );
	return array(
		'id'      => $id,
		'title'   => $post->post_title,
		'from'    => $from,
		'status'  => $now,
		'changed' => $from !== $now,
		'link'    => get_permalink( $id ),
	);
}

/* ------------------------------------------------------------------------------------------ */
/* Activity log, and its screen under Tools                                                   */
/* ------------------------------------------------------------------------------------------ */

function teg_ct_get_log() {
	$log = get_option( 'teg_ct_log', array() );
	return is_array( $log ) ? $log : array();
}

function teg_ct_log( $tool, $post_id, $what ) {
	$log = teg_ct_get_log();
	array_unshift(
		$log,
		array(
			'time'    => current_time( 'mysql' ),
			'account' => wp_get_current_user()->user_login,
			'tool'    => $tool,
			'id'      => (int) $post_id,
			'title'   => get_post_field( 'post_title', $post_id ),
			'what'    => $what,
		)
	);
	update_option( 'teg_ct_log', array_slice( $log, 0, 300 ), false );
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'TEG Systems activity', 'TEG Systems activity', 'edit_pages', 'teg-systems-activity', 'teg_ct_activity_screen' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=teg-systems-activity' ) ) . '">TEG Systems activity</a>' );
		return $links;
	}
);

function teg_ct_activity_screen() {
	$log = teg_ct_get_log();
	echo '<div class="wrap"><h1>TEG Systems activity</h1>';
	echo '<p>Every change made through TEG Systems Tools, newest first. Page and post edits are also kept as revisions, so each one can be restored from the editor.</p>';
	if ( ! $log ) {
		echo '<p><em>No changes yet.</em></p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>When</th><th>Tool</th><th>Item</th><th>What changed</th><th>Account</th></tr></thead><tbody>';
	foreach ( $log as $row ) {
		$link = $row['id'] ? get_edit_post_link( $row['id'] ) : '';
		$name = $row['title'] ? $row['title'] : '#' . $row['id'];
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( $row['time'] ),
			esc_html( $row['tool'] ),
			$link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ),
			esc_html( $row['what'] ),
			esc_html( $row['account'] )
		);
	}
	echo '</tbody></table></div>';
}

add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>TEG Systems Tools needs WordPress 6.9 or later.</p></div>';
		}
	}
);
