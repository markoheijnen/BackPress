<?php
/**
 * BackPress Options API.
 *
 * This is in place for the multiple projects that use BackPress to have options
 * and transients but not rely on the WordPress options API.
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

/**
 * Interface for BP_Transients;
 *
 * A BP_Transients class must be implemented by the host application for
 * BackPress to operate. This interface supplies a boilerplate for that
 * class but can only be implemented in PHP 5 environments.
 *
 * @since r205
 * @package BackPress
 */
interface BP_Transients_Interface
{
	/**
	 * Retrieve the prefix to be appended to the beginning of the transient key.
	 *
	 * @since r205
	 */
	function prefix();

	/**
	 * Retrieve the value of the transient.
	 *
	 * @since r205
	 *
	 * @param string $transient Transient name.
	 */
	function get( $transient );

	/**
	 * Sets the value of a transient with a given value.
	 *
	 * @since r205
	 *
	 * @param string $transient Transient name.
	 * @param mixed $value Transient value.
	 * @param integer $expiration The time in seconds the transient will be held for. Default is 0, meaning it is held indefinitely.
	 */
	function set( $transient, $value, $expiration = 0 );

	/**
	 * Deletes an existing transient.
	 *
	 * @since r205
	 *
	 * @param string $transient Transient name.
	 */
	function delete( $transient );
} // END Interface BP_Transients