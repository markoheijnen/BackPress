<?php

class WP_Ajax_Response {
	var $responses = array();

	function WP_Ajax_Response( $args = '' ) {
		if ( !empty($args) )
			$this->add($args);
	}

	// a WP_Error object can be passed in 'id' or 'data'
	function add( $args = '' ) {
		$defaults = array(
			'what' => 'object', 'action' => false,
			'id' => '0', 'old_id' => false,
			'position' => 1, // -1 = top, 1 = bottom, html ID = after, -html ID = before
			'data' => '', 'supplemental' => array()
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );
		$position = preg_replace( '/[^a-z0-9:_-]/i', '', $position );

		if ( is_wp_error($id) ) {
			$data = $id;
			$id = 0;
		}

		$response = '';
		if ( is_wp_error($data) ) {
			foreach ( $data->get_error_codes() as $code ) {
				$response .= "<wp_error code='$code'><![CDATA[" . $data->get_error_message($code) . "]]></wp_error>";
				if ( !$error_data = $data->get_error_data($code) )
					continue;
				$class = '';
				if ( is_object($error_data) ) {
					$class = ' class="' . get_class($error_data) . '"';
					$error_data = get_object_vars($error_data);
				}

				$response .= "<wp_error_data code='$code'$class>";

				if ( is_scalar($error_data) ) {
					$response .= "<![CDATA[$error_data]]>";
				} elseif ( is_array($error_data) ) {
					foreach ( $error_data as $k => $v )
						$response .= "<$k><![CDATA[$v]]></$k>";
				}

				$response .= "</wp_error_data>";
			}
		} else {
			$response = "<response_data><![CDATA[$data]]></response_data>";
		}

		$s = '';
		if ( (array) $supplemental ) {
			foreach ( $supplemental as $k => $v )
				$s .= "<$k><![CDATA[$v]]></$k>";
			$s = "<supplemental>$s</supplemental>";
		}

		if ( false === $action )
			$action = $_POST['action'];

		$x = '';
		$x .= "<response action='{$action}_$id'>"; // The action attribute in the xml output is formatted like a nonce action
		$x .=	"<$what id='$id' " . ( false === $old_id ? '' : "old_id='$old_id' " ) . "position='$position'>";
		$x .=		$response;
		$x .=		$s;
		$x .=	"</$what>";
		$x .= "</response>";

		$this->responses[] = $x;
		return $x;
	}

	function send() {
		header('Content-Type: text/xml');
		echo "<?xml version='1.0' standalone='yes'?><wp_ajax>";
		foreach ( $this->responses as $response )
			echo $response;
		echo '</wp_ajax>';
		die();
	}
}

?>
