<?php

class BP_Roles {
	var $db;

	var $roles;

	var $role_objects = array();
	var $role_names = array();
	var $role_key;
	var $use_db = true;

	function BP_Roles( &$db ) {
		$this->__construct();
	}

	function __construct() {
		$this->db =& $db;
		$this->role_key =& $this->db->prefix;

		$this->default_roles();

		if ( empty($this->roles) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ($this->roles as $role => $data) {
			$this->role_objects[$role] = new WP_Role($role, $this->roles[$role]['capabilities']);
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	function default_roles() {
		do_action_ref_array( 'bp_default_roles', array(&$this) );
	}

	function add_role($role, $display_name, $capabilities = '') {
		if ( isset($this->roles[$role]) )
			return;

		$this->roles[$role] = array(
			'name' => $display_name,
			'capabilities' => $capabilities);

		$this->role_objects[$role] = new BP_Role($role, $capabilities);
		$this->role_names[$role] = $display_name;
		return $this->role_objects[$role];
	}

	function remove_role($role) {
		if ( ! isset($this->role_objects[$role]) )
			return;

		unset($this->role_objects[$role]);
		unset($this->role_names[$role]);
		unset($this->roles[$role]);
	}

	function add_cap($role, $cap, $grant = true) {
		$this->roles[$role]['capabilities'][$cap] = $grant;
	}

	function remove_cap($role, $cap) {
		unset($this->roles[$role]['capabilities'][$cap]);
	}

	function &get_role($role) {
		if ( isset($this->role_objects[$role]) )
			return $this->role_objects[$role];
		else
			return null;
	}

	function get_names() {
		return $this->role_names;
	}

	function is_role($role)
	{
		return isset($this->role_names[$role]);
	}
}

class BP_Role {
	var $name;
	var $capabilities;

	function BP_Role($role, $capabilities) {
		$this->name = $role;
		$this->capabilities = $capabilities;
	}

	function add_cap($cap, $grant = true) {
		global $wp_roles;

		$this->capabilities[$cap] = $grant;
		$wp_roles->add_cap($this->name, $cap, $grant);
	}

	function remove_cap($cap) {
		global $wp_roles;

		unset($this->capabilities[$cap]);
		$wp_roles->remove_cap($this->name, $cap);
	}

	function has_cap($cap) {
		$capabilities = apply_filters('role_has_cap', $this->capabilities, $cap, $this->name);
		if ( !empty($capabilities[$cap]) )
			return $capabilities[$cap];
		else
			return false;
	}

	function map_meta_cap( $cap, $user_id ) {
		$args = array_slice(func_get_args(), 2);
		return apply_filters( 'map_meta_cap', arra(), $cap, $user_id, $args );
	}
}

?>
