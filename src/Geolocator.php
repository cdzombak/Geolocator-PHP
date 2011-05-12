<?php

require_once('GeolocatorException.php');
require_once('Location.php');

/**
 * @mainpage
 * @author  Chris Dzombak
 * @version 
 * @date    2011-05-11
 * 
 * The Geolocator class is very versatile. It provides several ways to retrieve
 * locations for one or many IPs/domains; you can use whatever's easiest for your
 * application.
 * 
 * Note that "IP", used in these docs and in these API methods, really means
 * "IP or Domain".
 * 
 * It can be used easily to get locations for one or multiple IPs. See the
 * generated API docs for complete documentation on all methods.
 *
 * General guide to using the class:
 * 1. Create the Geolocator object. Set the API key in the constructor.
 * 2. Add one or multiple IPs/domains.
 * 3. Access the results.
 *
 * Geolocator allows some array and iterator-like behavior for ease and
 * simplicity of use (when adding/looking up multiple IPs).
 *
 * All (non-getter) methods are chainable, meaning you can do things like:
 * $location = Geolocator::instantiate($apiKey, $myIp)->setPrecision(Geolocator::PRECISION_COUNTRY)->getLocation();
 *
 * See the examples directory included in this distribution for examples of
 * using the features of this class, and see the README.md file for an overview
 * of features.
 *
 * @section ABOUT
 *
 * This class requires PHP 5.3.0 or better, with JSON and cURL support.
 * 
 * The website for this project is <https://github.com/cdzombak/Geolocator-PHP>
 * 
 * @section LICENSE
 *
 * This software is copyright 2011 Chris Dzombak. This software is dual-licensed under
 * the MIT license and the GPLv3.
 * 
 * For additional info, see <https://github.com/cdzombak/Geolocator-PHP>.
 */

// NOTE: ArrayObject behavior is mostly broken in v3.0-beta-1.
// Will be completed before v3.0-beta-2.

class Geolocator extends ArrayObject {
	
	const CONNECT_TIMEOUT      = -1;  /**< Identifies a connect timeout. @see Geolocator::setTimeout() */
	const TRANSFER_TIMEOUT     = -2;  /**< Identifies a transfer timeout @see Geolocator::setTimeout() */
	
	const PRECISION_CITY       = 1;  /**< @see Geolocator::setPrecision() */
	const PRECISION_COUNTRY    = 2;  /**< @see Geolocator::setPrecision() */
	
	protected static $endpoints = array(
		self::PRECISION_CITY    => 'http://api.ipinfodb.com/v3/ip-city/',
		self::PRECISION_COUNTRY => 'http://api.ipinfodb.com/v3/ip-country/'
	);
	
	protected $iterCurrentKey  = 0;
	protected $connectTimeout  = 4;
	protected $transferTimeout = 4;
	protected $precision       = self::PRECISION_CITY;
	protected $apiKey          = NULL;
	protected $geodata         = array();  /**< IP addresses represented by the object. Indexed by IP. 
	                                            Each entry should have 2 keys: dataGood, location */
	private $curl              = NULL;
	
	
	/**
	 * Constructs a Geolocator with the given API key.
	 * 
	 * If $_ips is set, adds one or many IPs to the Geolocator's list
	 * of IPs to look up.
	 *
	 * Not chainable; for a chainable construction method, use the static
	 * instantiate() method.
	 *
	 * @param string $_apiKey your API key
	 * @param array $_ips IP(s) to add to the Geolocator.
	 */
	function __construct($_apiKey, $_ips=NULL) {
		$this->apiKey = $_apiKey;
		
		if (is_array($_ips)) {
			foreach ($_ips as $ip) {
				$this->addIp($ip);
			}
		} else  if (is_string($_ips)) {
			$this->addIp($_ips);
		}
		
		$this->rewind();
	}

	/**
	 * Returns a Geolocator initialized with the given API key and, if specified,
	 * the given IPs. (See the Geolocator constructor.)
	 *
	 * This method is chainable.
	 *
	 * @param string $_apiKey
	 * @param array|string $_ips
	 * @return Geolocator
	 */
	static public function instantiate($_apiKey, $_ips=NULL) {
		return new Geolocator($_apiKey, $_ips);
	}
	
	/**
	 * Adds an IP to the Geolocator.
	 *
	 * Chainable.
	 *
	 * @param string $ip IP or domain to add
	 * @return Geolocator $this
	 */ 
	public function addIp($ip) {
		$ip = $this->cleanIpInput($ip);
		
		$this->geodata[$ip] = array(
			'dataGood' => false,
			'location' => NULL
		);
		
		return $this;
	}
	
	/**
	 * Removes an IP from the Geolocator.
	 *
	 * Chainable.
	 *
	 * @param string $ip the IP/domain to remove
	 * @return Geolocator $this
	 */
	public function removeIp($ip) {
		$ip = $this->cleanIpInput($ip);
		unset($this->geodata[$ip]);
		
		return $this;
	}
	
	/**
	 * Sets the desired connect or transfer timeout.
	 * Defaults are 4s for connect, 4s for transfer.
	 * 
	 * This can only be changed before you get the first result from the Geolocator.
	 * (After the first result, the cURL connection is cached and reused for performance.)
	 *
	 * Chainable.
	 *
	 * @param int $timeoutType one of Geolocator::CONNECT_TIMEOUT , Geolocator::TRANSFER_TIMEOUT
	 * @param int $time timeout value
	 * @throws GeolocatorException
	 * @return Geolocator $this
	 */
	public function setTimeout($timeoutType, $time) {
		if (!is_numeric($time) || $time < 0) {
			throw new GeolocatorException ('Invalid time specified.');
		}
		if ($timeoutType == self::CONNECT_TIMEOUT) {
			$this->connectTimeout = $time;
		} else if ($timeoutType == self::TRANSFER_TIMEOUT) {
			$this->transferTimeout = $time;
		} else {
			throw new GeolocatorException ('Invalid timeout type specified.');
		}
		
		return $this;
	}
	
	/**
	 * Sets the desired lookup precision.
	 * Default is city precision.
	 *
	 * Chainable.
	 *
	 * @param int $precision one of Geolocator::PRECISION_CITY , Geolocator::PRECISION_COUNTRY
	 * @throws GeolocatorException
	 * @return Geolocator $this
	 */
	public function setPrecision($precision) {
		if ($precision == self::PRECISION_CITY || $precision == self::PRECISION_COUNTRY) {
			if ($this->precision == self::PRECISION_COUNTRY && $precision == self::PRECISION_CITY) {
				foreach($this->geodata as &$data) {
					$data['dataGood'] = false;
				}
			}
			$this->precision = $precision;
		} else {
			throw new GeolocatorException ('Invalid precision specified.');
		}

		return $this;
	}
	
	/**
	 * Gets the IPs/domains represented by the Geolocator.
	 *
	 * Returns a numerically-indexed array of IPs/domains.
	 *
	 * @return array
	 */	 	 	 	 	
	public function getIps() {
		$toReturn = array();
		foreach ($this->geodata as $key=>$val) {
			$toReturn[] = $key;
		}
		return $toReturn;
	}
	
	/**
	 * Get all locations represented by the Geolocator.
	 *
	 * Returns an associative array, indexed by IP/domain, of Geolocation objects.
	 *
	 * If lookupAll() was not previously called, this method may block while
	 * performing network operations.
	 *
	 * @return array
	 */
	public function getAllLocations() {
		$this->lookupAll();
		
		$toReturn = array();
		
		foreach ($this->geodata as $ip=>$value) {
			$toReturn[$ip] = $value['location'];
		}
		
		return $toReturn;
	}
	
	/**
	 * Get the location for one IP/domain.
	 *
	 * If this object represents only one location, you can pass NULL for $ip
	 * to get that location.
	 *
	 * If lookup() was not previously called for this IP, this method may block
	 * while performing network operations.
	 *
	 * Returns a Location object.
	 *
	 * @param string|NULL $ip the IP to get the location for
	 * @return Location
	 * @throws GeolocatorException
	 */
	public function getLocation($ip = NULL) {
		if ($ip === NULL) {
			$ip = $this->firstIp();
		}
		if ($ip === NULL) {
			throw new GeolocatorException("There are no IPs in the Geolocator. (Check that you specified an API key in the constructor.)");
		}
		
		$ip = $this->cleanIpInput($ip);
		
		if (array_key_exists($ip, $this->geodata)) {
			if ($this->geodata[$ip]['dataGood'] == false) {
				$this->lookup($ip);
			}
			return $this->geodata[$ip]['location'];
		} else {
			throw new GeolocatorException("IP/domain '$ip' was not found in the Geolocator");
		}
	}
	
	/**
	 * Gets data for all desired IPs/domains. The data will be cached for
	 * subsequent getLocation calls.
	 * 
	 * Does not redownload data for IPs that were already looked up.
	 * 
	 * This method will block while performing network operations.
	 *
	 * Chainable.
	 *
	 * @throws GeolocatorException
	 * @return Geolocator $this
	 */
	public function lookupAll() {
		foreach($this->geodata as $ip => $value) {
			if (!$value['dataGood']) {
				$this->lookup($ip);
			}
		}

		return $this;
	}
	
	/**
	 * Gets data for the given IPs/domain. The data will be cached for
	 * subsequent getLocation calls.
	 * 
	 * Will always refresh data for the given IP/domain.
	 *
	 * This method will block while performing network operations.
	 *
	 * Chainable.
	 *
	 * @param string $ip the IP to look up and cache the location
	 * @throws GeolocatorException
	 * @return Geolocator $this
	 */
	public function lookup($ip) {
		$endpoint = self::$endpoints[$this->precision];
		$result = NULL;

		$result = $this->curlRequest($ip, $endpoint);
		
		$this->geodata[$ip]['location'] = new Location($result, $this->precision);
		$this->geodata[$ip]['dataGood'] = true;
		
		return $this;
	}
	
	/**
	 * Filters and cleans up an IP/domain input string.
	 *
	 * @param string $input
	 * @return string
	 */
	protected function cleanIpInput($input) {
		$input = strtolower($input);
		$input = trim($input);
		return $input;
	}
	
	/**
	 * Performs a cURL request to the API.
	 *
	 * @param string $ipQueryString the IP string to pass to the API
	 * @param string $endpoint the API endpoint to use
	 * @throws GeolocatorException
	 * @return array array of API return
	 */
	protected function curlRequest($ip, $endpoint) {
		$qs = $endpoint . '?ip=' . $ip . '&format=json&key=' . $this->apiKey;
		
		if ($this->curl === NULL) {
			$this->curl = curl_init();
			curl_setopt ($this->curl, CURLOPT_FAILONERROR, TRUE);
			curl_setopt ($this->curl, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt ($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt ($this->curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
			curl_setopt ($this->curl, CURLOPT_TIMEOUT, $this->transferTimeout);
		}	
		
		curl_setopt ($this->curl, CURLOPT_URL, $qs);
		
		$json = curl_exec($this->curl);
		
		if(curl_errno($this->curl) || $json === FALSE) {
			$err = curl_error($this->curl);
			throw new GeolocatorException('cURL failed. Error: ' . $err);
		}
		
		$response = json_decode($json);
		
		if ($response->statusCode != 'OK') {
			throw new GeolocatorException('API returned error: ' . $response->statusMessage);
		}
		
		return $response;
	}
	
	/**
	 * Gets the first IP in the $this->ips array.
	 *
	 * @return string
	 */
	protected function firstIp() {
		reset($this->geodata);
		return key($this->geodata);
	}
	
	//
	// ArrayObject:
	//
	
	public function append($value) {
		return $this->offsetSet(NULL, $value);
	}
	
	/**
	 * Adds an IP/domain to this object.
	 *
	 * If $index is NULL, the $value is added (eg. if you used $locator[] = $ip)
	 * If $index is not NULL, $index is added (eg. if you said $locator[$ip] = NULL)
	 * 
	 * @param $index
	 * @param $value
	 * @return void
	 */
	public function offsetSet($index, $value) {
		$add = ($index === NULL) ? $value : $index;
		$this->addIp($add);
	}
	
	public function count() {
		return count($this->geodata);
	}
	
	public function offsetExists($index) {
		return array_key_exists($this->cleanIpInput($index), $this->geodata);
	}

	/*
	 * @see Geolocator::getLocation()
	 * This method may block for network operations.
	 */
	public function offsetGet($index) {
		return $this->getLocation($index);
	}
	
	public function offsetUnset($index) {
		$this->removeIp($index);
	}
	
	public function getIterator() {
		return $this;
	}
	
	//
	// Iterator:
	//
	
	/*
	 * @see Geolocator::getLocation()
	 * This method may block for network operations.
	 */
	public function current() {
		return $this->getLocation($this->iterCurrentKey);
	}
	
	public function key() {
		return $this->iterCurrentKey;
	}
	
	public function next() {
		if (next($this->geodata) === FALSE) {
			$this->iterCurrentKey = NULL;	
		} else {
			$this->iterCurrentKey = key($this->geodata);
		}
	}
	
	public function rewind() {
		reset($this->geodata);
		$this->iterCurrentKey = key($this->geodata);
	}
	
	public function valid() {
		return array_key_exists($this->iterCurrentKey, $this->geodata);
	}
}
