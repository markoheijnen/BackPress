<?php

class WP_Users {
	var $db;
	var $auth_cookie;
	var $cookie_domains;
	var $cookie_paths;
	var $cookie_secure = false;

	var $current = 0;

	function WP_Users( &$db, $cookie_array ) {
		$this->__construct( $db, $cookie_array );
//		register_shutdown_function( array(&$this, '__destruct') );
	}

	function __construct( &$db, $cookie_array ) {
		$this->db =& $db;

		$cookie_array = wp_parse_args( $cookie_array, array( 'domain' => null, 'path' => null, 'name' => '', 'expire' => 0, 'secure' => false ) );

		$this->cookie_domains = (array) $cookie_array['domain'];
		$this->cookie_paths = (array) $cookie_array['path'];
		$this->auth_cookie = (string) $cookie_array['name'];
		$this->cookie_secure = (bool) $cookie_array['secure'];
	}

//	function __destruct() {
//	}

	function is_id( $id ) {
		return is_numeric( $id );
	}

	function id( $id ) {
		return (int) $id;
	}

	function _put_user( $args = null ) {
		global $backpress_user_login_cache;

		$defaults = array(
			'ID' => false,
			'user_login' => '',
			'user_nicename' => '',
			'user_email' => '',
			'user_url' => '',
			'user_pass' => false,
			'user_registered' => time(),
			'display_name' => '',
			'user_status' => 0
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args, EXTR_SKIP );

		$ID = $this->id($ID);
	
		$user_login = $this->sanitize_user( $user_login );
		$user_nicename = $this->sanitize_slug( $user_login );
		if ( !$user_login || !$user_nicename )
			return new WP_Error( 'user_login', __('Invalid login name') );

		if ( !$user_email = $this->is_email( $user_email ) )
			return new WP_Error( 'user_email', __('Invalid email address') );

		$user_url = clean_url( $user_url );

		if ( !$user_pass )
			$user_pass = $this->generate_password();
		$plain_pass = $user_pass;
		$user_pass  = $this->hash_password( $user_pass );

		if ( !is_numeric($user_registered) )
			$user_registered = backpress_gmt_strtotime( $user_registered );

		if ( !$user_registered || $user_registered < 0 )
			return new WP_Error( 'user_registered', __('Invalid registration time') );

		if ( !$user_registered = @gmdate('Y-m-d H:i:s', $user_registered) )
			return new WP_Error( 'user_registered', __('Invalid registration timestamp') );

		if ( !$display_name )
			$display_name = $user_login;

		$db_return = NULL;
		if ( $ID && NULL !== $this->db->get_var( "SELECT ID FROM $backpress->users WHERE ID = '$ID'" ) ) {
			unset($args['ID']);
			unset($args['user_registered']);
			$db_return = $this->db->update( $this->db->users, compact( array_keys($args) ), compact('ID') );
		}
		if ( $db_return === null ) { 
			$db_return = $this->db->insert( $this->db->users, compact( array_keys($args) ) );
		}
	
		if ( !$db_return )
			return new WP_Error( 'BackPress::query', __('Query failed') );

		// Cache the result
		$user = (object) compact( array_keys($defaults) );
		$user = $this->append_meta( $user );

		$args = compact( array_keys($args) );

		$args['plain_pass'] = $plain_pass;

		do_action( __FUNCTION__, $args );

		return $args;
	}

	function new_user( $args = null ) {
		$args = wp_parse_args( $args );
		$args['ID'] = false;

		$r = $this->_put_user( $args );

		if ( is_wp_error($r) )
			return $r;

		do_action( __FUNCTION__, $r, $args );

		return $r;
	}

	function update_user( $ID, $args = null ) {
		$args = wp_parse_args( $args );

		$user = $this->get_user( $ID, true, ARRAY_A );
		if ( is_wp_error( $user ) )
			return $user;

		$args = array_merge( $user, $args );
		$args['ID'] = $user['ID'];

		$r = $this->_put_user( $args );

		if ( is_wp_error($r) )
			return $r;

		do_action( __FUNCTION__, $r, $args );

		return $r;
	}

	// $ID can be user ID#, user_login, or array( ID#s )
	// TODO: array of ids, by email
	function get_user( $ID = 0, $args = null ) {
		$defaults = array( 'cache' => true, 'output' => OBJECT, 'by' => false );
		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

		if ( $this->is_id( $ID ) ) {
			$ID = $this->id( $ID );
			$sql = "SELECT * FROM {$this->db->users} WHERE ID = %s";
		} else {
			$ID = $this->sanitize_user( $ID );
			$sql = "SELECT * FROM {$this->db->users} WHERE user_login = %s";
		}

		if ( !$ID )
			return new WP_Error( 'ID', __('Invalid user id') );

		$user = $this->db->get_row( $this->db->prepare( $sql, $ID ), $output );
	
		if ( !$user ) { // Cache non-existant users.
			return new WP_Error( 'user', __('User does not exist' ) );
		}

		$user = $this->append_meta( $user );

		return $user;
	}

	function delete_user( $ID ) {
		$user = $this->get_user( $ID );

		if ( is_wp_error( $user ) )
			return $user;

		do_action( 'pre_' . __FUNCTION__, $ID );

		$r = $this->db->query( $this->db->prepare( "DELETE FROM {$this->db->users} WHERE ID = %d", $user->ID ) );
		$this->db->query( $this->db->prepare( "DELETE FROM {$this->db->users} WHERE user_id = %d", $user->ID ) );

		do_action( __FUNCTION__, $ID );

		return $r;
	}

	// Used for user meta, but can be used for other meta data (such as bbPress' topic meta)
	function append_meta( $object, $args = null ) {
		$defaults = array( 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'id_field' => 'ID' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( is_array($object) ) :
			$trans = array();
			foreach ( array_keys($object) as $i )
				$trans[$object[$i]->$id_field] =& $object[$i];
			$ids = join(',', array_keys($trans));
			if ( $metas = $backpress->get_results("SELECT $meta_field, meta_key, meta_value FROM $meta_table WHERE $meta_field IN ($ids)") )
				foreach ( $metas as $meta ) :
					$trans[$meta->$meta_field]->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
					if ( strpos($meta->meta_key, $backpress->table_prefix) === 0 )
						$trans[$meta->$meta_field]->{substr($meta->meta_key, strlen($backpress->table_prefix))} = maybe_unserialize( $meta->meta_value );
				endforeach;
			foreach ( array_keys($trans) as $i ) {
	//CACHE
			}
			return $object;
		elseif ( $object ) :
			if ( $metas = $backpress->get_results("SELECT meta_key, meta_value FROM $meta_table WHERE $meta_field = '{$object->$id_field}'") )
				foreach ( $metas as $meta ) :
					$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
					if ( strpos($meta->meta_key, $backpress->table_prefix) === 0 )
						$object->{substr($meta->meta_key, strlen($backpress->table_prefix))} = maybe_unserialize( $meta->meta_value );
				endforeach;
	//CACHE
			return $object;
		endif;
	}
	
	function update_meta( &$backpress, $args = null ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( !$this->is_id($id) )
			return false;

		if ( is_null($meta_key) || is_null($meta_value) )
			return false;

		$id = $this->id( $id );

		$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
		if ( 'usermeta' == $meta_table && 'capabilities' == $meta_key )
			$meta_key = $this->db->prefix . 'capabilities';

		$meta_tuple = compact('id', 'meta_key', 'meta_value', 'meta_table');
		$meta_tuple = apply_filters( __FUNCTION__, $meta_tuple );
		extract($meta_tuple, EXTR_OVERWRITE);

		$_meta_value = maybe_serialize( $meta_value );

		$cur = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->db->$meta_table} WHERE $meta_field = %d AND meta_key = %s", $id, $meta_key ) );
		if ( !$cur ) {
			$this->db->insert( $this->db->$meta_table, array( $meta_field => $id, 'meta_key' => $meta_key, 'meta_value' => $_meta_value ) );
		} elseif ( $cur->meta_value != $meta_value ) {
			$backpress->update( $this->db->$meta_table, array( 'meta_value' => $_meta_value ), array( $meta_field => $id, 'meta_key' => $meta_key ) );
		}

	//CACHE
		return true;
	}

	function delete_meta( $args = null ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'meta_id_field' => 'umeta_id' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( !$this->is_id($id) )
			return false;

		if ( is_null($meta_key) )
			return false;

		$id = $this->id( $id );

		$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);

		$meta_tuple = compact('id', 'meta_key', 'meta_value', 'meta_table');
		$meta_tuple = apply_filters( __FUNCTION__, $meta_tuple );
		extract($meta_tuple, EXTR_OVERWRITE);

		$_meta_value = is_null($meta_value) ? null : maybe_serialize( $meta_value );

		if ( is_null($_meta_value) )
			$meta_id = $this->db->get_var( $this->db->prepare( "SELECT $meta_id_field FROM {$this->db->$meta_table} WHERE $meta_field = %d AND meta_key = %s", $id, $meta_key ) );
		else
			$meta_id = $this->db->get_var( $this->db->prepare( "SELECT $meta_id_field FROM {$this->db->$meta_table} WHERE $meta_field = %d AND meta_key = %s AND meta_value = %s", $id, $meta_key, $_meta_value ) );

		if ( !$meta_id )
			return false;

		if ( is_null($_meta_value) )
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->db->$meta_table} WHERE $meta_field = %d AND meta_key = %s", $id, $meta_key ) );
		else
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->db->$meta_table} WHERE $meta_id_field = %d", $meta_id ) );

	//CACHE

		return true;
	}

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
		if ( !$this->is_id( $user_id ) ) {
			if ( !$user = $this->get_user( $user_id ) )
				return new WP_Error( 'user_id', __( 'Invalid User' ) );
			else
				$user_id = $user->ID;
		}

		if ( isset($this->current->ID) && $user_id == $this->current->ID )
			return $this->current;

		$this->current = new WP_User( $user_id );

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
			wp_set_current_user( $user_id );
		else
			wp_set_current_user( 0 );

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

		if ( !$user = $this->get_user($username) )
			return false;

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
		if ( !$user = $this->get_user( $user_id ) )
			return false;

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
		$cookie = wp_generate_auth_cookie($user_id, $expiration);
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

		return $wp_hasher->CheckPassword($password, $hash);
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
		$hash = $this->hash_password($password);
		$query = $this->db->prepare("UPDATE {$this->db->users} SET user_pass = %s, user_activation_key = '' WHERE ID = %d", $hash, $user_id);
		$this->db->query($query);
		wp_cache_delete($user_id, 'users');
	}
}

?>
