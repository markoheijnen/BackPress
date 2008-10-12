<?php

/**
 * Interface for BP_Options;
 *
 * A BP_Options class must be implemented by the host application for
 * BackPress to operate. This interface supplies a boilerplate for that
 * class but can only be implemented in PHP 5 environments.
 *
 * @package BackPress
 */
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
} // END interface BP_Options_Interface
