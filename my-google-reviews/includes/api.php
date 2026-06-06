<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a Google Maps URL and return [ name, lat, lng ] or WP_Error.
 * Handles /maps/place/Business+Name/@lat,lng,... URLs.
 */
function mgr_parse_maps_url( string $maps_url ): array|WP_Error {
	// Extract business name from path: /maps/place/James+Whitby+Web/@...
	if ( ! preg_match( '#/maps/place/([^/@]+)/@([-\d.]+),([-\d.]+)#', $maps_url, $m ) ) {
		return new WP_Error( 'mgr_url', 'Could not parse that Google Maps URL. Make sure it is a business listing URL.' );
	}

	return [
		'name' => urldecode( str_replace( '+', ' ', $m[1] ) ),
		'lat'  => (float) $m[2],
		'lng'  => (float) $m[3],
	];
}

/**
 * Use Places API Text Search to resolve a business name + coordinates to a Place ID.
 * Returns the Place ID string or WP_Error.
 */
function mgr_resolve_place_id( string $api_key, string $name, float $lat, float $lng ): string|WP_Error {
	$response = wp_remote_post( 'https://places.googleapis.com/v1/places:searchText', [
		'timeout' => 15,
		'headers' => [
			'Content-Type'     => 'application/json',
			'X-Goog-Api-Key'   => $api_key,
			'X-Goog-FieldMask' => 'places.id,places.displayName',
		],
		'body' => wp_json_encode( [
			'textQuery'      => $name,
			'locationBias'   => [
				'circle' => [
					'center' => [ 'latitude' => $lat, 'longitude' => $lng ],
					'radius' => 500.0,
				],
			],
			'maxResultCount' => 1,
		] ),
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

	$place_id = $body['places'][0]['id'] ?? '';
	if ( ! $place_id ) {
		return new WP_Error( 'mgr_notfound', 'No matching place found. Check the Maps URL is a business listing.' );
	}

	return $place_id;
}

/**
 * Fetch reviews from Google Places API (New) and save to DB.
 * Returns true on success, WP_Error on failure.
 */
function mgr_sync_reviews() {
	$api_key  = get_option( 'mgr_api_key', '' );
	$place_id = get_option( 'mgr_place_id', '' );

	if ( ! $api_key ) {
		return new WP_Error( 'mgr_config', 'API key not configured.' );
	}

	// If no Place ID yet, try to resolve from the saved Maps URL
	if ( ! $place_id ) {
		$maps_url = get_option( 'mgr_maps_url', '' );
		if ( ! $maps_url ) {
			return new WP_Error( 'mgr_config', 'No Google Maps URL configured.' );
		}

		$parsed = mgr_parse_maps_url( $maps_url );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$resolved = mgr_resolve_place_id( $api_key, $parsed['name'], $parsed['lat'], $parsed['lng'] );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$place_id = $resolved;
		update_option( 'mgr_place_id', $place_id );
	}

	$url = add_query_arg(
		[
			'fields'       => 'id,displayName,formattedAddress,rating,userRatingCount,reviews',
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
