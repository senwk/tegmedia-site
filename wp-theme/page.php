<?php
/* Any page without a designed layout: the cart, checkout, account and the older pages. */
get_header();
while ( have_posts() ) :
	the_post();
	teg_band( get_the_title(), teg_is_shop_page() ? 'Shop' : '' );
	echo '<div class="wrap wp-body' . ( teg_is_shop_page() ? ' wc-body' : ' prose' ) . '">';
	the_content();
	echo '</div>';
endwhile;
get_footer();
