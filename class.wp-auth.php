<?php

class WP_Auth {
	var $db;
	var $users;

	var $auth_cookie;
	var $cookie_domains;
	var $cookie_paths;
	var $cookie_secure = false;

	var $current = 0;

	function WP_Auth( &$db, &$users, $cookie_args ) {
		$this->__construct( $db, $users, $cookie_args );
//		register_shutdown_function( array(&$this, '__destruct') );
	}

	function __construct( &$db, &$users, $cookie_args ) {
		$this->db =& $db;
		$this->users =& $users;

		$cookie_args = wp_parse_args( $cookie_args, array( 'domain' => null, 'path' => null, 'name' => '', 'secure' => false ) );

		$this->auth_cookie = (string) $cookie_args['name'];
		if ( !$this->cookie_domains = (array) $cookie_args['domain'] )
			$this->cookie_domains = array( false );
		if ( !$this->cookie_paths = (array) $cookie_args['path'] )
			$this->cookie_paths = array( '/' );
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
	 * @uses do_action() Calls 'set_current_user' hook after setting the current user.
	 *
	 * @param int $id User ID
	 * @param string $name User's username
	 * @return WP_User Current user User object
	 */
	function set_current_user( $user_id ) {
		$user = $this->users->get_user( $user_id );
		if ( !$user || is_wp_error( $user ) ) {
			$this->current = 0;
			return $this->current;
		}

		$user_id = $user->ID;

		if ( isset($this->current->ID) && $user_id == $this->current->ID )
			return $this->current;

		$this->current = new WP_User( $user_id );

		// WP add_action( 'set_current_user', 'setup_userdata', 1 );

		do_action('set_current_user', $user_id );

		return $this->current;
	}

	/**
	 * get_current_user() - Populate variables with information about the currently logged in user
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

		$user = $this->users->get_user($username);
		if ( !$user || is_wp_error( $user ) )
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
	function generate_auth_cookie( $user_id, $expiration ) {
		$user = $this->users->get_user( $user_id );
		if ( !$user || is_wp_error($user) )
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
		if ( !$expiration = $expire = (int) $expiration )
			$expiration = time() + 172800; // 2 days

		$cookie = $this->generate_auth_cookie($user_id, $expiration);
		if ( is_wp_error( $cookie ) )
			return $cookie;

		do_action('set_auth_cookie', $cookie, $expiration);

		foreach ( $this->cookie_domains as $domain ) {
			if ( $domain ) {
				foreach ( $this->cookie_paths as $path )
					setcookie($this->auth_cookie, $cookie, $expire, $path, $domain);
			} else {
				foreach ( $this->cookie_paths as $path )
					setcookie($this->auth_cookie, $cookie, $expire, $path );
			}
		}

	}

	/**
	 * clear_auth_cookie() - Deletes all of the cookies associated with authentication
	 *
	 * @since 2.5
	 */
	function clear_auth_cookie() {
		foreach ( $this->cookie_domains as $domain ) {
			if ( $domain ) {
				foreach ( $this->cookie_paths as $path )
					setcookie($this->auth_cookie, ' ', time() - 31536000, $path, $domain);
			} else {
				foreach ( $this->cookie_paths as $path )
					setcookie($this->auth_cookie, ' ', time() - 31536000, $path );
			}
		}
	}
}

?>
