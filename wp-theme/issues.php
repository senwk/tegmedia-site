<?php
/**
 * The issues on the site, kept up to date from Calameo.
 *
 * Every cover, issue name and "Read" link on the designed pages is a {{cal:...}} token, filled here
 * from the list of issues: the ones known when the theme was built (inc/calameo.php, covers in the
 * copied images), plus any WordPress has since found on Calameo. Twice a day it reads TEG's newest
 * 24 publications from the list Calameo's own account page uses (public, no key), recognises a new
 * Expat issue, Welcome Guide or Education Guide by its name, saves its cover into
 * wp-content/uploads/teg-media/calameo/, and from then on every page shows it.
 *
 * Calameo does not document that list. If it changes or fails, nothing breaks: the site keeps the
 * issues it has, and Appearance > TEG Media issues says what went wrong.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'TEG_CALAMEO_FEED' ) ) {
	define( 'TEG_CALAMEO_FEED', 'https://d.calameo.com/pinwheel/public/account/book/get?step=24&account=5063995' );
}

/* ------------------------------------------------------------------------------------------ */
/* The list of issues                                                                         */
/* ------------------------------------------------------------------------------------------ */

const TEG_CAL_KINDS = array( 'expat', 'welcome', 'education' );

function teg_cal_seed() {
	static $seed = null;
	if ( null === $seed ) {
		$seed = require __DIR__ . '/inc/calameo.php';
	}
	return $seed;
}

/** Issues found on Calameo since the theme was built: kind => list, each with its saved cover's 'file'. */
function teg_cal_found() {
	$found = get_option( 'teg_cal_found', array() );
	return is_array( $found ) ? $found : array();
}

/** Every issue, newest first, by kind. A slug the build already knew keeps the build's details. */
function teg_cal_issues() {
	$seed  = teg_cal_seed();
	$found = teg_cal_found();
	$all   = array();
	foreach ( TEG_CAL_KINDS as $kind ) {
		$by = array();
		foreach ( isset( $seed[ $kind ] ) ? $seed[ $kind ] : array() as $c ) {
			$by[ $c['slug'] ] = $c;
		}
		foreach ( isset( $found[ $kind ] ) ? $found[ $kind ] : array() as $c ) {
			if ( ! isset( $by[ $c['slug'] ] ) ) {
				$by[ $c['slug'] ] = $c;
			}
		}
		krsort( $by, SORT_STRING );
		$all[ $kind ] = array_values( $by );
	}
	return $all;
}

function teg_cal_dir() {
	$up = wp_upload_dir( null, false );
	return array(
		'path' => $up['basedir'] . '/teg-media/calameo',
		'url'  => set_url_scheme( $up['baseurl'] . '/teg-media/calameo' ),
	);
}

/** An issue's cover: saved from Calameo if it was found later, else among the copied images. */
function teg_cal_src( $kind, $c ) {
	if ( ! empty( $c['file'] ) ) {
		return teg_cal_dir()['url'] . '/' . $c['file'];
	}
	return teg_asset_url( ( 'expat' === $kind ? 'expat/' : 'guides/' ) . $c['slug'] . '.jpg' );
}

/** The big cover of the newest issue: a sharper print-file copy (<slug>-hi.jpg) when the build had one. */
function teg_cal_latest_img( $issues ) {
	$c = $issues['expat'][0];
	if ( empty( $c['file'] ) && isset( teg_assets()['files'][ 'expat/' . $c['slug'] . '-hi.jpg' ] ) ) {
		return teg_asset_url( 'expat/' . $c['slug'] . '-hi.jpg' );
	}
	return teg_cal_src( 'expat', $c );
}

/** A guide's newest cover. The build's newest edition has a sharper copy, guides/ewg.jpg or edu.jpg. */
function teg_cal_guide_img( $issues, $kind ) {
	$c    = $issues[ $kind ][0];
	$seed = teg_cal_seed();
	if ( empty( $c['file'] ) && ! empty( $seed[ $kind ] ) && $seed[ $kind ][0]['slug'] === $c['slug'] ) {
		return teg_asset_url( 'guides/' . ( 'welcome' === $kind ? 'ewg' : 'edu' ) . '.jpg' );
	}
	return teg_cal_src( $kind, $c );
}

/* ------------------------------------------------------------------------------------------ */
/* Filling the pages (the same markup the mockup's build-site.js writes)                      */
/* ------------------------------------------------------------------------------------------ */

function teg_cal_issue_link( $c ) {
	return sprintf(
		'<a class="iss" href="%s" target="_blank" rel="noopener"><img src="%s" alt="The Expat, %s" loading="lazy" width="%d" height="%d"><span>%s</span></a>',
		esc_url( $c['url'] ),
		esc_url( teg_cal_src( 'expat', $c ) ),
		esc_attr( $c['label'] ),
		(int) $c['w'],
		(int) $c['h'],
		esc_html( $c['label'] )
	);
}

function teg_cal_edition_link( $kind, $c ) {
	return sprintf(
		'<a class="iss" href="%s" target="_blank" rel="noopener"><img src="%s" alt="%s %s" loading="lazy" width="%d" height="%d"><span>%s</span></a>',
		esc_url( $c['url'] ),
		esc_url( teg_cal_src( $kind, $c ) ),
		'welcome' === $kind ? 'The Expat Welcome Guide' : 'The Expat Education Guide',
		esc_attr( $c['label'] ),
		(int) $c['w'],
		(int) $c['h'],
		esc_html( $c['label'] )
	);
}

function teg_cal_token( $issues, $name, $a, $b ) {
	$ex = $issues['expat'];
	switch ( $name ) {
		case 'latest-url':
			return esc_url( $ex[0]['url'] );
		case 'latest-label':
			return esc_html( $ex[0]['label'] );
		case 'latest-img':
			return esc_url( teg_cal_latest_img( $issues ) );
		case 'latest-thumb':
			return esc_url( teg_cal_src( 'expat', $ex[0] ) );
		case 'issue-count':
			return (string) count( $ex );
		case 'ewg-latest-url':
		case 'edu-latest-url':
			return esc_url( $issues[ 'ewg' === substr( $name, 0, 3 ) ? 'welcome' : 'education' ][0]['url'] );
		case 'ewg-latest-label':
		case 'edu-latest-label':
			return esc_html( $issues[ 'ewg' === substr( $name, 0, 3 ) ? 'welcome' : 'education' ][0]['label'] );
		case 'ewg-latest-img':
		case 'edu-latest-img':
			return esc_url( teg_cal_guide_img( $issues, 'ewg' === substr( $name, 0, 3 ) ? 'welcome' : 'education' ) );
		case 'ewg-editions':
		case 'edu-editions':
			$kind = 'ewg' === substr( $name, 0, 3 ) ? 'welcome' : 'education';
			return implode(
				"\n      ",
				array_map(
					function ( $c ) use ( $kind ) {
						return teg_cal_edition_link( $kind, $c );
					},
					array_slice( $issues[ $kind ], 0, 6 )
				)
			);
		case 'strip':
			return implode( "\n      ", array_map( 'teg_cal_issue_link', array_slice( $ex, (int) $a, (int) $b ) ) );
		case 'archive':
			$years = array();
			foreach ( $ex as $c ) {
				$years[ substr( $c['slug'], 0, 4 ) ][] = teg_cal_issue_link( $c );
			}
			$out = array();
			foreach ( $years as $y => $links ) {
				$out[] = '<div class="yr"><h3 class="serif">' . esc_html( $y ) . "</h3><div class=\"agrid\">\n      " . implode( "\n      ", $links ) . "\n    </div></div>";
			}
			return implode( "\n    ", $out );
		case 'fan':
			$out = array();
			foreach ( array_slice( $ex, 0, (int) $a ) as $i => $c ) {
				$out[] = sprintf(
					'<a class="fc f%d" href="%s" target="_blank" rel="noopener"><img src="%s" alt="The Expat, %s"%s></a>',
					$i + 1,
					esc_url( $c['url'] ),
					esc_url( $i ? teg_cal_src( 'expat', $c ) : teg_cal_latest_img( $issues ) ),
					esc_attr( $c['label'] ),
					$i ? ' loading="lazy"' : ''
				);
			}
			return implode( "\n        ", $out );
		case 'flow':
			/* Print's Cover Flow: ten covers, titles alternating, the newest Expat in front (see js/cats.js) */
			$w    = $issues['welcome'];
			$e    = $issues['education'];
			$pick = function ( $list, $i ) {
				return isset( $list[ $i ] ) ? $list[ $i ] : end( $list );
			};
			$list = array(
				teg_cal_latest_img( $issues ),
				teg_cal_src( 'welcome', $pick( $w, 0 ) ),
				teg_cal_src( 'education', $pick( $e, 0 ) ),
				teg_asset_url( 'circular-cover.jpg' ),
				teg_cal_src( 'expat', $pick( $ex, 1 ) ),
				teg_cal_src( 'welcome', $pick( $w, 1 ) ),
				teg_cal_src( 'education', $pick( $e, 1 ) ),
				teg_cal_src( 'expat', $pick( $ex, 2 ) ),
				teg_cal_src( 'welcome', $pick( $w, 2 ) ),
				teg_cal_src( 'education', $pick( $e, 2 ) ),
			);
			$n   = count( $list );
			$out = '';
			foreach ( $list as $i => $src ) {
				$out .= sprintf( '<img src="%s" alt="" data-pos="%d">', esc_url( $src ), $i <= 4 ? $i : $i - $n );
			}
			return $out;
	}
	return '';
}

/** Fills the {{cal:...}} tokens in a page's markup. */
function teg_cal_fill( $html ) {
	if ( false === strpos( $html, '{{cal:' ) ) {
		return $html;
	}
	$issues = teg_cal_issues();
	return preg_replace_callback(
		'/\{\{cal:([a-z-]+)(?::(\d+))?(?::(\d+))?\}\}/',
		function ( $m ) use ( $issues ) {
			return teg_cal_token( $issues, $m[1], isset( $m[2] ) ? $m[2] : '', isset( $m[3] ) ? $m[3] : '' );
		},
		$html
	);
}

/* ------------------------------------------------------------------------------------------ */
/* Checking Calameo                                                                           */
/* ------------------------------------------------------------------------------------------ */

/**
 * What a publication is, from its name: array( kind, slug, label ), or null for anything else
 * (EatDrink, Senses of Malaysia, The Circular and so on). Calameo names are not uniform:
 * "The Expat October 2026", "Expat July 2025", "Expat Education Guide 2026/2027",
 * "The Expat Welcome Guide 2026".
 */
function teg_cal_classify( $name ) {
	$name = trim( preg_replace( '/\s+/', ' ', (string) $name ) );
	$year = (int) gmdate( 'Y' );
	if ( preg_match( '#education guide (\d{4}) ?[/-] ?(\d{2}|\d{4})\b#i', $name, $m ) ) {
		$y1 = (int) $m[1];
		$y2 = 2 === strlen( $m[2] ) ? (int) ( substr( $m[1], 0, 2 ) . $m[2] ) : (int) $m[2];
		if ( $y2 !== $y1 + 1 || $y1 < 2017 || $y1 > $year + 1 ) {
			return null;
		}
		return array( 'education', 'edu-' . $y1 . '-' . $y2, $y1 . '/' . substr( (string) $y2, 2 ) );
	}
	if ( preg_match( '/welcome guide (\d{4})\b/i', $name, $m ) ) {
		$y = (int) $m[1];
		return ( $y < 2017 || $y > $year + 1 ) ? null : array( 'welcome', 'ewg-' . $y, (string) $y );
	}
	$months = array( 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' );
	if ( false === stripos( $name, 'guide' ) && preg_match( '/\bexpat\b.{0,24}?\b(' . implode( '|', $months ) . ') (\d{4})\b/i', $name, $m ) ) {
		$y = (int) $m[2];
		$i = array_search( strtolower( $m[1] ), $months, true );
		if ( $y < 2017 || $y > $year + 1 ) {
			return null;
		}
		return array( 'expat', sprintf( '%04d-%02d', $y, $i + 1 ), ucfirst( $months[ $i ] ) . ' ' . $y );
	}
	return null;
}

function teg_cal_get( $url ) {
	$r = wp_remote_get(
		$url,
		array(
			'timeout'    => 25,
			'user-agent' => 'Mozilla/5.0 (compatible; TEG Media website; +' . home_url( '/' ) . ')',
		)
	);
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'teg_cal', $r->get_error_message() );
	}
	if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'teg_cal', 'Calameo answered ' . wp_remote_retrieve_response_code( $r ) . ' for ' . $url );
	}
	return wp_remote_retrieve_body( $r );
}

/**
 * Cuts the cover out of Calameo's sharing card for a publication (1200x628, the cover centred on a
 * flat grey ground with a shadow down and to the right): about 400px wide, sharper than the 300px
 * thumbnail. Returns array( jpeg bytes, width, height ) or null. The same method made the covers
 * already on the site (scratchpad cal-crop.js).
 */
function teg_cal_crop_card( $bytes ) {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return null;
	}
	$im = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a bad image is handled below.
	if ( ! $im ) {
		return null;
	}
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$px = function ( $x, $y ) use ( $im ) {
		$c = imagecolorat( $im, $x, $y );
		return array( ( $c >> 16 ) & 255, ( $c >> 8 ) & 255, $c & 255 );
	};
	$g   = $px( 0, 0 );
	$off = function ( $x, $y ) use ( $px, $g ) {
		$p = $px( $x, $y );
		return abs( $p[0] - $g[0] ) + abs( $p[1] - $g[1] ) + abs( $p[2] - $g[2] ) > 30;
	};
	$col = function ( $cx ) use ( $off, $h ) {
		$n = 0;
		for ( $y = 0; $y < $h; $y += 2 ) {
			$n += $off( $cx, $y ) ? 1 : 0;
		}
		return $n > $h * 0.25;
	};
	$row = function ( $cy ) use ( $off, $w ) {
		$n = 0;
		for ( $x = 0; $x < $w; $x += 2 ) {
			$n += $off( $x, $cy ) ? 1 : 0;
		}
		return $n > $w * 0.12;
	};
	$l = 0;
	$r = $w - 1;
	$t = 0;
	$b = $h - 1;
	while ( $l < $w && ! $col( $l ) ) {
		++$l;
	}
	while ( $r > 0 && ! $col( $r ) ) {
		--$r;
	}
	while ( $t < $h && ! $row( $t ) ) {
		++$t;
	}
	while ( $b > 0 && ! $row( $b ) ) {
		--$b;
	}
	if ( 1200 === $w ) {
		$l += 2;
		$t += 2;
		/* the shadow: columns and rows whose pixels are dark and colourless; step in past them */
		$dark  = function ( $x, $y ) use ( $px ) {
			$p = $px( $x, $y );
			return max( $p ) < 70 && max( $p ) - min( $p ) < 18;
		};
		$shcol = function ( $cx ) use ( $dark, $t, $b ) {
			$s = 0;
			$n = 0;
			for ( $y = $t; $y < $b; $y += 3 ) {
				$s += $dark( $cx, $y ) ? 1 : 0;
				++$n;
			}
			return $n && $s / $n > 0.6;
		};
		$shrow = function ( $cy ) use ( $dark, $l, $r ) {
			$s = 0;
			$n = 0;
			for ( $x = $l; $x < $r; $x += 3 ) {
				$s += $dark( $x, $cy ) ? 1 : 0;
				++$n;
			}
			return $n && $s / $n > 0.6;
		};
		for ( $k = 0; $k < 30 && $shcol( $r ); $k++ ) {
			--$r;
		}
		for ( $k = 0; $k < 30 && $shrow( $b ); $k++ ) {
			--$b;
		}
		$r -= 2;
		$b -= 2;
	}
	$cw = $r - $l;
	$ch = $b - $t;
	/* a magazine cover: portrait, about 1.4 times as tall as wide; anything else means the card changed */
	if ( $cw < 200 || $ch < 280 || $ch / $cw < 1.25 || $ch / $cw > 1.6 ) {
		return null;
	}
	$out = imagecreatetruecolor( $cw, $ch );
	imagecopy( $out, $im, 0, 0, $l, $t, $cw, $ch );
	ob_start();
	imagejpeg( $out, null, 90 );
	return array( ob_get_clean(), $cw, $ch );
}

/** Saves a publication's cover; returns array( file relative to the calameo folder, width, height ) or a WP_Error. */
function teg_cal_save_cover( $kind, $slug, $code, $thumb ) {
	$img = null;
	if ( 'expat' === $kind ) {
		$card = teg_cal_get( 'https://www.calameo.com/books/social/cover/' . $code );
		$img  = is_wp_error( $card ) ? null : teg_cal_crop_card( $card );
	}
	if ( ! $img && $thumb ) {
		$body = teg_cal_get( ( 0 === strpos( $thumb, '//' ) ? 'https:' : '' ) . $thumb );
		$size = is_wp_error( $body ) ? false : @getimagesizefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size && IMAGETYPE_JPEG === $size[2] ) {
			$img = array( $body, $size[0], $size[1] );
		}
	}
	if ( ! $img ) {
		return new WP_Error( 'teg_cal', 'no usable cover for ' . $slug );
	}
	$file = ( 'expat' === $kind ? 'expat/' : 'guides/' ) . $slug . '.jpg';
	$path = teg_cal_dir()['path'] . '/' . $file;
	wp_mkdir_p( dirname( $path ) );
	if ( false === file_put_contents( $path, $img[0] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'teg_cal', 'could not save ' . $file );
	}
	return array( $file . '?v=' . substr( md5( $img[0] ), 0, 8 ), $img[1], $img[2] );
}

/** Reads Calameo's newest publications and adds any new issue. Runs twice a day, and from the admin screen. */
function teg_cal_check() {
	$status = array(
		'time'   => time(),
		'ok'     => false,
		'seen'   => 0,
		'added'  => array(),
		'errors' => array(),
	);
	$body = teg_cal_get( TEG_CALAMEO_FEED );
	$data = is_wp_error( $body ) ? null : json_decode( $body, true );
	$list = isset( $data['content']['list'] ) && is_array( $data['content']['list'] ) ? $data['content']['list'] : null;
	if ( null === $list ) {
		$status['errors'][] = is_wp_error( $body ) ? $body->get_error_message() : 'Calameo replied, but not with the list of publications it used to send.';
		update_option( 'teg_cal_status', $status, false );
		return $status;
	}
	$status['ok']   = true;
	$status['seen'] = count( $list );
	$issues         = teg_cal_issues();
	$have           = array();
	foreach ( $issues as $kind => $l ) {
		$have[ $kind ] = array_flip( wp_list_pluck( $l, 'slug' ) );
	}
	$ignored = (array) get_option( 'teg_cal_ignored', array() );
	$found   = teg_cal_found();
	foreach ( $list as $book ) {
		$what = teg_cal_classify( isset( $book['name'] ) ? $book['name'] : '' );
		$code = isset( $book['code'] ) ? (string) $book['code'] : '';
		if ( ! $what || ! preg_match( '/^[0-9a-f]{12,40}$/i', $code ) ) {
			continue;
		}
		list( $kind, $slug, $label ) = $what;
		if ( isset( $have[ $kind ][ $slug ] ) || in_array( $slug, $ignored, true ) ) {
			continue;
		}
		$cover = teg_cal_save_cover( $kind, $slug, $code, isset( $book['image']['large'] ) ? $book['image']['large'] : '' );
		if ( is_wp_error( $cover ) ) {
			$status['errors'][] = $book['name'] . ': ' . $cover->get_error_message() . ' (tries again next time)';
			continue;
		}
		$found[ $kind ][]       = array(
			'slug'  => $slug,
			'label' => $label,
			'id'    => $code,
			'url'   => 'https://www.calameo.com/read/' . $code,
			'w'     => (int) $cover[1],
			'h'     => (int) $cover[2],
			'file'  => $cover[0],
			'name'  => sanitize_text_field( $book['name'] ),
			'found' => time(),
		);
		$have[ $kind ][ $slug ] = true;
		$status['added'][]      = sanitize_text_field( $book['name'] );
	}
	if ( $status['added'] ) {
		update_option( 'teg_cal_found', $found, false );
		do_action( 'litespeed_purge_all' );
	}
	update_option( 'teg_cal_status', $status, false );
	return $status;
}

add_action( 'teg_calameo_check', 'teg_cal_check' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'teg_calameo_check' ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', 'teg_calameo_check' );
		}
	}
);
/* switching to another theme stops the checks */
add_action(
	'switch_theme',
	function () {
		wp_clear_scheduled_hook( 'teg_calameo_check' );
	}
);

/* ------------------------------------------------------------------------------------------ */
/* Appearance > TEG Media issues                                                              */
/* ------------------------------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'TEG Media issues', 'TEG Media issues', 'manage_options', 'teg-media-issues', 'teg_cal_screen' );
	}
);

add_action(
	'admin_post_teg_cal_check',
	function () {
		check_admin_referer( 'teg_cal_check' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only an administrator can do this.' );
		}
		teg_cal_check();
		wp_safe_redirect( admin_url( 'themes.php?page=teg-media-issues&checked=1' ) );
		exit;
	}
);

/* takes a wrongly recognised publication off the site, and stops it coming back */
add_action(
	'admin_post_teg_cal_remove',
	function () {
		check_admin_referer( 'teg_cal_remove' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only an administrator can do this.' );
		}
		$slug  = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$found = teg_cal_found();
		foreach ( $found as $kind => $list ) {
			$found[ $kind ] = array_values(
				array_filter(
					$list,
					function ( $c ) use ( $slug ) {
						return $c['slug'] !== $slug;
					}
				)
			);
		}
		update_option( 'teg_cal_found', $found, false );
		$ignored   = (array) get_option( 'teg_cal_ignored', array() );
		$ignored[] = $slug;
		update_option( 'teg_cal_ignored', array_values( array_unique( $ignored ) ), false );
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( admin_url( 'themes.php?page=teg-media-issues&removed=1' ) );
		exit;
	}
);

function teg_cal_screen() {
	$issues = teg_cal_issues();
	$status = get_option( 'teg_cal_status' );
	$found  = teg_cal_found();
	$names  = array(
		'expat'     => 'The Expat',
		'welcome'   => 'Welcome Guide',
		'education' => 'Education Guide',
	);
	echo '<div class="wrap"><h1>TEG Media issues</h1>';
	echo '<p>The covers, issue names and &ldquo;Read&rdquo; links across the site come from Calam&eacute;o. Twice a day this site checks TEG&rsquo;s newest publications there; a new issue of The Expat, Welcome Guide or Education Guide appears on every page on its own, cover included.</p>';
	if ( isset( $_GET['checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Checked.</p></div>';
	}
	if ( isset( $_GET['removed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Removed. It will not be added again.</p></div>';
	}
	echo '<h2>Showing now</h2><table class="widefat striped" style="max-width:720px"><tbody>';
	foreach ( $names as $kind => $name ) {
		$c = $issues[ $kind ][0];
		printf(
			'<tr><td style="width:64px"><img src="%s" alt="" style="width:48px;height:auto;display:block"></td><td><strong>%s %s</strong><br><a href="%s" target="_blank" rel="noopener">Read on Calam&eacute;o</a></td><td>%s</td></tr>',
			esc_url( 'expat' === $kind ? teg_cal_latest_img( $issues ) : teg_cal_guide_img( $issues, $kind ) ),
			esc_html( $name ),
			esc_html( $c['label'] ),
			esc_url( $c['url'] ),
			'expat' === $kind ? (int) count( $issues['expat'] ) . ' issues online' : ''
		);
	}
	echo '</tbody></table>';

	echo '<h2>Last check</h2>';
	if ( ! $status ) {
		echo '<p>Not checked yet. The first check runs a few minutes after the theme is switched on.</p>';
	} else {
		printf( '<p>%s ago. ', esc_html( human_time_diff( $status['time'] ) ) );
		if ( $status['ok'] ) {
			printf( 'Read %d publications from Calam&eacute;o. ', (int) $status['seen'] );
			echo $status['added'] ? 'Added: <strong>' . esc_html( implode( ', ', $status['added'] ) ) . '</strong>.' : 'Nothing new.';
		} else {
			echo '<strong>Could not read Calam&eacute;o.</strong> The site keeps showing the issues it has.';
		}
		echo '</p>';
		foreach ( $status['errors'] as $e ) {
			printf( '<p style="color:#b32d2e">%s</p>', esc_html( $e ) );
		}
	}
	printf(
		'<form method="post" action="%s"><input type="hidden" name="action" value="teg_cal_check">%s<p><button class="button button-primary">Check Calam&eacute;o now</button></p></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'teg_cal_check', '_wpnonce', true, false )
	);

	$rows = array();
	foreach ( $found as $kind => $list ) {
		foreach ( $list as $c ) {
			$rows[] = array( $kind, $c );
		}
	}
	if ( $rows ) {
		echo '<h2>Added from Calam&eacute;o since the theme was installed</h2><table class="widefat striped" style="max-width:720px"><tbody>';
		foreach ( $rows as list( $kind, $c ) ) {
			printf(
				'<tr><td style="width:64px"><img src="%s" alt="" style="width:48px;height:auto;display:block"></td><td><strong>%s %s</strong><br><span class="description">Named &ldquo;%s&rdquo; on Calam&eacute;o, added %s ago</span></td><td><form method="post" action="%s" onsubmit="return confirm(\'Take this off the site? It will not be added again.\')"><input type="hidden" name="action" value="teg_cal_remove"><input type="hidden" name="slug" value="%s">%s<button class="button-link">Wrong? Remove</button></form></td></tr>',
				esc_url( teg_cal_src( $kind, $c ) ),
				esc_html( $names[ $kind ] ),
				esc_html( $c['label'] ),
				esc_html( isset( $c['name'] ) ? $c['name'] : '' ),
				esc_html( human_time_diff( isset( $c['found'] ) ? $c['found'] : time() ) ),
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr( $c['slug'] ),
				wp_nonce_field( 'teg_cal_remove', '_wpnonce', true, false )
			);
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
