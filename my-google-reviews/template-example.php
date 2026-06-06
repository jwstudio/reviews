<?php
/**
 * Example: ACF Flexible Content template partial for Google Reviews.
 *
 * Drop this in your theme's ACF flex content layout folder, e.g.:
 *   template-parts/flexible/layout-google-reviews.php
 *
 * Requires the My Google Reviews plugin to be active.
 */

// Bail gracefully if plugin isn't active
if ( ! function_exists( 'mgr_get_reviews' ) ) {
	return;
}

$place   = mgr_get_place_details();
$reviews = mgr_get_reviews( [
	'min_rating' => 4,   // only show 4-star and above
	'limit'      => 6,   // cap at 6 cards
] );

if ( ! $reviews ) {
	return;
}

// Render star icons as pure HTML — no icon library needed
function mgr_stars( int $rating ): string {
	$out = '';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= $i <= $rating ? '&#9733;' : '&#9734;';
	}
	return $out;
}
?>

<section class="reviews-section py-5">
	<div class="container">

		<?php if ( $place ) : ?>
		<div class="reviews-header mb-4 text-center">
			<h2 class="h3"><?php echo esc_html( $place['name'] ); ?></h2>
			<p class="text-muted mb-1"><?php echo esc_html( $place['address'] ); ?></p>
			<p>
				<span class="text-warning fs-5"><?php echo mgr_stars( (int) round( $place['rating'] ) ); ?></span>
				<strong><?php echo esc_html( number_format( $place['rating'], 1 ) ); ?></strong>
				&mdash; <?php echo esc_html( number_format( $place['total_reviews'] ) ); ?> reviews on Google
			</p>
		</div>
		<?php endif; ?>

		<div class="row g-4">
			<?php foreach ( $reviews as $review ) : ?>
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card h-100 shadow-sm border-0">
					<div class="card-body d-flex flex-column">

						<div class="d-flex align-items-center mb-3">
							<?php if ( $review->photo_url ) : ?>
							<img
								src="<?php echo esc_url( $review->photo_url ); ?>"
								alt="<?php echo esc_attr( $review->author ); ?>"
								width="48" height="48"
								class="rounded-circle me-3"
								loading="lazy"
							>
							<?php else : ?>
							<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3 text-white fw-bold"
								style="width:48px;height:48px;font-size:1.2rem">
								<?php echo esc_html( mb_substr( $review->author, 0, 1 ) ); ?>
							</div>
							<?php endif; ?>
							<div>
								<div class="fw-semibold"><?php echo esc_html( $review->author ); ?></div>
								<div class="text-muted small"><?php echo esc_html( date_i18n( 'F Y', strtotime( $review->published ) ) ); ?></div>
							</div>
						</div>

						<div class="text-warning mb-2"><?php echo mgr_stars( (int) $review->rating ); ?></div>

						<p class="card-text flex-grow-1"><?php echo nl2br( esc_html( $review->body ) ); ?></p>

					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

	</div>
</section>
