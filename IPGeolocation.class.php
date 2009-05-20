<?php

/**
	@file	IPGeolocation.class.php
	@author	Chris Dzombak <chris@chrisdzombak.net>
	@version	XXXX
	
	@section LICENSE
	
	© 2009 Chris Dzombak
	
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
	(The GPL is stored in the text file COPYING which should accompany this
	class.)
	
	@section DESCRIPTION
	
	This class provides an interface to the IP Address Location XML API described
	at <http://iplocationtools.com/ip_location_api.php>.
	
	Basic usage:
		require_once('IPGeolocation.class.php');
		$location = new IPGeolocation(IP);
		$strCityStateCountry = $location->getFriendlyLocation();
	
	TODO before 1.0:
		- Learn more about country and region codes
		- Better error handling in general
		- Optimize curl options
		- Handle curl errors
	
	For documentation, we are using JavaDoc-style documentation (I think) with
	JAVADOC_AUTOBRIEF=YES.
*/

/**
 * @class IPGeolocation
 * @brief Represents the physical location of an IP address.
 *  
 * The class is initialized by passing the constructor an IP address. It
 * finds the physical location of that address and the class provides functions for
 * getting various bits of information about the location as well as a friendly
 * preformatted "city, state, country" location string.
 *
 */  

class IPGeolocation {
	
	private $ip;			/**< IP address represented by the object */
	private $xml;			/**< SimpleXML object used to parse the API response */
	private $timeout = 15;	/**< CURL timeout */
	private $serviceEndpoint = 'http://iplocationtools.com/ip_query.php';	/**< Endpoint of the geolocation API. This endpoint can also be <http://blogama.org/ip_query.php> */
	public  $error = false;	/**< Holds a string if there is an error in the class; remains FALSE if everything is OK */
	
	/**
	 * IPGeolocation constructor.
	 * Creates a new IPGeolocation object reperesnting one IP address/location.
	 * 
	 * @param $req_ip The IP address to be located.
	 * 
	 * @return True if the IP was located successfully.
	 * @return False if the IP location failed; check class error variable.
	 */
	function __construct($req_ip)
	{
		$this->ip = $req_ip;
		
		$ch = curl_init();
      	curl_setopt ($ch, CURLOPT_URL, "$serviceEndpoint?ip=$req_ip&output=xml");
      	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
      	$response = curl_exec($ch);
      	curl_close($ch);
		
      	$this->xml = new SimpleXMLElement($response);
		if ($this->xml->Status != 'OK')
		{
			$this->error = 'Geolocation service did not return OK status.';
			return false;
		}
		// todo: check for empty location strings as well. handle in TwitterOnAccess.
		return true;
	}
	
	/**
	 * Gets a "city, state, country" location string.
	 * @return The IP's physical location in a string with format "city, region, country"
	 * @see getCity()
	 * @see getRegion()
	 * @see getCountry()
	 */
	public function getFriendlyLocation()
	{
		$country = $this->xml->CountryName;
     	$region = $this->xml->RegionName;
     	$city = $this->xml->City;
     	if ($city == '' && $region == '')
     		$loc = $country;
     	else if ($city == '')
     		$loc = "$region, $country";
     	else
     		$loc = "$city, $region, $country";
     	return $loc;
	}
	
	/**
	 * Gets the country code of the IP address.
	 * Typically, this will return an abbreviation for the country. NOTE that the
	 * API docs (which are weak) do not clearly specify this behavior.
	 * @return The IP's country code (eg. US) in a string.
	 */
	public function getCountryCode()
	{
		return $this->xml->CountryCode;
	}
	
	/**
	 * Gets the IP's country.
	 * @return The IP's country in a string.
	 * @see getFriendlyLocation()	 
	 */
	public function getCountry()
	{
		return $this->xml->CountryName;
	}
	
	/**
	 * Gets the IP's region (or state).
	 * @return The IP's region (aka state) in a string.
	 * @see getFriendlyLocation()	 
	 */
	public function getRegion()
	{
		return $this->xml->RegionName;
	}
	
	/**
	 * Gets the IP's region (or state) code.
	 * This will return whatever the API says is the region code. NOTE: this is
	 * not clearly specified in the (weak) API docs; it is unclear what this does
	 * some of the time.
	 * @return The IP's region code.
	 */
	public function getRegionCode()
	{
		return $this->xml->RegionCode;
	}
	
	/**
	 * Gets the IP's city.
	 * @return The IP's city in a string.
	 * @see getFriendlyLocation()	 
	 */
	public function getCity()
	{
		return $this->xml->City;
	}
	
	/**
	 * Gets the IP's postal/zip code.
	 * @return The IP's postal/zip code.
	 */
	public function getPostalCode()
	{
		return $this->xml->ZipPostalCode;
	}
	
	/**
	 * Gets the IP's approximate latidude in decimal format.
	 * @return The IP's latitude (approx.), in decimal format.
	 * @see getLongitude()
	 * @see getLatLon()
	 * @see getLatLonAssoc()
	 */
	public function getLatitude()
	{
		return $this->xml->Latitude;
	}
	
	/**
	 * Gets the IP's approximate longitude in decimal format.
	 * @return The IP's longitude (approx.), in decimal format.
	 * @see getLatitude()
	 * @see getLatLon()
	 * @see getLatLonAssoc()	 
	 */
	public function getLongitude()
	{
		return $this->xml->Longitude;
	}
	
	/**
	 * Gets the IP's approximate latitude and longitude.
	 * Returns the IP's (approximate) latitude and longitude in a numerically-indexed array.
	 * @return A numerically-indexed array representing the IP's location. $return[0] holds the latitude of the IP address. $return[1] holds the longitude.
	 * @see getLatitude()
	 * @see getLongitude()
	 * @see getLatLonAssoc()
	 */
	public function getLatLon()
	{
		$toRet = array();
		$toRet[] = $this->xml->Latitude;
		$toRet[] = $this->xml->Longitude;
		return $toRet;
	}
	
	/**
	 * Gets the IP's approximate latitude and longitude.
	 * Returns the IP's (approximate) latidude and longitude in an associative array.
	 * 
	 * @param $latKey The array key to associate with latitude (default "latitude")
	 * @param $lonKey The array key to associate with longitude (default "longitude")
	 * 
	 * @return An associative array representing the IP's location.
	 * 
	 * @see getLatitude()
	 * @see getLongitude()
	 * @see getLatLon()
	 */	 
	public function getLatLonAssoc($latKey='latitude', $lonKey='longitude')
	{
		return array(
			"$latKey" => $this->xml->Latitude,
			"$lonKey" => $this->xml->Longitude
		);
	}
	
	/**
	 * Returns an associative array containing all location data returned by the API.
	 * Returns an associative array, whose keys can be chosen by the user in the function call,
	 * containing all meaningful (location) data returned by the geolocation API. Data returned
	 * are country name, country code, region/state name, region/state code, city, zip/postal
	 * code, latitude (approx), longitude (approx).
	 * 
	 * @param $regionNameKey		Array key for region name. Default "region". May be useful to redefine as "state".
	 * @param $countryNameKey	Array key for country name. Default "country"
	 * @param $cityKey			Array key for city name. Default "city"
	 * @param	$postalCodeKey		Array key for postal/zip code. Default "postalCode"
	 * @param $latKey			Array key for latitude (returned in decimal format). Default "latitude"
	 * @param $lonKey			Array key for longitude (returned in decimal format). Default "longitude"
	 * @param $regionCodeKey		Array key for region code. Default "regionCode"
	 * @param $countryCodeKey	Array key for country code. Default "countryCode"
	 * 
	 * @return Associative array containing all location data returned by the API.
	 */	 
	public function getLocationProperties($regionNameKey='region', $countryNameKey='country', $cityKey='city', $postalCodeKey='postalCode', $latKey='latitude', $lonKey='longitude', $regionCodeKey='regionCode', $countryCodeKey='countryCode')
	{
		return array(
			"$countryNameKey"	=> $this->xml->CountryName,
			"$regionNameKey"	=> $this->xml->RegionName,
			"$cityKey"		=> $this->xml->City,
			"$postalCodeKey"	=> $this->xml->ZipPostalCode,
			"$latKey"			=> $this->xml->Latitude,
			"$lonKey"			=> $this->xml->Longitude,
			"$regionCodeKey"	=> $this->xml->RegionCode,
			"$countryCodeKey"	=> $this->xml->CountryCode
		);
	}
	
	/**
	 * Gets the IP address associated with the object.
	 * This might be useful, for example, if looping through an array of IPGeolocation
	 * objects, or if the original IP was somehow "lost" in the user's code.
	 * @return The IP address whose location the object describes. String.
	 */	 	 	 	 	
	public function getIP() {
		return $this->ip;
	}
}

?>
