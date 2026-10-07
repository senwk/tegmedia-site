<?php
/* The shop, in the site's header and footer. Product pages have their own layout
   (teg_product_page in functions.php); lists of products are drawn by WooCommerce. */
get_header();
if ( is_product() ) {
	while ( have_posts() ) {
		the_post();
		teg_product_page( wc_get_product( get_the_ID() ) );
	}
} else {
	teg_band( woocommerce_page_title( false ), 'Shop' );
	echo '<div class="wrap wp-body wc-body">';
	woocommerce_content();
	echo '</div>';
}
get_footer();
