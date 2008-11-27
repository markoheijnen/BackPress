<?php
/**
 * BackPress Options API.
 *
 * This is in place for the multiple projects that use BackPress to have options
 * but not rely on the WordPress options API.
 *
 * @since r132
 */

/**
 * Interface for BP_Options;
 *
 * A BP_Options class must be implemented by the host application for
 * BackPress to operate. This interface supplies a boilerplate for that
 * class but can only be implemented in PHP 5 environments.
 *
 * @since r132
 * @package BackPress
 */
interface BP_Options_Interface
{
	/**
	 * Retrieve the prefix to be appended to the beginning of the option key.
	 *
	 * @since r132
	 */
	function prefix();

	/**
	 * Retrieve the value of the option.
	 *
	 * @since r132
	 *
	 * @param string $option Option name.
	 */
	function get($option);

	/**
	 * Adds an option with given value.
	 *
	 * @since r132
	 *
	 * @param string $option Option name.
	 * @param mixed $value Option value.
	 */
	function add($option, $value);

	/**
	 * Updates an existing option with a given value.
	 *
	 * @since r132
	 *
	 * @param string $option Option name.
	 * @param mixed $value Option value.
	 */
	function update($option, $value);

	/**
	 * Deletes an existing option.
	 *
	 * @since r132
	 *
	 * @param string $option Option name.
	 */
	function delete($option);
} // END interface BP_Options_Interface
