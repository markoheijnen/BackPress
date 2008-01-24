<?php

/**
 * Object Cache API
 *
 * @package WordPress
 * @subpackage Cache
 */

/**
 * wp_cache_add() - Adds data to the cache, if the cache key doesn't aleady exist
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::add()
 *
 * @param int|string $key The cache ID to use for retrieval later
 * @param mixed $data The data to add to the cache store
 * @param string $flag The group to add the cache to
 * @param int $expire When the cache data should be expired
 * @return unknown
 */
function wp_cache_add($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->add($key, $data, $flag, $expire);
}

/**
 * wp_cache_close() - Closes the cache
 *
 * This function has ceased to do anything since WordPress 2.5.
 * The functionality was removed along with the rest of the
 * persistant cache.
 *
 * @since 2.0
 *
 * @return bool Always returns True
 */
function wp_cache_close() {
	return true;
}

/**
 * wp_cache_delete() - Removes the cache contents matching ID and flag
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::delete()
 *
 * @param int|string $id What the contents in the cache are called
 * @param string $flag Where the cache contents are grouped
 * @return bool True on successful removal, false on failure
 */
function wp_cache_delete($id, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->delete($id, $flag);
}

/**
 * wp_cache_flush() - Removes all cache items
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::flush()
 *
 * @return bool Always returns true
 */
function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

/**
 * wp_cache_get() - Retrieves the cache contents from the cache by ID and flag
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::get()
 *
 * @param int|string $id What the contents in the cache are called
 * @param string $flag Where the cache contents are grouped
 * @return bool|mixed False on failure to retrieve contents or the cache contents on success
 */
function wp_cache_get($id, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->get($id, $flag);
}

/**
 * wp_cache_init() - Sets up Object Cache Global and assigns it
 *
 * @since 2.0
 * @global WP_Object_Cache $wp_object_cache WordPress Object Cache
 */
function wp_cache_init() {
	$GLOBALS['wp_object_cache'] =& new WP_Object_Cache();
}

/**
 * wp_cache_replace() - Replaces the contents of the cache with new data
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::replace()
 *
 * @param int|string $id What to call the contents in the cache
 * @param mixed $data The contents to store in the cache
 * @param string $flag Where to group the cache contents
 * @param int $expire When to expire the cache contents
 * @return bool False if cache ID and group already exists, true on success
 */
function wp_cache_replace($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->replace($key, $data, $flag, $expire);
}

/**
 * wp_cache_set() - Saves the data to the cache
 *
 * @since 2.0
 * @uses $wp_object_cache Object Cache Class
 * @see WP_Object_Cache::set()
 *
 * @param int|string $id What to call the contents in the cache
 * @param mixed $data The contents to store in the cache
 * @param string $flag Where to group the cache contents
 * @param int $expire When to expire the cache contents
 * @return bool False if cache ID and group already exists, true on success
 */
function wp_cache_set($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->set($key, $data, $flag, $expire);
}

?>
