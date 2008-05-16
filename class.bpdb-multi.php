<?php
//  backPress Multi DB Class

//  ORIGINAL CODE FROM:
//  Justin Vincent (justin@visunet.ie)
//	http://php.justinvincent.com

require( BACKPRESS_PATH . 'class.bpdb.php' );

class BPDB_Multi extends BPDB {
	var $conn = array();
	var $_force_dbhname = false;
	var $last_table = '';
	var $db_tables = array();
	var $db_servers = array();

	// function BPDB_Multi() {} // Not used - rely on PHP4 constructor from BPDB to call BPDB_Multi::__construct

	function __construct() {
		$args = func_get_args();
		$args = call_user_func_array( array(&$this, '_init'), $args );

		if ( $args['host'] ) {
			$this->db_servers['dbh_global'] = $args;
			$this->db_connect( '/* */' );
		}
	}

	function &db_connect( $query = '' ) {
		$false = false;
		if ( empty( $query ) )
			return $false;
		
		$this->last_table = $table = $this->get_table_from_query( $query );
		
		// We can attempt to force the connection identifier in use
		if ( $this->_force_dbhname )
			$dbhname = $this->_force_dbhname;
		
		if ( isset( $this->db_tables[$table] ) )
			$dbhname = "dbh_{$this->db_tables[$table]}";
		else
			$dbhname = 'dbh_global';

		if ( !isset($this->db_servers[$dbhname]) )
			return $false;

		if ( isset($this->conn[$dbhname]) && is_resource($this->conn[$dbhname]) ) // We're already connected!
			return $this->conn[$dbhname];
		
		$success = $this->db_connect_host( $this->db_servers[$dbhname] );

		if ( $success && is_resource($this->dbh) ) {
			$this->conn[$dbhname] =& $this->dbh;
		} else {
			unset($this->conn[$dbhname]);
			unset($this->dbh);
			return $false;
		}

		return $this->conn[$dbhname];
	}

	function get_table_from_query( $q ) {
		if ( substr( $q, -1 ) == ';' )
			$q = substr( $q, 0, -1 );
		if ( preg_match('/^\s*SELECT.*?\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*UPDATE IGNORE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*UPDATE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*INSERT INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*REPLACE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*INSERT IGNORE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DELETE\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*(?:TRUNCATE|RENAME|OPTIMIZE|LOCK|UNLOCK)\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^SHOW TABLE STATUS (LIKE|FROM) \'?`?(\w+)\'?`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^SHOW INDEX FROM `?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SHOW CREATE TABLE `?(\w+?)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*CREATE\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DROP\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DESCRIBE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SELECT.*?\s+FOUND_ROWS\(\)/is', $q) )
			return $this->last_table;

		return '';
	}

	function add_db_server( $ds, $args = null ) {
		$defaults = array(
			'user' => false,
			'password' => false,
			'name' => false,
			'host' => 'localhost',
			'charset' => false,
			'collate' => false
		);

		$args = wp_parse_args( $args, $defaults );
		$args['ds'] = $ds;

		$this->db_servers[$ds] = $args;
	}

	function add_db_table( $ds, $table ) {
		$this->db_tables[$table] = $ds;
	}
}
