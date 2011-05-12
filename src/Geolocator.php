<?php

require_once('GeolocatorException.php');
require_once('Location.php');

/**
 * @mainpage
 * @author  Chris Dzombak
 * @version 
 * @date    2011-05-11
 * 
 * The Geolocator class is designed to be very versatile.
 * 
 * Note that "IP", used in these docs and these API methods, really means "IP or Domain".
 *
 * Note special handling for only one IP (getLocation)
 * 
 * It can be used easily to represent one IP/location:
 * @code
 * $locator = new Geolocator($ipAddress);
 * // set parameters such as precision, timeout, etc. here
 * $locator->lookup();  // this call is optional, but it improves performance
 *                      // to do the lookup before you need the data
 * $location = $locator->getLocation(); // returns Geolocation object
 * @endcode
 *
 * You can also use it to lookup up to 25 IPs/domains at a time:
 * @code
 * $locator = new Geolocator(array($ip1, $ip2, $ip3));
 * $locator->addIp('google.com');
 * // set parameters like precision, timeout, etc. here
 * $googleLocation = $locator->getLocation('google.com');
 * @endcode
 *
 * There are two useful getter methods not covered in the examples above:
 * @code
 * getAllLocations()
 * getIps()
 * @endcode
 *
 * Please see the generated API docs for complete documentation on all methods.
 *
 * Geolocator allows some array-like behavior for ease and simplicity of use.
 *
 * It is possible to add IPs with the [] (array append) operator:
 * @code
 * // initialize Geolocator; add 'google.com' here.
 * $locator[] = 'google.com';
 * @endcode
 *
 * Once you've added everything you want, it is possible to use the Geolocator
 * as an array to loop through results.
 * @code
 * // initialize Geolocator
 * foreach ($locator as $key=>$value) {
 *     // $key   == IP/domain
 *     // $value == Geolocation object for that IP/domain
 * }
 * @endcode
 *
 * It is also possible to lookup one location with the array offset operator:
 * @code
 * // initialize Geolocator; add $ipAddress here.
 * $location = $locator[$ipAddress']; // returns Geolocation object
 * @endcode
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

class Geolocator extends ArrayObject implements Iterator {
	
	const CONNECT_TIMEOUT      = -1;  /**< Identifies a connect timeout. @see Geolocator::setTimeout() */
	const TRANSFER_TIMEOUT     = -2;  /**< Identifies a transfer timeout @see Geolocator::setTimeout() */
	
	const PRECISION_CITY       = 1;  /**< @see Geolocator::setPrecision() */
	const PRECISION_COUNTRY    = 2;  /**< @see Geolocator::setPrecision() */
	
	protected static $endpoints = array(
		PRECISION_CITY    => 'http://api.ipinfodb.com/v3/ip-city/',
		PRECISION_COUNTRY => 'http://api.ipinfodb.com/v3/ip-country/'
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
	 * 
	 */
	function __construct($_apiKey) {
		$this->apiKey = $_apikey;
		$this->rewind();
	}
	
	/**
	 * 
	 */
	function __construct($_apiKey, $_firstParam) {
		$this->apiKey = $_apikey;
		
		if (is_array($_firstParam)) {
			foreach ($_firstParam as $ip) {
				$this->addIp($ip);
			}
		} else {
			$this->addIp($_firstParam);
		}
		
		$this->rewind();
	}
	
	/**
	 * Adds an IP or domain to the Geolocator.
	 *
	 * @param $ip The IP or domain to look up
	 * @return void
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
	 * Removes an IP or domain from the Geolocator.
	 *
	 * @param $ip the IP/domain to remove
	 * @return void
	 */
	public function removeIp($ip) {
		$ip = $this->cleanIpInput($ip);
		unset($this->geodata[$ip]);
		
		return $this;
	}
	
	/**
	 * Sets the desired connect or transfer timeout.
	 *
	 * (Defaults are 4s for connect, 4s for transfer.)
	 * 
	 * This can only be changed before you get the first result from the Geolocator.
	 * (After the first result, the cURL connection is cached and reused for performance.)
	 *
	 * @param $timeoutType one of Geolocator::CONNECT_TIMEOUT , Geolocator::TRANSFER_TIMEOUT
	 * @param $time timeout value
	 * @throws GeolocatorException
	 * @return void
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
	 *
	 * (Default is city precision.)
	 *
	 * @param $precision one of Geolocator::PRECISION_CITY , Geolocator::PRECISION_COUNTRY
	 * @throws GeolocatorException
	 * @return void
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
	 * Gets the IPs/domains represented by this object.
	 *
	 * Returns a numerically-indexed array of IPs/domains.
	 *
	 * @return mixed
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
	 * If this object represents one location, gets that location.
	 * Returns a Geolocation object or NULL.
	 *
	 * @return mixed
	 */
	public function getLocation($ip = NULL) {
		if ($ip === NULL) {
			$ip = $this->firstIp();
		}
		
		$ip = $this->cleanIpInput($ip);
		
		if (array_key_exists($ip, $this->geodata)) {
			if (!$this->geodata[$ip]['dataGood']) {
				$this->lookup($ip);
			}
			return $this->geodata['ip']['location'];
		} else {
			throw new GeolocatorException("IP/domain '$ip' was not found in the Geolocator");
		}
		
		return NULL;
	}
	
	/**
	 * Gets data for all desired IPs/domains.
	 * 
	 * Does not redownload data for IPs we already have.
	 *
	 * @throws GeolocatorException
	 * @return void
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
	 * Gets data for the given IPs/domain.
	 * 
	 * Will always refresh data for the given IP/domain.
	 *
	 * @throws GeolocatorException
	 * @return void
	 */
	public function lookup($ip) {
		$endpoint = $this->endpoints[$this->precision];
		$result = NULL;

		$result = $this->curlRequest($ipString, $endpoint);
		
		$this->geodata[$ip]['location'] = new Location($result, $this->precision);
		$this->geodata[$ip]['dataGood'] = true;
		
		return $this;
	}
	
	/**
	 * Filters and cleans up an IP/domain input string.
	 *
	 * @param $input
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
	 * @param $ipQueryString the IP string to pass to the API
	 * @param $endpoint the API endpoint to use
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
		
		$json = curl_exec($ch);
		
		if(curl_errno($ch) || $json === FALSE) {
			$err = curl_error($ch);
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
		reset($this->ips);
		return key($this->ips);
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
