<?php

/**
 * Interface for BP_Options;
 *
 * Must be implemented by the host application for BackPress to operate
 *
 * @package BackPress
 **/
interface BP_Options_Interface
{
	// Returns the prefix to be appended to the beginning of the option key
	function prefix();
	
	// Returns the value of the option
	function get($option);
	
	// Adds an option with given value
	function add($option, $value);
	
	// Updates an existing option with a given value
	function update($option, $value);
	
	// Deletes an existing option
	function delete($option);
} // END interface BB_Options_Interface

?>