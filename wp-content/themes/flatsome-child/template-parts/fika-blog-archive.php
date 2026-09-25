<?php
/** Custom editorial archive loop shared by Category, Tag, Date, Author, Home and Search. */
defined( 'ABSPATH' ) || exit;

$archive_title       = fika_blog_archive_title();
$archive_description = fika_blog_archive_safe_description( get_the_archive_description() );
$archive_grid_count  = isset( $GLOBALS['wp_query']->post_count ) ? max( 1, min( 3, (int) $GLOBALS['wp_query']->post_count ) ) : 3;
?>
<main id="main" class="fika-archive-main">
	<div class="fika-archive-shell">
		<div class="container">
			<header class="fika-archive-hero">
				<nav class="fika-archive-breadcrumbs" aria-label="<?php echo esc_attr( fika_blog_archive_label( 'breadcrumb' ) ); ?>">
					<a href="<?php echo esc_url( fika_blog_archive_home_url() ); ?>"><?php echo esc_html( fika_blog_archive_label( 'home' ) ); ?></a>
					<span aria-hidden="true">/</span>
					<span aria-current="page"><?php echo esc_html( $archive_title ); ?></span>
				</nav>
				<p class="fika-archive-eyebrow"><?php echo esc_html( fika_blog_archive_label( 'eyebrow' ) ); ?></p>
				<h1><?php echo esc_html( $archive_title ); ?></h1>
				<?php if ( $archive_description ) : ?>
					<div class="fika-archive-description"><?php echo wp_kses_post( $archive_description ); ?></div>
				<?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="fika-archive-grid fika-archive-grid-count-<?php echo (int) $archive_grid_count; ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						$primary_category = function_exists( 'fika_get_primary_post_category' ) ? fika_get_primary_post_category( get_the_ID() ) : null;
						$categories       = $primary_category ? array( $primary_category ) : get_the_category();
						$category         = $categories ? $categories[0]->name : '';
						$excerpt          = wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 30, '…' );
						?>
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'fika-archive-card' ); ?>>
							<a class="fika-archive-card-link" href="<?php the_permalink(); ?>">
								<span class="fika-archive-card-media">
									<?php
									if ( has_post_thumbnail() ) {
										the_post_thumbnail(
											'medium_large',
											array(
												'alt'   => '',
												'sizes' => '(max-width: 549px) calc(100vw - 30px), (max-width: 849px) calc((100vw - 45px) / 2), 380px',
											)
										);
									} else {
										echo '<span class="fika-archive-card-placeholder" aria-hidden="true"></span>';
									}
									?>
								</span>
								<span class="fika-archive-card-body">
									<span class="fika-archive-card-meta">
										<?php if ( $category ) : ?><span><?php echo esc_html( $category ); ?></span><?php endif; ?>
										<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
									</span>
									<h2><?php echo esc_html( get_the_title() ); ?></h2>
									<?php if ( $excerpt ) : ?><span class="fika-archive-card-excerpt"><?php echo esc_html( $excerpt ); ?></span><?php endif; ?>
									<span class="fika-archive-card-cta"><?php echo esc_html( fika_blog_archive_label( 'read' ) ); ?><span aria-hidden="true"> →</span></span>
								</span>
							</a>
						</article>
					<?php endwhile; ?>
				</div>

				<div class="fika-archive-pagination">
					<?php
					the_posts_pagination(
						array(
							'mid_size'           => 1,
							'prev_text'          => '← ' . fika_blog_archive_label( 'previous' ),
							'next_text'          => fika_blog_archive_label( 'next' ) . ' →',
							'screen_reader_text' => fika_blog_archive_label( 'pagination' ),
						)
					);
					?>
				</div>
			<?php else : ?>
				<section class="fika-archive-empty">
					<h2><?php echo esc_html( fika_blog_archive_label( 'empty_title' ) ); ?></h2>
					<p><?php echo esc_html( fika_blog_archive_label( 'empty_text' ) ); ?></p>
					<?php get_search_form(); ?>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>
