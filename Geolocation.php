<?php

require_once('Geolocator.php');
require_once('GeolocationException.php');

class Geolocation {
	
	private $location;
	private $timeProperties;
	private $ip;
	private $precision;
	
	function __construct($apiRes, $precision) {
		$this->update($apiRes, $precision);
	}
	
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
	
	public function getPrecision() {
		return $this->precision;
	}
	
	public function getLocation() {
		return $this->location;
	}
	
	public function getTimeProperties() {
		return $this->timeProperties;
	}
	
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