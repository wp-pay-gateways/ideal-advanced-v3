<?php

namespace Pronamic\WordPress\Pay\Gateways\IDeal_Advanced_V3\XML;

use Directory;
use SimpleXMLElement;

/**
 * Title: iDEAL directory response XML message
 * Description:
 * Copyright: Copyright (c) 2005 - 2018
 * Company: Pronamic
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 */
class DirectoryResponseMessage extends ResponseMessage {
	/**
	 * The document element name
	 *
	 * @var string
	 */
	const NAME = 'DirectoryRes';

	//////////////////////////////////////////////////

	/**
	 * The directory
	 *
	 * @var Directory
	 */
	public $directory;

	//////////////////////////////////////////////////

	/**
	 * Constructs and initialize an directory response message
	 */
	public function __construct() {
		parent::__construct( self::NAME );
	}

	//////////////////////////////////////////////////

	/**
	 * Get the directory
	 *
	 * @return Directory
	 */
	public function get_directory() {
		return $this->directory;
	}

	//////////////////////////////////////////////////

	/**
	 * Parse the specified XML into an directory response message object
	 *
	 * @param SimpleXMLElement $xml
	 *
	 * @return ResponseMessage
	 */
	public static function parse( SimpleXMLElement $xml ) {
		$message = self::parse_create_date( $xml, new self() );

		$message->directory = DirectoryParser::parse( $xml->Directory );

		return $message;
	}
}
