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

		$this->get_roles();

		if ( empty($this->roles) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ($this->roles as $role => $data) {
			$this->role_objects[$role] = new BP_Role($role, $this->roles[$role]['capabilities'], $this);
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	function get_roles() {
		$this->roles = apply_filters( 'get_roles', array() );
	}

	function add_role($role, $display_name, $capabilities = '') {
		if ( isset($this->roles[$role]) )
			return;

		$this->roles[$role] = array(
			'name' => $display_name,
			'capabilities' => $capabilities);

		$this->role_objects[$role] = new BP_Role($role, $capabilities, $this);
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

	function is_role($role) {
		return isset($this->role_names[$role]);
	}

	function map_meta_cap( $cap, $user_id ) {
		$args = array_slice(func_get_args(), 2);
		return apply_filters( 'map_meta_cap', array( $cap ), $cap, $user_id, $args );
	}
}

class BP_Role {
	var $bp_roles;

	var $name;
	var $capabilities;

	function BP_Role($role, $capabilities, &$bp_roles) {
		$this->bp_roles =& $bp_roles;
		$this->name = $role;
		$this->capabilities = $capabilities;
	}

	function add_cap($cap, $grant = true) {
		$this->capabilities[$cap] = $grant;
		$this->bp_roles->add_cap($this->name, $cap, $grant);
	}

	function remove_cap($cap) {
		unset($this->capabilities[$cap]);
		$this->bp_roles->remove_cap($this->name, $cap);
	}

	function has_cap($cap) {
		$capabilities = apply_filters('role_has_cap', $this->capabilities, $cap, $this->name);
		if ( !empty($capabilities[$cap]) )
			return $capabilities[$cap];
		else
			return false;
	}

}

?>
