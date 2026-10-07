<?php
/* Posts and lists of posts (the old news), in the site's header and footer. */
get_header();
if ( is_singular() ) {
	while ( have_posts() ) {
		the_post();
		teg_band( get_the_title(), get_the_date() );
		echo '<div class="wrap wp-body prose">';
		the_content();
		echo '</div>';
	}
} else {
	teg_band( is_home() ? 'News' : wp_strip_all_tags( get_the_archive_title() ) );
	echo '<div class="wrap wp-body prose wp-list">';
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			printf(
				'<article><span class="date">%s</span><h2><a href="%s">%s</a></h2><p>%s</p></article>',
				esc_html( get_the_date() ),
				esc_url( get_permalink() ),
				esc_html( get_the_title() ),
				esc_html( wp_trim_words( get_the_excerpt(), 40 ) )
			);
		}
		the_posts_pagination();
	} else {
		echo '<p>Nothing here yet.</p>';
	}
	echo '</div>';
}
get_footer();
