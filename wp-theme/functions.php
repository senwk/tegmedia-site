<?php
/**
 * TEG Media theme.
 *
 * The designed pages are built from the Heritage mockup by build-wp-theme.js: each one's markup
 * sits in parts/<page>.html and its details in inc/pages.php. This file serves them, sends the
 * contact form, forwards the old pages, and wraps WooCommerce and any other WordPress page in the
 * same header and footer, so the shop and checkout keep working untouched.
 */

defined( 'ABSPATH' ) || exit;

define( 'TEG_THEME_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );

/* the covers and issue links, kept up to date from Calameo (Appearance > TEG Media issues) */
require __DIR__ . '/issues.php';

/* ------------------------------------------------------------------------------------------ */
/* The designed pages                                                                         */
/* ------------------------------------------------------------------------------------------ */

/** Each designed page's title, description, body class, marked nav item and contact band. */
function teg_pages() {
	static $pages = null;
	if ( null === $pages ) {
		$pages = require __DIR__ . '/inc/pages.php';
	}
	return $pages;
}

/** The designed page being shown (set by its template), or null on shop and other pages. */
function teg_current( $key = null ) {
	static $current = null;
	if ( null !== $key ) {
		$current = $key;
	}
	return $current;
}

function teg_page_meta() {
	$pages = teg_pages();
	$key   = teg_current();
	return ( $key && isset( $pages[ $key ] ) ) ? $pages[ $key ] : null;
}

function teg_is_shop_page() {
	return function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );
}

/** Fills the placeholders the build leaves in the HTML with this site's addresses. */
function teg_fill( $html ) {
	$html = teg_cal_fill( $html );
	$html = preg_replace_callback(
		'#\{\{assets\}\}/([A-Za-z0-9/._-]+)#',
		function ( $m ) {
			return esc_url( teg_asset_url( $m[1] ) );
		},
		$html
	);
	$html = strtr(
		$html,
		array(
			'{{captcha}}'    => teg_captcha_html(),
			'{{assets}}'     => esc_url( teg_asset_base() ),
			'{{theme}}'      => get_template_directory_uri(),
			'{{shop}}'       => esc_url( home_url( '/product/' ) ),
			'{{admin-post}}' => esc_url( admin_url( 'admin-post.php' ) ),
		)
	);
	return preg_replace_callback(
		'/\{\{url:([a-z0-9-]*)\}\}/',
		function ( $m ) {
			return esc_url( '' === $m[1] ? home_url( '/' ) : home_url( '/' . $m[1] . '/' ) );
		},
		$html
	);
}

function teg_part( $name ) {
	$file = __DIR__ . '/parts/' . $name . '.html';
	return is_readable( $file ) ? teg_fill( file_get_contents( $file ) ) : '';
}

/** Renders a designed page: called by front-page.php, page-<slug>.php and 404.php. */
function teg_designed_page( $key ) {
	teg_current( $key );
	get_header();
	echo teg_part( $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- the theme's own built markup.
	get_footer();
}

function teg_header_html() {
	$meta = teg_page_meta();
	$on   = $meta ? $meta['on'] : '';
	$html = file_get_contents( __DIR__ . '/parts/header.html' );
	$html = preg_replace_callback(
		'/\{\{on:([a-z0-9-]+)\}\}/',
		function ( $m ) use ( $on ) {
			return $m[1] === $on ? ' class="on"' : '';
		},
		$html
	);
	return teg_fill( $html );
}

/** The footer, with the red "Let's talk" band except where a page leaves it out, and in the shop. */
function teg_footer_html() {
	$meta    = teg_page_meta();
	$contact = $meta ? $meta['contact'] : ! teg_is_shop_page();
	return teg_part( $contact ? 'footer' : 'footer-nocontact' );
}

/** The dark band at the top of shop and other WordPress pages, so the white header reads over it. */
function teg_band( $title = '', $eyebrow = '' ) {
	echo '<section class="phero ink wpb' . ( $title ? '' : ' slim' ) . '">';
	echo '<div class="fronds"><svg viewBox="0 490 595.5 352" preserveAspectRatio="xMidYMax slice"><use href="#fronds"/></svg></div>';
	echo '<div class="wrap in">';
	if ( $eyebrow ) {
		echo '<span class="eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	if ( $title ) {
		echo '<h1><span class="sans">' . esc_html( $title ) . '</span></h1>';
	}
	echo '</div></section>';
}

/* ------------------------------------------------------------------------------------------ */
/* The images                                                                                 */
/* ------------------------------------------------------------------------------------------ */

/* Where the photographs, covers and homepage film live. The standard build carries them inside the
   theme, in assets/. The slim build (node build-wp-theme.js --slim) leaves them out, for servers
   whose upload limit refuses the full zip, and the pages use the copy in wp-content/uploads/
   teg-media/assets/ instead: the folder the 1.3.0 theme copied them into, or the images pack
   (teg-media-images.zip) unpacked into wp-content/uploads/ with cPanel's File Manager. Either way
   the site loads its images only from itself. inc/assets.php, generated by the build, says which
   build this is and lists every image with its size. */
function teg_assets() {
	static $assets = null;
	if ( null === $assets ) {
		$assets = require __DIR__ . '/inc/assets.php';
	}
	return $assets;
}

/** The folder the images are in: array( path, url ). */
function teg_asset_root() {
	static $root = null;
	if ( null === $root ) {
		if ( teg_assets()['inside'] ) {
			$root = array(
				'path' => get_template_directory() . '/assets',
				'url'  => get_template_directory_uri() . '/assets',
			);
		} else {
			$up   = wp_upload_dir( null, false );
			$root = array(
				'path' => $up['basedir'] . '/teg-media/assets',
				'url'  => set_url_scheme( $up['baseurl'] . '/teg-media/assets' ),
			);
		}
	}
	return $root;
}

function teg_asset_base() {
	return teg_asset_root()['url'];
}

/** One image's address. */
function teg_asset_url( $file ) {
	return teg_asset_base() . '/' . ltrim( $file, '/' );
}

/** Whether this image is there (a sharper copy of a cover, for example). */
function teg_asset_exists( $file ) {
	return is_file( teg_asset_root()['path'] . '/' . ltrim( $file, '/' ) );
}

/** The images the pages use that are missing or not at full size. Only a slim build can have any. */
function teg_assets_missing() {
	$dir     = teg_asset_root()['path'];
	$missing = array();
	foreach ( teg_assets()['files'] as $file => $size ) {
		if ( ! is_file( $dir . '/' . $file ) || filesize( $dir . '/' . $file ) !== $size ) {
			$missing[] = $file;
		}
	}
	return $missing;
}

/* with a slim build, administrators hear about any image the pages need that is not on the site */
add_action(
	'admin_notices',
	function () {
		if ( teg_assets()['inside'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$missing = teg_assets_missing();
		if ( ! $missing ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>TEG Media theme:</strong> %1$d of the %2$d images the pages use are missing from <code>wp-content/uploads/teg-media/assets/</code>, so they show as broken. Unpack the images pack (teg-media-images.zip) into <code>wp-content/uploads/</code> with cPanel&rsquo;s File Manager, or install the standard theme zip, which carries its own images. Missing: %3$s%4$s</p></div>',
			count( $missing ),
			count( teg_assets()['files'] ),
			esc_html( implode( ', ', array_slice( $missing, 0, 5 ) ) ),
			count( $missing ) > 5 ? ' and more' : ''
		);
	}
);

/* ------------------------------------------------------------------------------------------ */
/* Setup, head and assets                                                                     */
/* ------------------------------------------------------------------------------------------ */

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
);

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$meta = teg_page_meta();
		return $meta ? $meta['title'] : $title;
	}
);

add_filter(
	'document_title_separator',
	function () {
		return '|';
	}
);

/* Designed pages carry only the mockup's body classes, so nothing else WordPress or a plugin adds
   can restyle them. Elsewhere the WordPress classes stay (WooCommerce needs them), except
   "archive", which the mockup uses for the Read page's archive section. */
add_filter(
	'body_class',
	function ( $classes ) {
		$meta = teg_page_meta();
		if ( $meta ) {
			$keep = array( 'a', $meta['body'] );
			if ( is_admin_bar_showing() ) {
				$keep[] = 'admin-bar';
			}
			return $keep;
		}
		$classes   = array_diff( $classes, array( 'archive' ) );
		$classes[] = 'a';
		$classes[] = teg_is_shop_page() ? 'pg-shop' : 'pg-wp';
		return array_values( $classes );
	},
	PHP_INT_MAX
);

add_action(
	'wp_head',
	function () {
		$uri = get_template_directory_uri();
		echo "<script>document.documentElement.className+=' js'</script>\n";
		echo '<meta name="theme-color" content="#0B0B0D">' . "\n";
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%1$s/favicon.svg" type="image/svg+xml"><link rel="icon" href="%1$s/favicon-32.png" sizes="32x32" type="image/png"><link rel="apple-touch-icon" href="%1$s/apple-touch-icon.png">' . "\n", esc_url( $uri ) );
		}
		$meta = teg_page_meta();
		if ( ! $meta || '' === $meta['description'] || defined( 'WPSEO_VERSION' ) ) {
			return;
		}
		$url = 'front' === teg_current() ? home_url( '/' ) : get_permalink( get_queried_object_id() );
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $meta['description'] ) );
		echo '<meta property="og:type" content="website"><meta property="og:site_name" content="TEG Media">' . "\n";
		printf( '<meta property="og:title" content="%s"><meta property="og:description" content="%s">' . "\n", esc_attr( $meta['title'] ), esc_attr( $meta['description'] ) );
		printf( '<meta property="og:image" content="%s"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n", esc_url( teg_asset_url( 'og.jpg' ) ) );
		if ( '404' !== teg_current() && $url ) {
			printf( '<meta property="og:url" content="%s">', esc_url( $url ) );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$uri = get_template_directory_uri();
		$v   = TEG_THEME_VERSION;
		wp_enqueue_style( 'teg-fonts', 'https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;700&family=Libre+Baskerville:ital,wght@0,400;1,400&family=Montserrat:wght@500;600;700;800&display=swap', array(), null );
		$after = array( 'teg-fonts' );
		foreach ( array( 'base', 'a', 'pages', 'polish', 'work', 'motion', 'wp' ) as $sheet ) {
			wp_enqueue_style( 'teg-' . $sheet, $uri . '/css/' . $sheet . '.css', $after, $v );
			$after = array( 'teg-' . $sheet );
		}
		$defer = array(
			'strategy'  => 'defer',
			'in_footer' => false,
		);
		wp_enqueue_script( 'teg-motion', $uri . '/js/motion.js', array(), $v, $defer );
		wp_enqueue_script( 'teg-yt-loop', $uri . '/js/yt-loop.js', array(), $v, $defer );
		wp_enqueue_script( 'teg-offers', $uri . '/js/offers.js', array(), $v, $defer );
		wp_enqueue_script( 'teg-cats', $uri . '/js/cats.js', array(), $v, $defer );
	}
);

add_filter(
	'wp_resource_hints',
	function ( $urls, $type ) {
		if ( 'preconnect' === $type ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	},
	10,
	2
);

/* On the designed pages, only the theme's own styles and scripts load (and the admin bar's, for
   anyone logged in). Plugins built for the old Elementor pages would otherwise restyle them. */
/* Google Analytics. The live site reports to this property (G-LKT9ZPPN4P), so the figures carry
   on whichever way the old site added the tag. Only the public site reports: a
   copy with Settings > Reading > "Discourage search engines" ticked (the staging site) stays out
   of the figures. If a plugin or snippet already adds Google's tag or Tag Manager, this one stands
   down so no visit is counted twice. Change or switch off in wp-config.php:
   define( 'TEG_GA_ID', 'G-XXXXXXXXXX' );  or  define( 'TEG_GA_ID', '' );  */
if ( ! defined( 'TEG_GA_ID' ) ) {
	define( 'TEG_GA_ID', 'G-LKT9ZPPN4P' );
}

add_action(
	'wp_head',
	function () {
		ob_start();
	},
	PHP_INT_MIN
);
add_action(
	'wp_head',
	function () {
		$head = ob_get_clean();
		echo $head; // phpcs:ignore WordPress.Security.EscapeOutput -- WordPress's own <head>, passed through.
		$id = (string) apply_filters( 'teg_ga_id', TEG_GA_ID );
		if ( ! preg_match( '/^G-[A-Z0-9]+$/', $id ) || '1' !== (string) get_option( 'blog_public' ) ) {
			return;
		}
		if ( preg_match( '#googletagmanager\.com/(gtag/js|gtm\.js)#', $head ) ) {
			return;
		}
		printf( '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>' . "\n" . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%1$s");</script>' . "\n", esc_attr( $id ) );
	},
	PHP_INT_MAX
);

/* Plugin files the designed pages keep: analytics and tag managers (so visits are still counted),
   cookie and consent tools, and WooCommerce's order attribution, which records where a visitor
   came from when they land on the homepage and later buy. Matched against each file's handle;
   change the list with the teg_keep_assets filter (a regular expression, or '' for none). */
function teg_keep_asset( $handle ) {
	$keep = apply_filters( 'teg_keep_assets', '/(gtag|gtm|tag-?manager|analytics|sitekit|jetpack-stats|wp-statistics|monsterinsights|pixel|clarity|hotjar|cookie|consent|cmplz|complianz|iubenda|sourcebuster|order-attribution)/i' );
	return $keep && preg_match( $keep, $handle );
}

function teg_trim_assets() {
	if ( ! teg_current() ) {
		return;
	}
	foreach ( wp_styles()->queue as $handle ) {
		if ( 0 !== strpos( $handle, 'teg-' ) && ! in_array( $handle, array( 'admin-bar', 'dashicons' ), true ) && ! teg_keep_asset( $handle ) ) {
			wp_dequeue_style( $handle );
		}
	}
	/* jQuery stays: plugins print small inline scripts on every page that call it (ShopLentor's
	   sales notification is one), and without it they throw "jQuery is not defined" */
	foreach ( wp_scripts()->queue as $handle ) {
		if ( 0 !== strpos( $handle, 'teg-' ) && ! in_array( $handle, array( 'admin-bar', 'jquery', 'jquery-core', 'jquery-migrate' ), true ) && ! teg_keep_asset( $handle ) ) {
			wp_dequeue_script( $handle );
		}
	}
	if ( ! wp_script_is( 'jquery', 'enqueued' ) && ! wp_script_is( 'jquery', 'done' ) ) {
		wp_enqueue_script( 'jquery' );
	}
}
add_action( 'wp_enqueue_scripts', 'teg_trim_assets', PHP_INT_MAX );
add_action( 'wp_print_styles', 'teg_trim_assets', PHP_INT_MAX );
add_action( 'wp_print_footer_scripts', 'teg_trim_assets', 1 );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/* Ultimate Addons for Elementor can replace any theme's header and footer with Elementor-built
   ones from the old site. This theme has its own, so those stay switched off everywhere. */
foreach ( array( 'hfe_header_enabled', 'hfe_footer_enabled', 'hfe_before_footer_enabled', 'enable_hfe_render_header', 'enable_hfe_render_footer', 'enable_hfe_render_before_footer' ) as $teg_hfe_filter ) {
	add_filter( $teg_hfe_filter, '__return_false', PHP_INT_MAX );
}

/* ------------------------------------------------------------------------------------------ */
/* Product pages                                                                              */
/* ------------------------------------------------------------------------------------------ */

/* Product pages use this theme's layout. ShopLentor, kept from the old site, swaps in its own
   Elementor template at priority 999; this runs after it, for product pages only. The cart and
   checkout are left exactly as they are. */
add_filter(
	'template_include',
	function ( $template ) {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$ours = locate_template( 'woocommerce.php' );
			if ( $ours ) {
				return $ours;
			}
		}
		return $template;
	},
	1000
);

/**
 * A product page: the dark band with the product and its price, then the order form (with any
 * fields Advanced Product Fields adds) in the site's own form styling. The TEG Card gets the card
 * itself, its own headline and a short summary of what members save beside the form.
 */
function teg_product_page( $product ) {
	if ( ! $product ) {
		return;
	}
	$is_card = 'teg-card-application' === $product->get_slug();
	$uri     = get_template_directory_uri();

	echo '<section class="memhero prodhero' . ( $is_card ? ' is-card' : '' ) . '">';
	echo '<div class="fronds"><svg viewBox="0 490 595.5 352" preserveAspectRatio="xMidYMax slice"><use href="#fronds"/></svg></div>';
	echo '<div class="wrap memgrid"><div class="memtxt">';
	echo '<span class="eyebrow">' . ( $is_card ? 'Membership' : 'Shop' ) . '</span>';
	if ( $is_card ) {
		echo '<h1><span class="sans">The TEG Card.</span><span class="serif">Apply or renew.</span></h1>';
	} else {
		echo '<h1><span class="sans">' . esc_html( $product->get_name() ) . '</span></h1>';
	}
	echo '<div class="prodprice">' . wp_kses_post( $product->get_price_html() ) . ( $is_card ? ' <small>a year</small>' : '' ) . '</div>';
	$intro = $is_card
		? 'One card covers you and your partner. Fill in your details below and choose how to collect it.'
		: wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() );
	if ( '' !== trim( $intro ) ) {
		echo '<p>' . esc_html( wp_trim_words( $intro, 55 ) ) . '</p>';
	}
	echo '<div class="cta"><a class="btn solid" href="#order">' . ( $is_card ? 'Apply now' : 'Order now' ) . ' <span class="arr">&darr;</span></a></div>';
	echo '</div>';
	if ( $is_card ) {
		echo '<div class="memcard"><span class="tilt"><img src="' . esc_url( $uri . '/assets/teg-card.png' ) . '" alt="The TEG Card" width="900" height="568"><i class="shine"></i></span></div>';
	} else {
		echo '<div class="prodimg">' . wp_kses_post( $product->get_image( 'large' ) ) . '</div>';
	}
	echo '</div></section>';

	echo '<section class="prodbuy" id="order"><div class="wrap prodbuyin' . ( $is_card ? '' : ' solo' ) . '">';
	echo '<div class="prodform"><h2>' . ( $is_card ? 'Your details' : 'Your order' ) . '</h2>';
	do_action( 'woocommerce_before_single_product' );
	woocommerce_template_single_add_to_cart();
	echo '</div>';
	if ( $is_card ) {
		echo '<aside class="prodside"><h3>What members save</h3><ul>'
			. '<li><b>RM15</b><span>off each at every Mingle, and member prices at Mingle Plus</span></li>'
			. '<li><b>RM25</b><span>off each, at every Wine Dinner</span></li>'
			. '<li><b>Member prices</b><span>at WhiskyPLUS</span></li>'
			. '<li class="soon"><b>Coming soon</b><span>with the new TEG Card: offers at partner restaurants and bars</span></li>'
			. '</ul><p>Collect your card at a Mingle or Wine Dinner, at our office in Bangsar, or have it posted for RM5.</p>'
			. '<p class="links"><a href="' . esc_url( home_url( '/membership/' ) ) . '">About membership</a><a href="' . esc_url( home_url( '/contact/?about=subscriptions#message' ) ) . '">Ask us a question</a></p></aside>';
	}
	echo '</div></section>';

	if ( function_exists( 'WC' ) && isset( WC()->structured_data ) ) {
		WC()->structured_data->generate_product_data( $product );
	}
}

/* ------------------------------------------------------------------------------------------ */
/* The contact form                                                                           */
/* ------------------------------------------------------------------------------------------ */

/* Every enquiry is saved in WordPress first (Enquiries, in the admin menu), then emailed to the
   site's admin address, or another set with the teg_contact_to filter (info@tegmedia.my at launch).
   So a lead is never lost if the email fails. Spam protection, in layers:
   - a hidden field people never see and bots fill in;
   - a timestamp the page sets when it opens: a form sent within 3 seconds is a bot;
   - at most 5 messages an hour from one address;
   - Cloudflare Turnstile, a free CAPTCHA that is usually invisible, switched on by adding
     TEG_TURNSTILE_SITE_KEY and TEG_TURNSTILE_SECRET to wp-config.php (keys from dash.cloudflare.com). */
add_action( 'admin_post_nopriv_teg_contact', 'teg_contact_send' );
add_action( 'admin_post_teg_contact', 'teg_contact_send' );

add_action(
	'init',
	function () {
		register_post_type(
			'teg_enquiry',
			array(
				'labels'          => array(
					'name'          => 'Enquiries',
					'singular_name' => 'Enquiry',
					'menu_name'     => 'Enquiries',
					'all_items'     => 'All enquiries',
					'edit_item'     => 'Enquiry',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

function teg_turnstile_on() {
	return defined( 'TEG_TURNSTILE_SITE_KEY' ) && defined( 'TEG_TURNSTILE_SECRET' ) && TEG_TURNSTILE_SITE_KEY && TEG_TURNSTILE_SECRET;
}

/** What fills {{captcha}} on the contact form: the Turnstile widget once its keys are set. */
function teg_captcha_html() {
	if ( ! teg_turnstile_on() ) {
		return '';
	}
	return '<div class="cf-turnstile" data-sitekey="' . esc_attr( TEG_TURNSTILE_SITE_KEY ) . '" data-theme="light"></div>'
		. '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function teg_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

function teg_contact_send() {
	$back  = home_url( '/contact/' );
	$field = function ( $key, $multiline = false ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a public form on cached pages; see the checks below.
		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	};
	$fail = function () use ( $back ) {
		wp_safe_redirect( add_query_arg( 'sent', '0', $back ) . '#message' );
		exit;
	};

	/* the hidden field: bots are told it worked and nothing happens */
	if ( '' !== $field( 'website' ) ) {
		wp_safe_redirect( add_query_arg( 'sent', '1', $back ) . '#message' );
		exit;
	}
	/* sent within 3 seconds of the page opening, or with no timestamp at all */
	$opened = (int) ( (int) $field( 't' ) / 1000 );
	if ( $opened <= 0 || time() - $opened < 3 ) {
		$fail();
	}
	/* at most 5 an hour from one address */
	$key   = 'teg_cf_' . md5( teg_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		$fail();
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	/* Turnstile, when switched on */
	if ( teg_turnstile_on() ) {
		$check = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => TEG_TURNSTILE_SECRET,
					'response' => $field( 'cf-turnstile-response' ),
					'remoteip' => teg_client_ip(),
				),
			)
		);
		$ok = ! is_wp_error( $check ) && ! empty( json_decode( wp_remote_retrieve_body( $check ), true )['success'] );
		if ( ! $ok ) {
			$fail();
		}
	}

	$topics  = array(
		'advertising'   => 'Advertising',
		'event'         => 'An event for my brand',
		'mm2h'          => 'MM2H',
		'subscriptions' => 'Subscriptions',
		'other'         => 'Something else',
	);
	$about   = $field( 'about' );
	$topic   = isset( $topics[ $about ] ) ? $topics[ $about ] : 'Something else';
	$name    = trim( str_replace( array( '<', '>', '"' ), '', $field( 'name' ) ) );
	$email   = sanitize_email( $field( 'email' ) );
	$message = $field( 'message', true );
	$sent    = false;

	if ( '' !== $name && is_email( $email ) && '' !== $message ) {
		$body = implode(
			"\n",
			array(
				'Name: ' . $name,
				'Company: ' . $field( 'company' ),
				'Email: ' . $email,
				'Phone: ' . $field( 'phone' ),
				'Interested in: ' . $topic,
				'',
				$message,
				'',
				'Sent from the contact form at ' . $back,
			)
		);
		/* saved first, so the enquiry is kept even if the email does not go */
		$saved = wp_insert_post(
			array(
				'post_type'    => 'teg_enquiry',
				'post_status'  => 'private',
				'post_title'   => $topic . ': ' . $name . ' (' . $email . ')',
				'post_content' => $body,
			)
		);
		$sent  = wp_mail(
			apply_filters( 'teg_contact_to', get_option( 'admin_email' ) ),
			'Website enquiry: ' . $topic . ' (' . $name . ')',
			$body,
			array( 'Reply-To: ' . $name . ' <' . $email . '>' )
		);
		if ( $saved && ! is_wp_error( $saved ) ) {
			update_post_meta( $saved, '_teg_emailed', $sent ? 'yes' : 'no' );
			/* the visitor is told it went through: the enquiry is on file even if the email failed */
			$sent = true;
		}
	}

	wp_safe_redirect( add_query_arg( 'sent', $sent ? '1' : '0', $back ) . '#message' );
	exit;
}

/* ------------------------------------------------------------------------------------------ */
/* The old pages, forwarded to their place on the new site                                    */
/* ------------------------------------------------------------------------------------------ */

/* Permanent (301) on the public site, so search engines move the old pages' standing to the new
   ones. Temporary (302) while "Discourage search engines" is ticked (the staging site), so no
   browser remembers a forward that may still change. Override with the teg_redirect_status filter.
   The shop, checkout and subscription pages are left alone. */
add_filter(
	'allowed_redirect_hosts',
	function ( $hosts ) {
		$hosts[] = 'mm2h.com';
		return $hosts;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! is_page() || is_front_page() ) {
			return;
		}
		$map  = array(
			'whiskyplus'              => 'events/#whiskyplus',
			'the-mingle'              => 'events/#monthly',
			'the-wine-dinner'         => 'events/#monthly',
			'expatgo'                 => 'portfolio/#expatgo',
			'the-expat-magazine'      => 'the-expat/',
			'read-online'             => 'read/',
			'the-expat-welcome-guide' => 'portfolio/#welcome',
			'expat-education-guide'   => 'portfolio/#education',
			'teg-card'                => 'membership/',
			'mm2h'                    => 'https://mm2h.com/',
			'mm2h-visa-agency'        => 'https://mm2h.com/',
		);
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( ! isset( $map[ $slug ] ) ) {
			return;
		}
		$to = 0 === strpos( $map[ $slug ], 'https://' ) ? $map[ $slug ] : home_url( '/' . $map[ $slug ] );
		$status = '1' === (string) get_option( 'blog_public' ) ? 301 : 302;
		wp_safe_redirect( $to, apply_filters( 'teg_redirect_status', $status ), 'TEG Media theme' );
		exit;
	}
);

/* ------------------------------------------------------------------------------------------ */
/* Enquiries whose email did not go out                                                       */
/* ------------------------------------------------------------------------------------------ */

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$failed = get_posts(
			array(
				'post_type'      => 'teg_enquiry',
				'post_status'    => 'private',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => '_teg_emailed', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'no', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( $failed ) {
			printf(
				'<div class="notice notice-warning"><p><strong>TEG Media:</strong> %d website %s could not be emailed and %s waiting in <a href="%s">Enquiries</a>. Check the site\'s email settings.</p></div>',
				count( $failed ),
				1 === count( $failed ) ? 'enquiry' : 'enquiries',
				1 === count( $failed ) ? 'is' : 'are',
				esc_url( admin_url( 'edit.php?post_type=teg_enquiry' ) )
			);
		}
	}
);
add_filter(
	'manage_teg_enquiry_posts_columns',
	function ( $cols ) {
		$cols['teg_emailed'] = 'Emailed';
		return $cols;
	}
);
add_action(
	'manage_teg_enquiry_posts_custom_column',
	function ( $col, $id ) {
		if ( 'teg_emailed' === $col ) {
			echo 'no' === get_post_meta( $id, '_teg_emailed', true ) ? '<strong style="color:#b32d2e">No</strong>' : 'Yes';
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------------------------------ */
/* The WordPress pages the designed pages need                                                */
/* ------------------------------------------------------------------------------------------ */

/* WordPress shows a designed page through page-<slug>.php, so each one needs a published page at
   its address: About at /about/, Portfolio at /portfolio/ and so on. Their content stays empty;
   the theme supplies it. The homepage, the privacy notice and the 404 page need none.
   Administrators see a notice listing any that are missing, with a button that creates them. */
function teg_page_names() {
	return array(
		'about'      => 'About',
		'portfolio'  => 'Portfolio',
		'membership' => 'Membership',
		'advertise'  => 'Advertise',
		'contact'    => 'Contact',
		'events'     => 'Events',
		'clients'    => 'Past clients',
		'read'       => 'Read',
		'the-expat'  => 'The Expat',
	);
}

/** Each needed page that is not published: slug => 'missing', or the status of the page there. */
function teg_pages_needed() {
	$out = array();
	foreach ( array_keys( teg_page_names() ) as $slug ) {
		$found = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => 'page',
				'post_parent'    => 0,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => 1,
			)
		);
		if ( ! $found ) {
			$out[ $slug ] = 'missing';
		} elseif ( 'publish' !== $found[0]->post_status ) {
			$out[ $slug ] = $found[0]->post_status;
		}
	}
	return $out;
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		$needed = teg_pages_needed();
		if ( isset( $_GET['teg_pages_made'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- only shows a message.
			printf( '<div class="notice notice-success is-dismissible"><p><strong>TEG Media:</strong> created %d %s.</p></div>', (int) $_GET['teg_pages_made'], 1 === (int) $_GET['teg_pages_made'] ? 'page' : 'pages' ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( ! $needed ) {
			return;
		}
		$names   = teg_page_names();
		$missing = array();
		$other   = array();
		foreach ( $needed as $slug => $state ) {
			if ( 'missing' === $state ) {
				$missing[] = esc_html( $names[ $slug ] ) . ' (/' . $slug . '/)';
			} else {
				$other[] = esc_html( $names[ $slug ] ) . ' (/' . $slug . '/) is ' . ( 'trash' === $state ? 'in the bin' : esc_html( $state ) );
			}
		}
		echo '<div class="notice notice-warning"><p><strong>TEG Media theme:</strong> these designed pages show the site&rsquo;s 404 page until WordPress has a published page at their address.</p>';
		if ( $missing ) {
			printf(
				'<p>No page yet: %s.</p><form method="post" action="%s"><input type="hidden" name="action" value="teg_create_pages">%s<p><button class="button button-primary">Create the missing pages</button> <span class="description">Empty pages with the right address; the theme fills them.</span></p></form>',
				implode( ', ', $missing ), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( 'teg_create_pages', '_wpnonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
		if ( $other ) {
			echo '<p>Not published: ' . implode( '; ', $other ) . '. Publish or restore it under Pages.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		}
		echo '</div>';
	}
);

add_action(
	'admin_post_teg_create_pages',
	function () {
		check_admin_referer( 'teg_create_pages' );
		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_die( 'Only an editor or administrator can create pages.', 403 );
		}
		$names = teg_page_names();
		$made  = 0;
		foreach ( teg_pages_needed() as $slug => $state ) {
			if ( 'missing' !== $state ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $names[ $slug ],
					'post_content' => '',
				),
				true
			);
			if ( ! is_wp_error( $id ) ) {
				++$made;
			}
		}
		wp_safe_redirect( add_query_arg( 'teg_pages_made', $made, wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=page' ) ) );
		exit;
	}
);

/* ------------------------------------------------------------------------------------------ */
/* Browser security headers                                                                   */
/* ------------------------------------------------------------------------------------------ */

/* Sent with every public page. The content policy is the safe part of CSP: no framing by other
   sites, no plugins, no changing the base address, and everything upgraded to https. A full
   script allow-list would break the payment page and the plugins, so it is left to the server.
   Turn any of this off with the teg_security_headers filter. */
add_action(
	'send_headers',
	function () {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		$h = array(
			'X-Content-Type-Options'  => 'nosniff',
			'X-Frame-Options'         => 'SAMEORIGIN',
			'Referrer-Policy'         => 'strict-origin-when-cross-origin',
			'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
			'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests",
		);
		if ( is_ssl() ) {
			$h['Strict-Transport-Security'] = 'max-age=31536000';
		}
		foreach ( apply_filters( 'teg_security_headers', $h ) as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}
);

/* ------------------------------------------------------------------------------------------ */
/* The privacy notice                                                                         */
/* ------------------------------------------------------------------------------------------ */

/* /privacy/ works without anyone creating a page: if no page has that address, the theme
   serves its own. WordPress's Privacy Policy setting points at it as well. */
add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( 'privacy' === $path ) {
			status_header( 200 );
			global $wp_query;
			$wp_query->is_404 = false;
			teg_designed_page( 'privacy' );
			exit;
		}
	},
	1
);
add_filter(
	'privacy_policy_url',
	function ( $url ) {
		return $url ? $url : home_url( '/privacy/' );
	}
);

/* the TEG Card form collects personal details, so it says where they go */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		if ( teg_current() === null && function_exists( 'is_product' ) && is_product() ) {
			printf( '<p class="pnote">We use your details only for your order and membership. See our <a href="%s">privacy notice</a>.</p>', esc_url( home_url( '/privacy/' ) ) );
		}
	}
);
