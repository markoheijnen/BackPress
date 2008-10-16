<?php
//  backPress DB Class

//  ORIGINAL CODE FROM:
//  Justin Vincent (justin@visunet.ie)
//	http://php.justinvincent.com

define('EZSQL_VERSION', 'BP1.25');
define('OBJECT', 'OBJECT', true);
define('OBJECT_K', 'OBJECT_K', false);
define('ARRAY_A', 'ARRAY_A', false);
define('ARRAY_K', 'ARRAY_K', false);
define('ARRAY_N', 'ARRAY_N', false);

if ( !defined('SAVEQUERIES') )
	define('SAVEQUERIES', false);

if ( !defined('BPDB__ERROR_STRING') )
	define( 'BPDB__ERROR_STRING', 'DB Error: %s, %s: %s' );
if ( !defined('BPDB__ERROR_HTML') )
	define( 'BPDB__ERROR_HTML', '<div class="error"><p><strong>DB Error in %3$s:</strong> %1$s</p><pre>%2$s</pre></div>' );
if ( !defined('BPDB__CONNECT_ERROR_MESSAGE') )
	define( 'BPDB__CONNECT_ERROR_MESSAGE', 'DB Error: cannot connect' );
if ( !defined('BPDB__SELECT_ERROR_MESSAGE') )
	define( 'BPDB__SELECT_ERROR_MESSAGE', 'DB Error: cannot select' );
if ( !defined( 'BPDB__DB_VERSION_ERROR' ) )
	define( 'BPDB__DB_VERSION_ERROR', 'DB Requires MySQL version 4.0 or higher' );

class BPDB {
	var $show_errors = false;
	var $suppress_errors = false;
	var $last_error = '';
	var $num_queries = 0;
	var $last_query;
	var $col_info;
	var $queries;

	/**                     
	 * Whether to use the query log
	 * @var bool
	 */
	var $save_queries = false;

	var $prefix = '';
	var $ready = false;

	var $dbh = false; // Current connection

	var $tables = array();

	function BPDB() {
		$args = func_get_args();
		register_shutdown_function( array(&$this, '__destruct') );
		return call_user_func_array( array(&$this, '__construct'), $args );
	}

	function __construct() {
		$args = func_get_args();
		$args = call_user_func_array( array(&$this, '_init'), $args );

		$this->db_connect_host( $args );
	}

	function _init( $args ) {
		if ( 4 == func_num_args() )
			$args = array( 'user' => $args, 'password' => func_get_arg(1), 'name' => func_get_arg(2), 'host' => func_get_arg(3) );

		$defaults = array(
			'user' => false,
			'password' => false,
			'name' => false,
			'host' => 'localhost',
			'charset' => false,
			'collate' => false,
			'errors' => false
		);

		$args = wp_parse_args( $args, $defaults );

		switch ( $args['errors'] ) :
		case 'show' :
			$this->show_errors( true );
			break;
		case 'suppress' :
			$this->suppress_errors( true );
			break;
		endswitch;

		return $args;
	}

	function __destruct() {
		return true;
	}

	/**
	 * Figure out which database server should handle the query, and connect to it.
	 * @param string query
	 * @return resource mysql database connection
	 */
	function &db_connect( $query = '' ) {
		$false = false;
		if ( empty( $query ) )
			return $false;
		return $this->dbh;
	}

	/**
	 * Connect to and selects a specific database server 
	 * @param array args
	 *	name => string DB name (required)
	 *	user => string DB user (optional: false)
	 *	password => string DB user password (optional: false)
	 *	host => string DB hostname (optional: 'localhost')
	 *	charset => string DB default charset.  Used in a SET NAMES query. (optional)
	 *	collate => string DB default collation.  If charset supplied, optionally added to the SET NAMES query (optional)
	 * @return void|bool void if cannot connect, false if cannot select, true if success
	 */
	function db_connect_host( $args ) {
		extract( $args, EXTR_SKIP );

		unset($this->dbh); // De-reference before re-assigning
		$this->dbh = mysql_connect($host, $user, $password, true);

		if ( !$this->dbh ) {
			$this->bail( BPDB__CONNECT_ERROR_MESSAGE );
			return;
		}

		$this->ready = true;

		if ( $this->has_cap( 'collation' ) ) {
			$collation_query = '';
			if ( !empty($charset) ) {
				$collation_query = "SET NAMES '{$charset}'";
				if (!empty($collate) )
					$collation_query .= " COLLATE '{$collate}'";
			}
			
			if ( !empty($collation_query) )
				$this->query($collation_query, true);
		}

		return $this->select($name, $this->dbh);
	}

	/**
	 * Sets the prefix of the database tables
	 * @param string prefix
	 * @param false|array tables (optional: false)
	 *	table identifiers are array keys
	 *	array values
	 *		empty: set prefix: array( 'posts' => false, 'users' => false, ... )
	 *		string: set to that array value: array( 'posts' => 'my_posts', 'users' => 'my_users' )
	 *	OR array values (with numeric keys): array( 'posts', 'users', ... )
	 *
	 * @return string the previous prefix (mostly only meaningful if all $table parameter was false)
	 */
	function set_prefix( $prefix, $tables = false ) {
		if ( !$prefix )
			return false;
		if ( preg_match('|[^a-z0-9_]|i', $prefix) )
			return new WP_Error('invalid_db_prefix', 'Invalid database prefix'); // No gettext here

		$old_prefix = $this->prefix;

		if ( $tables && is_array($tables) ) {
			$_tables =& $tables;
		} else {
			$_tables =& $this->tables;
			$this->prefix = $prefix;
		}

		foreach ( $_tables as $key => $value ) {
			if ( is_numeric( $key ) ) { // array( 'posts', 'users', ... )
				$this->$value = $prefix . $value;
			} elseif ( !$value ) {
				$this->$key = $prefix . $key; // array( 'posts' => false, 'users' => false, ... )
			} elseif ( is_string( $value ) ) { // array( 'posts' => 'my_posts', 'users' => 'my_users' )
				$this->$key = $value;
			}
		}

		return $old_prefix;
	}

	/**
	 * Selects a database using the current class's $this->dbh
	 * @param string $db name
	 * @param resource $dbh mysql database resource
	 */
	function select( $db, &$dbh ) {
		if ( !@mysql_select_db($db, $dbh) ) {
			$this->ready = false;
			$this->bail( BPDB__SELECT_ERROR_MESSAGE );
			return false;
		}
		return true;
	}

	/**
	 * Escapes content for insertion into the database, for security
	 *
	 * @param string $string
	 * @return string query safe string
	 */
	function escape($string) {
		return addslashes( $string );
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 * @param string $s
	 */
	function escape_by_ref(&$s) {
		$s = $this->escape($s);
	}

	/**
	 * Escapes array recursively for insertion into the database, for security
	 * @param array $array
	 */
	function escape_deep( $array ) {
		return is_array($array) ? array_map(array(&$this, 'escape_deep'), $array) : $this->escape( $array );
	}

	/**
	 * Prepares a SQL query for safe use, using sprintf() syntax
	 */
	function prepare($args=NULL) {
		if ( NULL === $args )
			return;
		$args = func_get_args();
		$query = array_shift($args);
		$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
		$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
		$query = str_replace('%s', "'%s'", $query); // quote the strings
		array_walk($args, array(&$this, 'escape_by_ref'));
		return @vsprintf($query, $args);
	}

	/**
	 * Get SQL/DB error
	 * @param string $str Error string
	 */
	function get_error( $str = '' ) {
		if ( empty($str) ) {
			if ( $this->last_error )
				$str = $this->last_error;
			else
				return false;
		}

		$caller = $this->get_caller();
		$error_str = sprintf( BPDB__ERROR_STRING, $str, $this->last_query, $caller );

		if ( class_exists( 'WP_Error' ) )
			return new WP_Error( 'db_query', $error_str, array( 'query' => $this->last_query, 'error' => $str, 'caller' => $caller ) );
		else
			return array( 'query' => $this->last_query, 'error' => $str, 'caller' => $caller, 'error_str' => $error_str );
	}

	/**
	 * Print SQL/DB error
	 * @param string $str Error string
	 */
	function print_error($str = '') {
		if ( $this->suppress_errors )
			return false;

		$error = $this->get_error( $str );
		if ( is_object( $error ) && is_a( $error, 'WP_Error' ) ) {
			$err = $error->get_error_data();
			$err['error_str'] = $error->get_error_message();
		} else {
			$err =& $error;
		}

		$log_file = ini_get('error_log');
		if ( !empty($log_file) && ('syslog' != $log_file) && is_writable($log_file) && function_exists( 'error_log' ) )
			error_log($err['error_str'], 0);

		// Is error output turned on or not
		if ( !$this->show_errors )
			return false;

		$str = htmlspecialchars($err['error'], ENT_QUOTES);
		$query = htmlspecialchars($err['query'], ENT_QUOTES);
		$caller = htmlspecialchars($err['caller'], ENT_QUOTES);

		// If there is an error then take note of it

		printf( BPDB__ERROR_HTML, $str, $query, $caller );
	}

	/**
	 * Turn error output on or off
	 * @param bool $show
	 * @return bool previous setting
	 */
	function show_errors( $show = true ) {
		$errors = $this->show_errors;
		$this->show_errors = $show;
		return $errors;
	}

	/**
	 * Turn error output off
	 * @return bool previous setting of show_errors
	 */
	function hide_errors() {
		return $this->show_errors( false );
	}

	/**
	 * Turn error logging on or off
	 * @param bool $suppress
	 * @return bool previous setting
	 */
	function suppress_errors( $suppress = true ) {
		$errors = $this->suppress_errors;
		$this->suppress_errors = $suppress;
		return $errors;
	}

	/**
	 * Kill cached query results
	 */
	function flush() {
		$this->last_result = array();
		$this->col_info = null;
		$this->last_query = null;
		$this->last_error = '';
		$this->num_rows = 0;
	}

	/**
	 * Basic query. See docs for more details.
	 * @param string $query
	 * @return int number of rows
	 */
	function query($query, $use_current = false) {
		if ( ! $this->ready )
			return false;

		// filter the query, if filters are available
		// NOTE: some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
		if ( function_exists('apply_filters') )
			$query = apply_filters('query', $query);

		// initialise return
		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		// Perform the query via std mysql_query function..
		if (SAVEQUERIES)
			$this->timer_start();

		if ( $use_current )
			$dbh =& $this->dbh;
		else
			$dbh = $this->db_connect( $query );

		$this->result = @mysql_query($query, $dbh);
		++$this->num_queries;

		if (SAVEQUERIES)
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );

		// If there is an error then take note of it..
		if ( $this->last_error = mysql_error($dbh) ) {
			return $this->print_error($this->last_error);
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace|alter) /i",$query) ) {
			$this->rows_affected = mysql_affected_rows($dbh);
			// Take note of the insert_id
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$this->insert_id = mysql_insert_id($dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ($i < @mysql_num_fields($this->result)) {
				$this->col_info[$i] = @mysql_fetch_field($this->result);
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mysql_fetch_object($this->result) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysql_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;

			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
	}

	/**
	 * Insert an array of data into a table
	 * @param string $table WARNING: not sanitized!
	 * @param array $data should not already be SQL-escaped
	 * @return mixed results of $this->query()
	 */
	function insert($table, $data) {
		$data = $this->escape_deep($data);
		$fields = array_keys($data);
		return $this->query("INSERT INTO $table (`" . implode('`,`',$fields) . "`) VALUES ('".implode("','",$data)."')");
	}

	/**
	 * Update a row in the table with an array of data
	 * @param string $table WARNING: not sanitized!
	 * @param array $data should not already be SQL-escaped
	 * @param array $where a named array of WHERE column => value relationships.  Multiple member pairs will be joined with ANDs.  WARNING: the column names are not currently sanitized!
	 * @return mixed results of $this->query()
	 */
	function update($table, $data, $where){
		$data = $this->escape_deep($data);
		$bits = $wheres = array();
		foreach ( array_keys($data) as $k )
			$bits[] = "`$k` = '$data[$k]'";

		if ( is_array( $where ) )
			foreach ( $where as $c => $v )
				$wheres[] = "$c = '" . $this->escape( $v ) . "'";
		else
			return false;
		return $this->query( "UPDATE $table SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres ) );
	}

	/**
	 * Get one variable from the database
	 * @param string $query (can be null as well, for caching, see codex)
	 * @param int $x = 0 row num to return
	 * @param int $y = 0 col num to return
	 * @return mixed results
	 */
	function get_var($query=null, $x = 0, $y = 0) {
		$this->func_call = "\$db->get_var(\"$query\",$x,$y)";
		if ( $query )
			$this->query($query);

		// Extract var out of cached results based x,y vals
		if ( !empty( $this->last_result[$y] ) ) {
			$values = array_values(get_object_vars($this->last_result[$y]));
		}

		// If there is a value return it else return null
		return (isset($values[$x]) && $values[$x]!=='') ? $values[$x] : null;
	}

	/**
	 * Get one row from the database
	 * @param string $query
	 * @param string $output ARRAY_A | ARRAY_N | OBJECT
	 * @param int $y row num to return
	 * @return mixed results
	 */
	function get_row($query = null, $output = OBJECT, $y = 0) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->query($query);
		else
			return null;

		if ( !isset($this->last_result[$y]) )
			return null;

		if ( $output == OBJECT ) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
		} else {
			$this->print_error(" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
		}
	}

	/**
	 * Gets one column from the database
	 * @param string $query (can be null as well, for caching, see codex)
	 * @param int $x col num to return
	 * @return array results
	 */
	function get_col($query = null , $x = 0) {
		if ( $query )
			$this->query($query);

		$new_array = array();
		// Extract the column values
		for ( $i=0; $i < count($this->last_result); $i++ ) {
			$new_array[$i] = $this->get_var(null, $x, $i);
		}
		return $new_array;
	}

	/**
	 * Return an entire result set from the database
	 * @param string $query (can also be null to pull from the cache)
	 * @param string $output ARRAY_A | ARRAY_N | OBJECT_K | OBJECT
	 * @return mixed results
	 */
	function get_results($query = null, $output = OBJECT) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->query($query);
		else
			return null;

		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ( $output == OBJECT_K || $output == ARRAY_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			$key = $this->col_info[0]->name;
			foreach ( $this->last_result as $row )
				if ( !isset( $new_array[ $row->$key ] ) )
					$new_array[ $row->$key ] = $row;
			if ( $output == ARRAY_K )
				return array_map('get_object_vars', $new_array);
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->last_result ) {
				$i = 0;
				foreach( $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[$i] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[$i] = get_object_vars( $row );
					}
					++$i;
				}
				return $new_array;
			}
		}
	}

	/**
	 * Grabs column metadata from the last query
	 * @param string $info_type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
	 * @param int $col_offset 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
	 * @return mixed results
	 */
	function get_col_info($info_type = 'name', $col_offset = -1) {
		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
				$i = 0;
				foreach($this->col_info as $col ) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}

	/**
	 * Starts the timer, for debugging purposes
	 */
	function timer_start() {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$this->time_start = $mtime[1] + $mtime[0];
		return true;
	}

	/**
	 * Stops the debugging timer
	 * @return int total time spent on the query, in milliseconds
	 */
	function timer_stop() {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$time_end = $mtime[1] + $mtime[0];
		$time_total = $time_end - $this->time_start;
		return $time_total;
	}

	/**
	 * Wraps fatal errors in a nice header and footer and dies.
	 * @param string $message
	 */
	function bail($message) { // Just wraps errors in a nice header and footer
		if ( !$this->show_errors ) {
			if ( class_exists('WP_Error') )
				$this->error = new WP_Error('500', $message);
			else
				$this->error = $message;
			return false;
		}
		wp_die($message);
	}

	/**
	 * Checks wether of not the database version is high enough to support the features WordPress uses
	 * @global $wp_version
	 */
	function check_database_version( $dbh_or_table = false ) {
		// Make sure the server has MySQL 4.0
		$mysql_version = preg_replace( '|[^0-9\.]|', '', $this->db_version( $dbh_or_table ) );
		if ( version_compare($mysql_version, '4.0.0', '<') )
			return new WP_Error( 'database_version', BPDB__DB_VERSION_ERROR );
	}

	/**
	 * This function is called when WordPress is generating the table schema to determine wether or not the current database
	 * supports or needs the collation statements.
	 * @return bool
	 */
	function supports_collation() {
		return $this->has_cap( 'collation' );
	}

	/**
	 * Generic function to determine if a database supports a particular feature
	 * @param string $db_cap the feature
	 * @param false|string|resource $dbh_or_table the databaese (the current database, the database housing the specified table, or the database of the mysql resource)
	 * @return bool
	 */
	function has_cap( $db_cap, $dbh_or_table = false ) {
		$version = $this->db_version( $dbh_or_table );

		switch ( strtolower( $db_cap ) ) :
		case 'collation' :
		case 'group_concat' :
		case 'subqueries' :
			return version_compare($version, '4.1', '>=');
			break;
		endswitch;

		return false;
	}

	/**
	 * The database version number
	 * @param false|string|resource $dbh_or_table the databaese (the current database, the database housing the specified table, or the database of the mysql resource)
	 * @return false|string false on failure, version number on success
	 */
	function db_version( $dbh_or_table = false ) {
		if ( !$dbh_or_table )
			$dbh =& $this->dbh;
		elseif ( is_resource( $dbh_or_table ) )
			$dbh =& $dbh_or_table;
		else
			$dbh = $this->db_connect( "DESCRIBE $dbh_or_table" );

		if ( $dbh )
			return mysql_get_server_info( $dbh );
		return false;
	}

	/**
	 * Get the name of the function that called wpdb.
	 * @return string the name of the calling function
	 */
	function get_caller() {
		$caller = '[unavailable]';
		// requires PHP 4.3+
		if ( !is_callable('debug_backtrace') )
			return $caller;

		$bt = debug_backtrace();

		$intermediates = array( 'call_user_func_array', 'call_user_func', 'apply_filters', 'do_action', 'do_action_ref_array' );

		foreach ( $bt as $trace ) {
			if ( isset($trace['class']) && is_a( $this, $trace['class'] ) )
				continue;
			elseif ( !isset($trace['function']) )
				continue;
			elseif ( in_array( strtolower(@$trace['function']), $intermediates ) )
				continue;

			if ( isset($trace['class']) )
				$caller = $trace['class'] . '::' . $trace['function'];
			else
				$caller = $trace['function'];
			break;
		}
		return $caller;
	}
}
