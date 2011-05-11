<?php

require_once('Geolocator.php');
require_once('GeolocationException.php');

/**
 * @author  Chris Dzombak
 * @version 
 * @date    
 */

class Geolocation {
	
	private $location;
	private $timeProperties;
	private $ip;
	private $precision;
	
	/**
	 * Constructs a location given a return from the ipInfoDb API.
	 *
	 * @param $apiRes The API response for this location
	 * @param $precision The precision of this location; Geolocator::PRECISION_CITY or Geolocator::PRECISION_COUNTRY
	 */
	function __construct($apiRes, $precision) {
		$this->update($apiRes, $precision);
	}
	
	/**
	 * Updates this location given a return from the ipInfoDb API.
	 *
	 * @param $apiRes The API response for this location
	 * @param $precision The precision of this location; Geolocator::PRECISION_CITY or Geolocator::PRECISION_COUNTRY
	 */
	public function update($apiRes, $precision) {
		$this->precision = $precision;
		$this->ip        = $apiRes->Ip;
		
		$this->location['countryAbbrev'] = $apiRes->CountryCode;
		$this->location['country']       = $apiRes->CountryName;
		// @TODO region codes
		//$this->location['regionCode']  = $apiRes->RegionCode;
		// Note: 'region' refers to a state in the US
		$this->location['region']        = $apiRes->RegionName;
		$this->location['city']          = $apiRes->City;
		$this->location['postalCode']    = $apiRes->ZipPostalCode;
		$this->location['latitude']      = $apiRes->Latitude;
		$this->location['longitude']     = $apiRes->Longitude;
		
		$this->timeProperties['timezone']  = $apiRes->Timezone;
		$this->timeProperties['gmtOffset'] = $apiRes->Gmtoffset;
		$this->timeProperties['dstOffset'] = $apiRes->Dstoffset;
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
	 * Returns an array representing the location.
	 *
	 * Array keys:
	 * @code
	 *  countryAbbrev
	 *  country
	 *  region
	 *  city
	 *  postalCode
	 *  latitude
	 *  longitude
	 * @endcode
	 *
	 * @return array
	 */
	public function getLocation() {
		return $this->location;
	}
	
	/**
	 * Returns an array representing the time info for this location.
	 *
	 * Array keys:
	 * @code
	 *  timezone
	 *  gmtOffset
	 *  dstOffset
	 * @endcode
	 *
	 * For more on time from this API, see <http://ipinfodb.com/ip_location_api_json.php>
	 *
	 * @return array
	 */
	public function getTimeProperties() {
		return $this->timeProperties;
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
