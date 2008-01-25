<?php

class WP_Auth {
	var $db;
	var $auth_cookie;
	var $cookie_domains;
	var $cookie_paths;
	var $cookie_secure = false;

	var $current = 0;

	function WP_Auth( &$db, $cookie_args ) {
		$this->__construct( $db, $cookie_args );
//		register_shutdown_function( array(&$this, '__destruct') );
	}

	function __construct( &$db, $cookie_args ) {
		$this->db =& $db;

		$cookie_args = wp_parse_args( $cookie_args, array( 'domain' => null, 'path' => null, 'name' => '', 'secure' => false ) );

		$this->cookie_domains = (array) $cookie_args['domain'];
		$this->cookie_paths = (array) $cookie_args['path'];
		$this->auth_cookie = (string) $cookie_args['name'];
		$this->cookie_secure = (bool) $cookie_args['secure'];
	}

//	function __destruct() {
//	}

	/**
	 * set_current_user() - Changes the current user by ID or name
	 *
	 * Set $id to null and specify a name if you do not know a user's ID
	 *
	 * Some WordPress functionality is based on the current user and
	 * not based on the signed in user. Therefore, it opens the ability
	 * to edit and perform actions on users who aren't signed in.
	 *
	 * @since 2.0.4
	 * @global object $current_user The current user object which holds the user data.
	 * @uses do_action() Calls 'set_current_user' hook after setting the current user.
	 *
	 * @param int $id User ID
	 * @param string $name User's username
	 * @return WP_User Current user User object
	 */
	function set_current_user( $user_id ) {
		global $wp_users_object;

		$user = $wp_users_object->get_user( $user_id );
		if ( is_wp_error( $user ) ) {
			$this->current = 0;
			return $this->current;
		}

		$user_id = $user->ID;

		if ( isset($this->current->ID) && $user_id == $this->current->ID )
			return $this->current;

		// TODO: WP_User may not be generic enough for backpress - look into that
		$this->current = new BB_User( $user_id );

		// WP add_action( 'set_current_user', 'setup_userdata', 1 );

		do_action('set_current_user', $user_id );

		return $this->current;
	}

	/**
	 * get_current_user() - Populate global variables with information about the currently logged in user
	 *
	 * Will set the current user, if the current user is not set. The current
	 * user will be set to the logged in person. If no user is logged in, then
	 * it will set the current user to 0, which is invalid and won't have any
	 * permissions.
 	*
	 * @since 0.71
 	* @uses $current_user Checks if the current user is set
 	* @uses wp_validate_auth_cookie() Retrieves current logged in user.
 	*
 	* @return bool|null False on XMLRPC Request and invalid auth cookie. Null when current user set
 	*/
	function get_current_user() {
		if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) // Why?
			return false;

		if ( !empty($this->current) )
			return $this->current;

		if ( $user_id = $this->validate_auth_cookie() )
			$this->set_current_user( $user_id );
		else
			$this->set_current_user( 0 );

		return $this->current;
	}

	/**
	 * validate_auth_cookie() - Validates authentication cookie
	 *
	 * The checks include making sure that the authentication cookie
	 * is set and pulling in the contents (if $cookie is not used).
	 *
	 * Makes sure the cookie is not expired. Verifies the hash in
	 * cookie is what is should be and compares the two.
	 *
	 * @since 2.5
	 *
	 * @param string $cookie Optional. If used, will validate contents instead of cookie's
	 * @return bool|int False if invalid cookie, User ID if valid.
	 */
	function validate_auth_cookie( $cookie = null ) {
		global $wp_users_object;

		if ( empty($cookie) ) {
			if ( empty($_COOKIE[$this->auth_cookie]) )
				return false;
			$cookie = $_COOKIE[$this->auth_cookie];
		}

		list($username, $expiration, $hmac) = explode('|', $cookie);

		$expired = $expiration;

		// Allow a grace period for POST and AJAX requests
		if ( defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD'] )
			$expired += 3600;

		if ( $expired < time() )
			return false;

		$key  = wp_hash($username . $expiration);
		$hash = hash_hmac('md5', $username . $expiration, $key);
	
		if ( $hmac != $hash )
			return false;

		$user = $wp_users_object->get_user($username);
		if ( is_wp_error( $user ) )
			return $user;

		return $user->ID;
	}

	/**
	 * generate_auth_cookie() - Generate authentication cookie contents
	 *
	 * @since 2.5
	 * @uses apply_filters() Calls 'auth_cookie' hook on $cookie contents, User ID
	 *		and expiration of cookie.
	 *
	 * @param int $user_id User ID
	 * @param int $expiration Cookie expiration in seconds
	 * @return string Authentication cookie contents
	 */
	function wp_generate_auth_cookie( $user_id, $expiration ) {
		global $wp_users_object;
		$user = $wp_users_object->get_user( $user_id );
		if ( is_wp_error($user) )
			return $user;

		$key  = wp_hash($user->user_login . $expiration);
		$hash = hash_hmac('md5', $user->user_login . $expiration, $key);

		$cookie = $user->user_login . '|' . $expiration . '|' . $hash;

		return apply_filters('auth_cookie', $cookie, $user_id, $expiration);
	}

	/**
	 * set_auth_cookie() - Sets the authentication cookies based User ID
	 *
	 * The $remember parameter increases the time that the cookie will
	 * be kept. The default the cookie is kept without remembering is
	 * two days. When $remember is set, the cookies will be kept for
	 * 14 days or two weeks.
	 *
	 * @since 2.5
	 *
	 * @param int $user_id User ID
	 * @param bool $remember Whether to remember the user or not
	 */
	function set_auth_cookie( $user_id, $expiration = 0 ) {
		$cookie = $this->generate_auth_cookie($user_id, $expiration);
		if ( !$expiration = $expire = (int) $expiration )
			$expiration = time() + 172800; // 2 days

		do_action('set_auth_cookie', $cookie, $expiration);

		foreach ( $this->cookie_domains as $domain )
			foreach ( $this->cookie_paths as $path )
				setcookie($this->auth_cookie, $cookie, $expire, $path, $domain);
	}

	/**
	 * clear_auth_cookie() - Deletes all of the cookies associated with authentication
	 *
	 * @since 2.5
	 */
	function clear_auth_cookie() {
		foreach ( $this->cookie_domains as $domain )
			foreach ( $this->cookie_paths as $path )
				setcookie($this->auth_cookie, ' ', time() - 31536000, $path, $domain);
	}

	/**
	 * hash_password() - Create a hash (encrypt) of a plain text password
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
	 * check_password() - Checks the plaintext password against the encrypted Password
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
	function check_password($password, $hash) {
		global $wp_hasher;

		if ( strlen($hash) <= 32 )
			return ( $hash == md5($password) );

		// If the stored hash is longer than an MD5, presume the
		// new style phpass portable hash.
		if ( empty($wp_hasher) ) {
			require_once( BACKPRESS_PATH . 'class.passwordhash.php');
			// By default, use the portable hash from phpass
			$wp_hasher = new PasswordHash(8, TRUE);
		}

		$check = $wp_hasher->CheckPassword($password, $hash);
		return apply_filters('check_password', $check, $password, $hash);
	}

	/**
	 * generate_password() - Generates a random password drawn from the defined set of characters
	 *
	 * @since 2.5
	 *
	 * @return string The random password
	 **/
	function generate_password( $length = 7 ) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$length = (int) $length;
		$password = '';
		for ( $i = 0; $i < $length; $i++ )
			$password .= substr($chars, mt_rand(0, 61), 1);
		return $password;
	}

	/**
	 * set_password() - Updates the user's password with a new encrypted one
	 *
	 * For integration with other applications, this function can be
	 * overwritten to instead use the other package password checking
	 * algorithm.
	 *
	 * @since 2.5
	 * @uses wp_hash_password() Used to encrypt the user's password before passing to the database
	 *
	 * @param string $password The plaintext new user password
	 * @param int $user_id User ID
	 */
	function set_password( $password, $user_id ) {
		global $wp_users_object;
		$user = $wp_users_object->get_user( $user_id );
		if ( is_wp_error( $user ) )
			return $user;

		$user_id = $user->ID;
		$hash = $this->hash_password($password);
		$wp_users_object->update_user( $user->ID, array( 'user_pass', $hash ) );
	}
}

?>
