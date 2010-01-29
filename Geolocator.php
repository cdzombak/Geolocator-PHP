<?php

require_once('GeolocatorException.php');

// @TODO Geolocation class for returns?
// require_once('Geolocation.php');

/**
	@mainpage
	@file    Geolocator.php
	@author  Chris Dzombak <chris@chrisdzombak.net> <http://chris.dzombak.name>
	@version 2.0-alpha-1
	@date    January 29, 2010
	
	@section DESCRIPTION
	
	This class provides an interface to the IP Address Location XML API described
	at <http://ipinfodb.com/ip_location_api.php>. It requires PHP 5 and cURL.
	
	The Web site of this project is: http://projects.chrisdzombak.net/ipgeolocationphp
	
	@section LICENSE

	© 2009-2010 Chris Dzombak
	
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
	
	(The GPL is found in the text file COPYING which should accompany this
	class.)
*/

/**
 * @class Geolocator
 */  

class Geolocator {
	
	const CONNECT_TIMEOUT     = -1;
	const TRANSFER_TIMEOUT    = -2;
	
	const PRECISION_CITY      = 1;
	const PRECISION_COUNTRY   = 2;
	
	private $ips              = array();  /**< IP addresses represented by the object. array. */
	private $hasData          = false;    /**< Whether the geolocator has found valid data */
	private $attemptedLookup  = false;    /**< Whether the geolocator has attempted a lookup */
	private $precision        = self::PRECISION_CITY;   /**< desired lookup precision */
	private $result;
	
	private $connectTimeout   = 2;  /**< cURL connect timeout (seconds) */
	private $transferTimeout  = 3;  /**< cURL transfer timeout (seconds) */
	
	const PRIMARY_SERVER      = 'http://ipinfodb.com/';
	const BACKUP_SERVER       = 'http://backup.ipinfodb.com/';
	const IPQUERY             = 'ip_query.php';
	const IPQUERY_2           = 'ip_query2.php';
	const IPQUERY_COUNTRY     = 'ip_query_country.php';
	const IPQUERY_2_COUNTRY   = 'ip_query2_country.php';
	
	/**
	 * Geolocator constructor.
	 * 
	 * @param $req String of one IP/domain to lookup OR an array of IPs/domains to lookup
	 * @return void
	 */
	 // @TODO should not require that something be passed in
	function __construct($req) {
		if (is_array($req)) {
			foreach ($req as $ip) {
				$this->addIp($ip);
			}
		} else {
			$this->addIp($req);
		}
	}
	
	public function addIp($ip) {
		$this->hasData = false;
		$this->attemptedLookup = false;
		
		$ip = $this->cleanIpInput($ip);
		
		if (!array_key_exists($ip, $this->ips)) {
			$this->ips[$ip] = NULL;
		}
	}
	
	public function getAllLocations() {
		if (!$this->hasData) {
			if (!$this->lookup()) {
				throw new GeolocatorException('Automatic call to lookup() failed');
			}
		}
		return $this->ips;
	}
	
	public function getLocation($ip) {
		if (!$this->hasData) {
			if (!$this->lookup()) {
				throw new GeolocatorException('Automatic call to lookup() failed');
			}
		}
		$ip = $this->cleanIpInput($ip);
		if (array_key_exists($ip, $this->ips)) {
			return $this->ips[$ip];
		}
		return NULL;
	}
	
	/**
	 * Sets the desired connect or transfer timeout
	 *
	 * Defaults are 2s for connect, 3s for transfer
	 *
	 * @param $timeoutType one of Geolocator::CONNECT_TIMEOUT , Geolocator::TRANSFER_TIMEOUT
	 * @param $time timeout value
	 * @return void
	 * @throws GeolocatorException
	 */
	public function setTimeout($timeoutType, $time) {
		$this->hasData = false;
		$this->attemptedLookup = false;
		
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
	}
	
	/**
	 * Sets the desired lookup precision
	 *
	 * Default is city precision
	 *
	 * @param $precision one of Geolocator::PRECISION_CITY , Geolocator::PRECISION_COUNTRY
	 * @return void
	 * @throws GeolocatorException
	 */
	public function setPrecision($precision) {
		$this->hasData = false;
		$this->attemptedLookup = false;
		
		if ($precision == self::PRECISION_CITY || $precision == self::PRECISION_COUNTRY) {
			$this->precision = $precision;
		} else {
			throw new GeolocatorException ('Invalid precision specified.');
		}
	}
	
	/**
	 * Gets the IPs/domains represented by this object
	 * Returns a string if the object represents one IP/domain
	 * Otherwise, returns a numerically-indexed array of IPs/domains
	 * @return mixed
	 */	 	 	 	 	
	public function getIps() {
		if (count($this->ips) == 1) {
			return $this->firstIp();
		}
		$toReturn = array();
		foreach ($this->ips as $key=>$val) {
			$toReturn[] = $key;
		}
		return $toReturn;
	}
	
	/**
	 * Performs the lookup of all desired IPs/domains
	 * Returns TRUE if success, FALSE if failure
	 * @return bool
	 */
	public function lookup() {
		$this->attemptedLookup = true;
		
		$endpointFile = NULL;
		if (count($this->ips) == 1) {
			switch ($this->precision) {
				case self::PRECISION_CITY:
					$endpointFile = self::IPQUERY;
					break;
				case self::PRECISION_COUNTRY:
					$endpointFile = self::IPQUERY_COUNTRY;
					break;
				default:
					throw new GeolocatorException('Internal error: $this->precision is invalid: ' . $this->precision);
					break;
			}
		} else {
			switch ($this->precision) {
				case self::PRECISION_CITY:
					$endpointFile = self::IPQUERY_2;
					break;
				case self::PRECISION_COUNTRY:
					$endpointFile = self::IPQUERY_2_COUNTRY;
					break;
				default:
					throw new GeolocatorException('Internal error: $this->precision is invalid: ' . $this->precision);
					break;
			}
		}
		
		$endpoint       = self::PRIMARY_SERVER . $endpointFile;
		$backupEndpoint = self::BACKUP_SERVER . $endpointFile;
		
		$ipString = '';
		if (count($this->ips) == 1) {
			$ipString = $this->firstIp();
		} else {
			foreach ($this->ips as $key=>$value) {
				$ipString .= ',' . $key;
			}
			$ipString = substr($ipString, 1);
		}
		
		if (!$this->curlRequest($ipString, $endpoint)) {
			// primary server failed; try backup
			if (!$this->curlRequest($ipString,$backupEndpoint)) {
				//backup endpoint failed; return false
				return false;
			}
		}
		
		$this->parseIntoIps();
		return true;
	}
	
	private function parseIntoIps() {
		$i = 0;
		foreach ($this->ips as &$ip) {
			$ip = $this->result->Locations[$i];
			$i++;
		}
		unset($this->result);
		$this->hasData = true;
	}
	
	private function curlRequest($ipQueryString, $endpoint) {
		// @TODO error handling in this function
		$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $endpoint . '?ip=' . $ipQueryString . '&output=json');
			curl_setopt ($ch, CURLOPT_FAILONERROR, TRUE);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
			curl_setopt ($ch, CURLOPT_TIMEOUT, $this->transferTimeout);
		
		$json = curl_exec($ch);
		
		if(curl_errno($ch)) {
			//throw new GeolocatorException('cURL failed. Error: ' . curl_error($ch));
			curl_close($ch);
			return false;
		}

		curl_close($ch);
		
		$this->result = json_decode($json);
		// @TODO validate result
		
		return true;
	}
	
	private function firstIp() {
		reset($this->ips);
		return key($this->ips);
	}
	
	private function cleanIpInput($input) {
		$input = strtolower($input);
		$input = trim($input);
		return $input;
	}
}
