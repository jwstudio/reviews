<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch reviews from Google Places API (New) and save to DB.
 * Returns true on success, WP_Error on failure.
 */
function mgr_sync_reviews() {
	$api_key  = get_option( 'mgr_api_key', '' );
	$place_id = get_option( 'mgr_place_id', '' );

	if ( ! $api_key || ! $place_id ) {
		return new WP_Error( 'mgr_config', 'API key or Place ID not configured.' );
	}

	$url = add_query_arg(
		[
			'fields'       => 'id,displayName,formattedAddress,rating,userRatingCount,photos,reviews',
			'languageCode' => get_option( 'mgr_language', 'en' ),
		],
		'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id )
	);

	$response = wp_remote_get( $url, [
		'timeout' => 15,
		'headers' => [
			'X-Goog-Api-Key'  => $api_key,
			'Accept-Language' => get_option( 'mgr_language', 'en' ),
		],
	] );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 ) {
		$message = $body['error']['message'] ?? 'Unknown API error.';
		return new WP_Error( 'mgr_api', $message );
	}

	// Save place-level details
	$place_details = [
		'place_id'      => $place_id,
		'name'          => $body['displayName']['text'] ?? '',
		'address'       => $body['formattedAddress'] ?? '',
		'rating'        => $body['rating'] ?? 0,
		'total_reviews' => $body['userRatingCount'] ?? 0,
	];
	update_option( 'mgr_place_details', wp_json_encode( $place_details ) );

	// Save reviews
	$reviews = $body['reviews'] ?? [];
	mgr_upsert_reviews( $reviews );

	update_option( 'mgr_last_sync', current_time( 'mysql' ) );

	return true;
}

/**
 * Insert new reviews; skip ones we already have (preserve hidden flag on existing).
 */
function mgr_upsert_reviews( array $reviews ) {
	global $wpdb;
	$table = $wpdb->prefix . 'google_reviews';

	foreach ( $reviews as $r ) {
		$review_id = $r['name'] ?? '';  // "places/{id}/reviews/{reviewId}"
		if ( ! $review_id ) {
			continue;
		}

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table WHERE review_id = %s LIMIT 1",
			$review_id
		) );

		$data = [
			'author'    => $r['authorAttribution']['displayName'] ?? '',
			'photo_url' => $r['authorAttribution']['photoUri'] ?? '',
			'rating'    => (int) ( $r['rating'] ?? 0 ),
			'body'      => $r['text']['text'] ?? '',
			'published' => gmdate( 'Y-m-d H:i:s', strtotime( $r['publishTime'] ?? 'now' ) ),
		];

		if ( $exists ) {
			// Update text/rating but keep hidden flag untouched
			$wpdb->update( $table, $data, [ 'review_id' => $review_id ] );
		} else {
			$wpdb->insert( $table, array_merge( $data, [
				'review_id' => $review_id,
				'hidden'    => 0,
			] ) );
		}
	}
}

/**
 * Public helper — use this in your ACF flex content templates.
 *
 * @param array $args  Optional: [ 'hidden' => false, 'limit' => 0, 'min_rating' => 0 ]
 * @return array       Array of review row objects.
 */
function mgr_get_reviews( array $args = [] ) {
	global $wpdb;
	$table = $wpdb->prefix . 'google_reviews';

	$hidden     = $args['hidden']     ?? false;
	$limit      = (int) ( $args['limit']      ?? 0 );
	$min_rating = (int) ( $args['min_rating'] ?? 0 );

	$where  = $hidden ? '' : 'WHERE hidden = 0';
	$where .= $min_rating ? ( $where ? " AND rating >= $min_rating" : "WHERE rating >= $min_rating" ) : '';
	$order  = 'ORDER BY published DESC';
	$lim    = $limit ? "LIMIT $limit" : '';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( "SELECT * FROM $table $where $order $lim" );
}

/**
 * Public helper — returns the decoded place details array or an empty array.
 */
function mgr_get_place_details() {
	$raw = get_option( 'mgr_place_details', '' );
	return $raw ? json_decode( $raw, true ) : [];
}
