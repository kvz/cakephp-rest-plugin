<?php
/**
 * Takes the JSON approach to XML.
 * Basic array <> XML conversion.
 * No support for attributes.
 * Uses configurable <item> tag to numerically indexed objects
 *
 * @author kvz
 * @author Jonathan Dalrymple
 */
class BluntXml {
	public $itemTag  = 'item';
	public $rootTag  = 'root';
	public $encoding = 'utf-8';
	public $version  = '1.1';
	public $beautify = true;

	protected $_encodeBuffer = '';

	/**
	 * Decode xml string to multidimensional array
	 *
	 * @param string $xml
	 *
	 * @return array
	 */
	public function decode ($xml) {
		if (!($obj = simplexml_load_string($xml))) {
			return false;
		}

		$array      = $this->_toArray($obj);
		$unitemized = $this->_unitemize($array);

		return $unitemized;
	}

	/**
	 * Encode multidimensional array to xml string
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public function encode ($array, $rootTag = null) {
		if ($rootTag !== null) {
			$this->rootTag = $rootTag;
		}

		$this->_encodeBuffer = '';
		$this->_toXml(array($this->rootTag => $array));

		return $this->_xmlBeautify($this->_encodeBuffer);
	}

	/**
	 * Strips out <item> tags and nests children in sensible places
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	protected function _unitemize ($array) {
		if (!is_array($array)) {
			return $array;
		}
		foreach ($array as $key => $val) {
			if (is_array($val)) {
				$array[$key] = $this->_unitemize($val);
			}

			if ($key === $this->itemTag && is_array($val)) {
				if ($this->_numeric($val)) {
					foreach ($val as $i => $v) {
						$array[] = $v;
					}
				} else {
					$array[] = $val;
				}
				unset($array[$this->itemTag]);
			}
		}
		return $array;
	}

	/**
	 * SimpleXML Object to Array
	 *
	 * @param object $object
	 *
	 * @return array $array
	 */
	protected function _toArray ($object) {
	   $array = array();
	   foreach ((array) $object as $key => $val) {
		   if (is_object($val)) {
			   if (count($val->children()) == 0) {
				   $array[$key] = null;
			   } else {
				   $array[$key] = $this->_toArray($val);
			   }
		   } else if (is_array($val)) {
			   $array[$key] = $this->_toArray($val);
		   } else {
			   $array[$key] = $this->_toArrayValue($val, $key);
		   }
	   }
	   return $array;
	}

	/**
	 * Takes unformatted xml string and beautifies it
	 *
	 * @param string $xml
	 *
	 * @return string
	 */
	protected function _xmlBeautify ($xml) {
		$xml = mb_convert_encoding($xml, 'UTF-8', 'HTML-ENTITIES');
		
		if (!$this->beautify) {
			return $xml;
		}

		// Indentation
		$doc = new DOMDocument($this->version);
		$doc->preserveWhiteSpace = false;
		if (!$doc->loadXML(html_entity_decode($xml))) {
			trigger_error('Invalid XML: ' . $xml, E_USER_ERROR);
		}
		$doc->encoding	 = $this->encoding;
		$doc->formatOutput = true;

		return $doc->saveXML();
	}

	/**
	 * Recusively converts array to xml, itemizes numerically indexes arrays
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	protected function _toXml ($array) {
		if (!is_array($array)) {
			$this->_encodeBuffer .= $this->_toXmlValue($array);
			return;
		}

		foreach ($array as $key => $val) {
			// starting tag
			if (!is_numeric($key)) {
				$this->_encodeBuffer .= sprintf("<%s>", $key);
			}
			// Another array
			if (is_array($val)){
				// Handle non-associative arrays
				if ($this->_numeric($val)) {
					foreach ($val as $item) {
						$tag = $this->itemTag;

						$this->_encodeBuffer .= sprintf("<%s>", $tag);

						$this->_toXml($item);

						$this->_encodeBuffer .= sprintf("</%s>", $tag);
					}
				} else {
					$this->_toXml($val);
				}
			} else {
				$this->_encodeBuffer .= $this->_toXmlValue($val);
			}
			// Draw closing tag
			if (!is_numeric($key)) {
				$this->_encodeBuffer .= sprintf("</%s>", $key);
			}
		}
	}

	protected function _toXmlValue ($data) {
		if ($data === true) {
			return 'TRUE';
		}
		if ($data === false) {
			return 'FALSE';
		}
		if ($data === null) {
			return 'NULL';
		}
		return htmlentities($data, ENT_COMPAT, 'UTF-8');
	}

	protected function _toArrayValue ($data, $key = '') {
		if ($data === 'TRUE') {
			return true;
		}
		if ($data === 'FALSE') {
			return false;
		}
		if ($data === 'NULL') {
			return null;
		}
		return html_entity_decode($data, ENT_COMPAT, 'UTF-8');
	}

	/**
	 * Determines is an array is numerically indexed
	 *
	 * @param array $array
	 *
	 * @return boolean
	 */
	protected function _numeric ($array = array()) {
		if (empty($array)) {
			return null;
		}
		$keys = array_keys($array);
		foreach ($keys as $key) {
			if (!is_numeric($key)) {
				return false;
			}
		}
		return true;
	}
}
