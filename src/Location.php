<?php

require_once('Geolocator.php');

/**
 * @author  Chris Dzombak
 * @version 
 * @date    2011-05-11
 * 
 * Represents a location returned from the geolocation API.
 */

class Location {
	
	protected $location;
	protected $precision;
	protected $ip;
	
	/**
	 * Constructs a location given a response from the geolocation API.
	 *
	 * @param $apiRes API response for this location
	 * @param $precision precision of this location; Geolocator::PRECISION_CITY or Geolocator::PRECISION_COUNTRY
	 */
	function __construct($apiRes, $precision) {
		$this->precision = $precision;
		$this->ip = $apiRes->ipAddress;
		
		$this->location['countryCode'] = $apiRes->countryName;
		$this->location['country']     = $apiRes->countryName;
		$this->location['region']      = $apiRes->regionName;
		$this->location['city']        = $apiRes->cityName;
		$this->location['postalCode']  = $apiRes->zipCode;
		$this->location['latitude']    = $apiRes->latitude;
		$this->location['longitude']   = $apiRes->longitude;
		$this->location['timeZone']    = $apiRes->timeZone;
	}
	
	/**
	 * Allows you to grab individual keys from the location array.
	 * 
	 * Called like $location->getCountry();
	 * 
	 * See Location::getlocation() for a list of keys.
	 * 
	 * @return string
	 * @throws Exception
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->location)) {
			return $this->location[$name];
		} else {
			throw new Exception("Key '$name' does not exist in location array.");
		}
	}
	
	/**
	 * Gets the precision represented by this location.
	 *
	 * Returns Geolocator::PRECISION_CITY or Geolocator::PRECISION_COUNTRY
	 * 
	 * @return int
	 */
	public function getPrecision() {
		return $this->precision;
	}
	
	/**
	 * Gets the IP address/domain represented by this location.
	 * 
	 * @return string
	 */
	public function getIp() {
		return $this->ip;
	}
	
	/**
	 * Returns an array representing the location.
	 *
	 * Array keys:
	 * @code
	 *  countryCode
	 *  country
	 *  region
	 *  city
	 *  postalCode
	 *  latitude
	 *  longitude
	 *  timeZone
	 * @endcode
	 *
	 * Note: for the USA, "region" == "state".
	 * 
	 * @return array
	 */
	public function getLocation() {
		return $this->location;
	}
	
	/**
	 * Returns a human-friendly name for this location.
	 *
	 * Formatted like "Ann Arbor, Michigan, United States"
	 *
	 * @return string
	 */
	public function getFriendlyName() {
		if ($this->location['city'] === NULL && $this->location['$region'] === NULL)
			$loc = $country;
		else if ($this->location['city'] === NULL)
			$loc = "$region, $country";
		else
			$loc = "$city, $region, $country";
		return $loc;
	}
}
