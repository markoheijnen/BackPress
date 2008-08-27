<?php

class WP_Users {
	var $db;

	function WP_Users( &$db ) {
		$this->__construct( $db );
//		register_shutdown_function( array(&$this, '__destruct') );
	}

	function __construct( &$db ) {
		$this->db =& $db;
	}

//	function __destruct() {
//	}

	function _put_user( $args = null ) {
		$defaults = array(
			'ID' => false,
			'user_login' => '',
			'user_nicename' => '',
			'user_email' => '',
			'user_url' => '',
			'user_pass' => false,
			'user_registered' => time(),
			'display_name' => '',
			'user_status' => 0,
			'strict_user_login' => false
		);

		$fields = array_keys( wp_parse_args( $args ) );
		$args = wp_parse_args( $args, $defaults );
		unset($defaults['strict_user_login']);

		if ( isset($args['ID']) && $args['ID'] ) {
			unset($defaults['ID']);
			$fields = array_intersect( $fields, array_keys( $defaults ) );
		} else {
			$fields = array_keys( $defaults );
		}

		extract( $args, EXTR_SKIP );

		$ID = (int) $ID;

		if ( !$ID || in_array( 'user_login', $fields ) ) {
			$user_login = $this->sanitize_user( $user_login, $strict_user_login );

			if ( !$user_login )
				return new WP_Error( 'user_login', __('Invalid login name') );
			if ( !$ID && $this->get_user( $user_login ) )
				return new WP_Error( 'user_login', __('Name already exists') );
		}

		if ( !$ID || in_array( 'user_nicename', $fields ) ) {
			if ( !$user_nicename = $this->sanitize_nicename( $user_nicename ? $user_nicename : $user_login ) )
				return new WP_Error( 'user_nicename', __('Invalid nicename') );
			if ( !$ID && $this->get_user( $user_nicename, array( 'by' => 'nicename' ) ) )
				return new WP_Error( 'user_nicename', __('Nicename already exists') );
		}

		if ( !$ID || in_array( 'user_email', $fields ) ) {
			if ( !$this->is_email( $user_email ) )
				return new WP_Error( 'user_email', __('Invalid email address') );

			if ( $already_email = $this->get_user( $user_email, array( 'by' => 'email' ) ) ) {
				// if new user, or if multiple users with that email, or if only one user with that email, but it's not the user being updated
				if ( !$ID || is_wp_error( $already_email ) || $already_email->ID != $ID )
					return new WP_Error( 'user_email', __('Email already exists') );
			}
		}

		if ( !$ID || in_array( 'user_url', $fields ) ) {
			$user_url = clean_url( $user_url );
		}

		if ( !$ID || in_array( 'user_pass', $fields ) ) {
			if ( !$user_pass )
				$user_pass = WP_Pass::generate_password();
			$plain_pass = $user_pass;
			$user_pass  = WP_Pass::hash_password( $user_pass );
		}

		if ( !$ID || in_array( 'user_registered', $fields ) ) {
			if ( !is_numeric($user_registered) )
				$user_registered = backpress_gmt_strtotime( $user_registered );

			if ( !$user_registered || $user_registered < 0 )
				return new WP_Error( 'user_registered', __('Invalid registration time') );

			if ( !$user_registered = @gmdate('Y-m-d H:i:s', $user_registered) )
				return new WP_Error( 'user_registered', __('Invalid registration timestamp') );
		}

		if ( !$ID || in_array( 'user_display', $fields ) ) {
			if ( !$display_name )
				$display_name = $user_login;
		}

		$db_return = NULL;
		if ( $ID ) {
			$db_return = $this->db->update( $this->db->users, compact( $fields ), compact('ID') );
		} else {
			$db_return = $this->db->insert( $this->db->users, compact( $fields ) );
			$ID = $this->db->insert_id;
		}

		if ( !$db_return )
			return new WP_Error( 'WP_Users::_put_user', __('Query failed') );

		// Cache the result
		if ( $ID ) {
			$this->append_meta( (object) compact( $fields ) );
		} else {
			$this->get_user( $ID, array( 'from_cache' => false ) );
		}

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

		$args['output'] = OBJECT;
		$user = $this->get_user( $ID, $args );
		if ( !$user || is_wp_error( $user ) )
			return $user;

		$args['ID'] = $user->ID;

		$r = $this->_put_user( $args );

		if ( is_wp_error($r) )
			return $r;

		do_action( __FUNCTION__, $r, $args );

		return $r;
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
		$user = $this->get_user( $user_id );
		if ( !$user || is_wp_error( $user ) )
			return $user;

		$user_id = $user->ID;
		$hash = WP_Pass::hash_password($password);
		$this->update_user( $user->ID, array( 'user_pass' => $password ) );
	}

	// $user_id can be user ID#, user_login, user_email (by specifying by = email)
	function get_user( $user_id = 0, $args = null ) {
		$defaults = array( 'output' => OBJECT, 'by' => false, 'from_cache' => true );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( is_array( $user_id ) ) {
			$users = array();
			foreach ( $user_id as $the_id ) {
				$user = $this->get_user( $the_id, $args );
				if ( !is_wp_error($user) )
					$users[] = $user;
			}

			// append_meta does the user object, useremail, userlogins caching
			$users = $this->append_meta( $users );

			backpress_convert_object( $users, $output );
			return $users;
		}

		if ( is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
			if ( $from_cache ) {
				if ( 0 === $user = wp_cache_get( $user_id, 'users' ) ) {
					return false;
				} elseif ( $user ) {
					backpress_convert_object( $user, $output );
					return $user;
				}
			}
			$sql_field = 'ID';
		} elseif ( 'email' == $by ) {
			if ( !$this->is_email( $user_id ) )
				return false;
			if ( $from_cache ) {
				if ( 0 === $ID = wp_cache_get( $user_id, 'useremail' ) )
					return false;
				elseif ( $ID ) {
					$args['by'] = false;
					return $this->get_user( $ID, $args );
				}
			}
			$sql_field = 'user_email';
		} elseif ( 'nicename' == $by ) { // No cache?
			$user_id = $this->sanitize_nicename( $user_id );
			$sql_field = 'user_nicename';
		} else {
			$user_id = $this->sanitize_user( $user_id );
			if ( $from_cache ) {
				if ( 0 === $ID = wp_cache_get( $user_id, 'userlogins' ) )
					return false;
				elseif ( $ID )
					return $this->get_user( $ID, $args );
			}
			$sql_field = 'user_login';
		}

		if ( !$user_id )
			return false;

		$sql = "SELECT * FROM {$this->db->users} WHERE $sql_field = %s"; // ID is already (int)ed
		$user = $this->db->get_row( $this->db->prepare( $sql, $user_id ) );

		if ( 1 < $this->db->num_rows ) {
			if ( 'user_email' == $sql_field )
				$err = __( 'Multiple email matches.  Log in with your username.' );
			else
				$err = sprintf( __( 'Multiple %s matches' ), $sql_field );
			return new WP_Error( $sql_field, $err, $args + array( 'user_id' => $user_id, 'unique' => false ) );
		}

		if ( !$user ) { // Cache non-existant users.
			if ( is_numeric( $user_id ) )
				wp_cache_add($user_id, 0, 'users');
			elseif ( 'email' == $by )
				wp_cache_add($user_id, 0, 'useremail');
			else
				wp_cache_add($user_id, 0, 'userlogins');
			return false;
		}

		// append_meta does the user object, useremail, userlogins caching
		$user = $this->append_meta( $user );

		backpress_convert_object( $user, $output );
		return $user;
	}

	function delete_user( $user_id ) {
		$user = $this->get_user( $user_id );

		if ( !$user || is_wp_error( $user ) )
			return $user;

		do_action( 'pre_' . __FUNCTION__, $user->ID );

		$r = $this->db->query( $this->db->prepare( "DELETE FROM {$this->db->users} WHERE ID = %d", $user->ID ) );
		$this->db->query( $this->db->prepare( "DELETE FROM {$this->db->usermeta} WHERE user_id = %d", $user->ID ) );

		wp_cache_delete( $user->ID, 'users' );
		wp_cache_delete( $user->user_email, 'useremail' );
		wp_cache_delete( $user->user_login, 'userlogins' );

		do_action( __FUNCTION__, $user->ID );

		return $r;
	}

	// Used for user meta, but can be used for other meta data (such as bbPress' topic meta)
	// Should this be in the class or should it be it's own special function?
	function append_meta( $object, $args = null ) {
		$defaults = array( 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'id_field' => 'ID', 'cache_group' => 'users' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( is_array($object) ) {
			$trans = array();
			foreach ( array_keys($object) as $i )
				$trans[$object[$i]->$id_field] =& $object[$i];
			$ids = join(',', array_keys($trans));
			if ( $metas = $this->db->get_results("SELECT $meta_field, meta_key, meta_value FROM {$this->db->$meta_table} WHERE $meta_field IN ($ids) /* WP_Users::append_meta */") ) {
				usort( $metas, array(&$this, '_append_meta_sort') );
				foreach ( $metas as $meta ) {
					$trans[$meta->$meta_field]->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
					if ( strpos($meta->meta_key, $this->db->prefix) === 0 )
						$trans[$meta->$meta_field]->{substr($meta->meta_key, strlen($this->db->prefix))} = maybe_unserialize( $meta->meta_value );
				}
			}
			foreach ( array_keys($trans) as $i ) {
				wp_cache_add( $i, $trans[$i], $cache_group );
				if ( 'users' == $cache_group ) {
					wp_cache_add( $trans[$i]->user_login, $i, 'userlogins' );
					wp_cache_add( $trans[$i]->user_email, $i, 'useremail' );
				}
			}
			return $object;
		} elseif ( $object ) {
			if ( $metas = $this->db->get_results("SELECT meta_key, meta_value FROM {$this->db->$meta_table} WHERE $meta_field = '{$object->$id_field}' /* WP_Users::append_meta */") ) {
				usort( $metas, array(&$this, '_append_meta_sort') );
				foreach ( $metas as $meta ) {
					$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
					if ( strpos($meta->meta_key, $this->db->prefix) === 0 )
						$object->{substr($meta->meta_key, strlen($this->db->prefix))} = maybe_unserialize( $meta->meta_value );
				}
			}
			wp_cache_add( $object->$id_field, $object, $cache_group );
			if ( 'users' == $cache_group ) {
				wp_cache_add($object->user_login, $object->ID, 'userlogins');
				wp_cache_add($object->user_email, $object->ID, 'useremail');
			}
			return $object;
		}
	}
	
	/** 
	 * _append_meta_sort() - sorts meta keys by length to ensure $appended_object->{$bbdb->prefix}key overwrites $appended_object->key as desired
	 *
	 * @internal
	 */
	function _append_meta_sort( $a, $b ) {
		return strlen( $a->meta_key ) - strlen( $b->meta_key );
	}

	function update_meta( $args = null ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'cache_group' => 'users' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		$user = $this->get_user( $id );
		if ( !$user || is_wp_error($user) )
			return $user;

		$id = (int) $user->ID;

		if ( is_null($meta_key) || is_null($meta_value) )
			return false;

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
			$this->db->update( $this->db->$meta_table, array( 'meta_value' => $_meta_value ), array( $meta_field => $id, 'meta_key' => $meta_key ) );
		}


		wp_cache_delete( $id, $cache_group );

		return true;
	}

	function delete_meta( $args = null ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'meta_id_field' => 'umeta_id', 'cache_group' => 'users' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( is_numeric($id) )
			return false;

		$id = (int) $id;

		if ( is_null($meta_key) )
			return false;

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

		wp_cache_delete( $id, $cache_group );

		return true;
	}

	function sanitize_user( $user_login, $strict = false ) {
		return sanitize_user( $user_login, $strict );
	}

	function sanitize_nicename( $slug ) {
		return sanitize_title( $slug );
	}

	function is_email( $email ) {
		return is_email( $email );
	}

}

?>
