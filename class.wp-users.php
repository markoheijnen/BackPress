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
			'user_status' => 0
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args, EXTR_SKIP );

		$ID = (int) $ID;
	
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

	// $user_id can be user ID#, user_login, user_email (by specifying by = email)
	// TODO: array of ids
	function get_user( $user_id = 0, $args = null ) {
		$defaults = array( 'output' => OBJECT, 'by' => false );
		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

		if ( is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
			if ( 0 === $user = wp_cache_get( $user_id, 'users' ) )
				return new WP_Error( 'invalid-user', __('User does not exist' ) );
			elseif ( $user )
				return $user;
			$sql = "SELECT * FROM {$this->db->users} WHERE ID = %s";
		} elseif ( 'email' == $by ) {
			$user_id = $this->sanitize_email( $user_id );
			if ( 0 === $ID = wp_cache_get( $user_id, 'useremail' ) )
				return new WP_Error( 'invalid-user', __('User does not exist' ) );
			elseif ( $ID ) {
				$args['by'] = false;
				return $this->get_user( $ID, $args );
			}
			$sql = "SELECT * FROM {$this->db->users} WHERE user_email = %s";
		} else {
			$user_id = $this->sanitize_user( $user_id );
			if ( 0 === $ID = wp_cache_get( $user_id, 'userlogins' ) )
				return new WP_Error( 'invalid-user', __('User does not exist' ) );
			elseif ( $ID )
				return $this->get_user( $ID, $args );
			$sql = "SELECT * FROM {$this->db->users} WHERE user_login = %s";
		}

		if ( !$user_id )
			return new WP_Error( 'ID', __('Invalid user id') );

		$user = $this->db->get_row( $this->db->prepare( $sql, $user_id ), $output );

		if ( $user ) {
			if ( is_numeric( $user_id ) ); // [sic]
			elseif ( 'email' == $by )
				wp_cache_add($user_id, $ID, 'useremail');
			else
				wp_cache_add($user_id, $ID, 'userlogins' );
		} else { // Cache non-existant users.
			if ( is_numeric( $user_id ) )
				wp_cache_add($user_id, 0, 'users');
			elseif ( 'email' == $by )
				wp_cache_add($user_id, 0, 'useremail');
			else
				wp_cache_add($user_id, 0, 'userlogins');
			return new WP_Error( 'invalid-user', __('User does not exist' ) );
		}

		// append_meta does the user object caching
		$user = $this->append_meta( $user );

		return $user;
	}

	function delete_user( $user_id ) {
		$user = $this->get_user( $user_id );

		if ( is_wp_error( $user ) )
			return $user;

		do_action( 'pre_' . __FUNCTION__, $user->ID );

		$r = $this->db->query( $this->db->prepare( "DELETE FROM {$this->db->users} WHERE ID = %d", $user->ID ) );
		$this->db->query( $this->db->prepare( "DELETE FROM {$this->db->users} WHERE user_id = %d", $user->ID ) );

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
				wp_cache_add( $i, $trans[$i], $cache_group );
				if ( 'users' == $cache_group ) {
					wp_cache_add( $trans[$i]->user_login, $i, 'userlogins' );
					wp_cache_add( $trans[$i]->user_email, $i, 'useremail' );
				}
			}
			return $object;
		elseif ( $object ) :
			if ( $metas = $backpress->get_results("SELECT meta_key, meta_value FROM $meta_table WHERE $meta_field = '{$object->$id_field}'") )
				foreach ( $metas as $meta ) :
					$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
					if ( strpos($meta->meta_key, $backpress->table_prefix) === 0 )
						$object->{substr($meta->meta_key, strlen($backpress->table_prefix))} = maybe_unserialize( $meta->meta_value );
				endforeach;
			wp_cache_add( $object->$id_field, $object, $cache_group );
			if ( 'users' == $cache_group ) {
				wp_cache_add($object->user_login, $object->ID, 'userlogins');
				wp_cache_add($object->user_email, $object->ID, 'useremail');
			}
			return $object;
		endif;
	}
	
	function update_meta( &$backpress, $args = null ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'cache_group' = 'users' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		if ( is_numeric($id) )
			return false;

		$id = (int) $id;

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
			$backpress->update( $this->db->$meta_table, array( 'meta_value' => $_meta_value ), array( $meta_field => $id, 'meta_key' => $meta_key ) );
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

}

?>
