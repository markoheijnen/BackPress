<?php

class WP_Pass {
	/**
	 * Create a hash (encrypt) of a plain text password
	 *
	 * For integration with other applications, this function can be
	 * overwritten to instead use the other package password checking
	 * algorithm.
	 *
	 * @since 2.5
	 * @global object $wp_hasher PHPass object
	 * @uses PasswordHash::HashPassword
	 *
	 * @param string $password Plain text user password to hash
	 * @return string The hash string of the password
	 */
	function hash_password($password) {
		global $wp_hasher;

		if ( empty($wp_hasher) ) {
			require_once( BACKPRESS_PATH . 'class.passwordhash.php');
			// By default, use the portable hash from phpass
			$wp_hasher = new PasswordHash(8, TRUE);
		}

		return $wp_hasher->HashPassword($password);
	}

	/**
	 * Checks the plaintext password against the encrypted Password
	 *
	 * Maintains compatibility between old version and the new cookie
	 * authentication protocol using PHPass library. The $hash parameter
	 * is the encrypted password and the function compares the plain text
	 * password when encypted similarly against the already encrypted
	 * password to see if they match.
	 *
	 * For integration with other applications, this function can be
	 * overwritten to instead use the other package password checking
	 * algorithm.
	 *
	 * @since 2.5
	 * @global object $wp_hasher PHPass object used for checking the password
	 *	against the $hash + $password
	 * @uses PasswordHash::CheckPassword
	 *
	 * @param string $password Plaintext user's password
	 * @param string $hash Hash of the user's password to check against.
	 * @return bool False, if the $password does not match the hashed password
	 */
	function check_password($password, $hash, $user_id = '') {
		global $wp_hasher, $wp_users_object;
		// If the hash is still md5...
		if ( strlen($hash) <= 32 ) {
			$check = ( $hash == md5($password) );
			if ( $check && $user_id ) {
				// Rehash using new hash.
				$wp_users_object->set_password($password, $user_id);
				$hash = WP_Pass::hash_password($password);
			}

			return apply_filters('check_password', $check, $password, $hash, $user_id);
		}

		// If the stored hash is longer than an MD5, presume the
		// new style phpass portable hash.
		if ( empty($wp_hasher) ) {
			require_once( BACKPRESS_PATH . 'class.passwordhash.php');
			// By default, use the portable hash from phpass
			$wp_hasher = new PasswordHash(8, TRUE);
		}

		$check = $wp_hasher->CheckPassword($password, $hash);
		return apply_filters('check_password', $check, $password, $hash, $user_id);
	}

	/**
	 * Generates a random password drawn from the defined set of characters
	 *
	 * @since WP 2.5
	 *
	 * @return string The random password
	 **/
	function generate_password($length = 12, $special_chars = true) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars )
			$chars .= '!@#$%^&*()';
		$password = '';
		for ( $i = 0; $i < $length; $i++ )
			$password .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		return $password;
	}
}
