<?php

function backpress_get_option( $option )
{
	if ( !class_exists('BP_Options') ) {
		return;
	}

	return BP_Options::get( $option );
}

function backpress_add_option( $option, $value )
{
	if ( !class_exists('BP_Options') ) {
		return;
	}

	return BP_Options::add( $option, $value );
}

function backpress_update_option( $option, $value )
{
	if ( !class_exists('BP_Options') ) {
		return;
	}

	return BP_Options::update( $option, $value );
}

function backpress_delete_option( $option )
{
	if ( !class_exists('BP_Options') ) {
		return;
	}

	return BP_Options::delete( $option );
}