<?php

class WP_User {
	var $data;
	var $ID = 0;
	var $id = 0; // Deprecated, use $ID instead.
	var $caps = array();
	var $cap_key;
	var $roles = array();
	var $allcaps = array();

	function WP_User( $id ) {
		global $wp_users_object;

		if ( empty($id) )
			return;

		$this->data = $wp_users_object->get_user( $id );

		if ( !$this->data || empty($this->data->ID) )
			return;

		foreach ( get_object_vars($this->data) as $key => $value )
			$this->{$key} = $value;

		$this->id = $this->ID;
		$this->_init_caps();
	}

	function _init_caps() {
		global $wp_users_object;
		$this->cap_key = $wp_users_object->db->prefix . 'capabilities';
		$this->caps = &$this->{$this->cap_key};
		if ( ! is_array($this->caps) )
			$this->caps = array();
		$this->get_role_caps();
	}

	function get_role_caps() {
		global $wp_roles, $wp_users_object;

		if ( !isset($wp_roles) )
			$wp_roles = new BP_Roles( $wp_users_object->db );

		// Filter out caps that are not role names and assign to $this->roles
		if( is_array($this->caps) )
			$this->roles = array_filter(array_keys($this->caps), array(&$wp_roles, 'is_role'));

		// Build $allcaps from role caps, overlay user's $caps
		$this->allcaps = array();
		foreach( (array) $this->roles as $role) {
			$role = $wp_roles->get_role($role);
			$this->allcaps = array_merge($this->allcaps, $role->capabilities);
		}
		$this->allcaps = array_merge($this->allcaps, $this->caps);
	}

	function add_role($role) {
		$this->caps[$role] = true;
		$this->update_user();
	}

	function remove_role($role) {
		if ( empty($this->roles[$role]) || (count($this->roles) <= 1) )
			return;
		unset($this->caps[$role]);
		$this->update_user();
	}

	function set_role($role) {
		foreach($this->roles as $oldrole)
			unset($this->caps[$oldrole]);
		if ( !empty($role) ) {
			$this->caps[$role] = true;
			$this->roles = array($role => true);
		} else {
			$this->roles = false;
		}
		$this->update_user();
	}

	function update_user() {
		global $wp_users_object;
		$wp_users_object->update_meta( array( 'id' => $this->ID, 'meta_key' => $this->cap_key, 'meta_value' => $this->caps ) );
		$this->get_role_caps();
//		$this->update_user_level_from_caps(); // WP
	}
/*
	function level_reduction($max, $item) {
		if(preg_match('/^level_(10|[0-9])$/i', $item, $matches)) {
			$level = intval($matches[1]);
			return max($max, $level);
		} else {
			return $max;
		}
	}

	function update_user_level_from_caps() {
		global $wp_users_object;
		$this->user_level = array_reduce(array_keys($this->allcaps), 	array(&$this, 'level_reduction'), 0);
		update_usermeta($this->ID, $wpdb->prefix.'user_level', $this->user_level);
	}

	function translate_level_to_cap($level) {
		return 'level_' . $level;
	}
*/

	function add_cap($cap, $grant = true) {
		$this->caps[$cap] = $grant;
		$this->update_user();
	}

	function remove_cap($cap) {
		if ( empty($this->caps[$cap]) ) return;
		unset($this->caps[$cap]);
		$this->update_user();
	}

	function remove_all_caps() {
		global $wp_users_object;
		$this->caps = array();
		$wp_users_object->delete_meta( $this->ID, $this->cap_key );
		$this->get_role_caps();
	}

	//has_cap(capability_or_role_name) or
	//has_cap('edit_post', post_id)
	function has_cap( $cap ) {
		global $wp_roles;
		$args = array_slice(func_get_args(), 1);
		$args = array_merge(array($cap, $this->ID), $args);
		$caps = call_user_func_array( array(&$wp_roles, 'map_meta_cap'), $args);
		// Must have ALL requested caps
		$capabilities = apply_filters('user_has_cap', $this->allcaps, $caps, $args);
		foreach ($caps as $cap) {
			//echo "Checking cap $cap<br />";
			if(empty($capabilities[$cap]) || !$capabilities[$cap])
				return false;
		}

		return true;
	}

}

?>
