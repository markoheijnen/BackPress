<?php

function maybe_serialize( $data ) {
	if ( is_string( $data ) )
		$data = trim( $data );
	elseif ( is_array( $data ) || is_object( $data ) )
		return serialize( $data );
	if ( is_serialized( $data ) )
		return serialize( $data );
	return $data;
}

function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) { // don't attempt to unserialize data that wasn't serialized going in
		if ( 'b:0;' === $data )
			return false;
		if ( false !== $gm = @unserialize( $original ) )
			return $gm;
	}
	return $original;
}

function is_serialized( $data ) {
	// if it isn't a string, it isn't serialized
	if ( !is_string( $data ) )
		return false;
	$data = trim( $data );
	if ( 'N;' == $data )
		return true;
	if ( !preg_match( '/^([adObis]):/', $data, $badions ) )
		return false;
	switch ( $badions[1] ) {
		case 'a' :
		case 'O' :
		case 's' :
			if ( preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data ) )
				return true;
			break;
		case 'b' :
		case 'i' :
		case 'd' :
			if ( preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data ) )
				return true;
			break;
	}
	return false;
}

function is_serialized_string( $data ) {
	// if it isn't a string, it isn't a serialized string
	if ( !is_string( $data ) )
		return false;
	$data = trim( $data );
	if ( preg_match( '/^s:[0-9]+:.*;$/s', $data ) ) // this should fetch all serialized strings
		return true;
	return false;
}

function is_email($user_email) {
	$chars = "/^([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}\$/i";
	if (strpos($user_email, '@') !== false && strpos($user_email, '.') !== false) {
		if (preg_match($chars, $user_email)) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function sanitize_title($title, $fallback_title = '') {
	$title = strip_tags($title);
	$title = apply_filters('sanitize_title', $title);

	if ( '' === $title || false === $title )
		$title = $fallback_title;

	return $title;
}

function sanitize_title_with_dashes($title) {
	$title = strip_tags($title);
	// Preserve escaped octets.
	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
	// Remove percent signs that are not part of an octet.
	$title = str_replace('%', '', $title);
	// Restore octets.
	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

	$title = remove_accents($title);
	if (seems_utf8($title)) {
		if (function_exists('mb_strtolower')) {
			$title = mb_strtolower($title, 'UTF-8');
		}
		$title = utf8_uri_encode($title, 200);
	}

	$title = strtolower($title);
	$title = preg_replace('/&.+?;/', '', $title); // kill entities
	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
	$title = preg_replace('/\s+/', '-', $title);
	$title = preg_replace('|-+|', '-', $title);
	$title = trim($title, '-');

	return $title;
}

function sanitize_user( $username, $strict = false ) {
	$raw_username = $username;
	$username = strip_tags($username);
	// Kill octets
	$username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username);
	$username = preg_replace('/&.+?;/', '', $username); // Kill entities

	// If strict, reduce to ASCII for max portability.
	if ( $strict )
		$username = preg_replace('|[^a-z0-9 _.\-@]|i', '', $username);

	return apply_filters('sanitize_user', $username, $raw_username, $strict);
}

function backpress_gmt_strtotime( $string ) {
	if ( is_numeric($string) )
		return $string;
	if ( !is_string($string) )
		return -1;

	if ( stristr($string, 'utc') || stristr($string, 'gmt') || stristr($string, '+0000') )
		return strtotime($string);

	if ( -1 == $time = strtotime($string . ' +0000') )
		return strtotime($string);

	return $time;
}

/**
 * Converts input to an absolute integer
 * @param mixed $maybeint data you wish to have convered to an absolute integer
 * @return int an absolute integer
 */     
function absint( $maybeint ) {  
	return abs( intval( $maybeint ) );
}

/**
 * Escapes text for SQL LIKE special characters % and _
 *
 * @param string text the text to be escaped
 * @return string text, safe for inclusion in LIKE query
 */
function like_escape($text) {
	return str_replace(array("%", "_"), array("\\%", "\\_"), $text);
}

function wp_specialchars( $text, $quotes = 0 ) { // [WP4451]
	// Like htmlspecialchars except don't double-encode HTML entities
	$text = str_replace('&&', '&#038;&', $text);
	$text = str_replace('&&', '&#038;&', $text);
	$text = preg_replace('/&(?:$|([^#])(?![a-z1-4]{1,8};))/', '&#038;$1', $text);
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('>', '&gt;', $text);
	if ( 'double' === $quotes ) {
		$text = str_replace('"', '&quot;', $text);
	} elseif ( 'single' === $quotes ) {
		$text = str_replace("'", '&#039;', $text);
	} elseif ( $quotes ) {
		$text = str_replace('"', '&quot;', $text);
		$text = str_replace("'", '&#039;', $text);
	}
	return $text;
}

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object($args) )
		$r = get_object_vars($args);
	else if ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	else
		return $r;
}

function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array ); // parse_str() adds slashes if magicquotes is on.  See: http://php.net/parse_str
	$array = apply_filters( 'wp_parse_str', $array );
}

function stripslashes_deep($value) { // [5261]
	return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
}
