<?php

require_once('GeolocatorException.php');
require_once('Geolocation.php');

/**
	@mainpage
	@author  Chris Dzombak <chris@chrisdzombak.net> <http://chris.dzombak.name>
	@version 2.0-alpha-1.9
	@date    January 30, 2010
	
	@section USAGE
	
	TODO: Complete this Usage section once everything for v2.0 is implemented. 
	
	@section DESCRIPTION
	
	This class provides an interface to the IP Address Location XML API described
	at <http://ipinfodb.com/ip_location_api.php>. It requires PHP 5 and cURL.
	
	The Web site of this project is: <http://projects.chrisdzombak.net/ipgeolocationphp>
	
	@section LICENSE

	(c) 2009-2010 Chris Dzombak
	
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

class Geolocator {
	
	const CONNECT_TIMEOUT     = -1;  /**< Identifies a connect timeout. @see Geolocator::setTimeout() */
	const TRANSFER_TIMEOUT    = -2;  /**< Identifies a transfer timeout @see Geolocator::setTimeout() */
	
	const PRECISION_CITY      = 1;  /**< @see Geolocator::setPrecision() */
	const PRECISION_COUNTRY   = 2;  /**< @see Geolocator::setPrecision() */
	
	private $ips              = array();  /**< IP addresses represented by the object. array. */
	private $hasData          = false;    /**< Whether the geolocator has found valid data */
	private $attemptedLookup  = false;    /**< Whether the geolocator has attempted a lookup */
	private $precision        = self::PRECISION_CITY;   /**< desired lookup precision */
	private $useBackupFirst   = false;    /**< Whether to use the backup server first (for better Europe performance) */
	
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
	 * @param $request String of one IP/domain to lookup OR an array of IPs/domains to lookup
	 * @throws GeolocatorException
	 * @return void
	 */
	function __construct($request) {
		// @TODO eventually, should not require that something be passed in
		if (is_array($request)) {
			foreach ($request as $ip) {
				if(!$this->addIp($ip)) {
					throw new GeolocatorException('Too many IPs added. Maximum: 25');
				}
			}
		} else {
			$this->addIp($request);
		}
	}
	
	/**
	 * Adds an IP or domain to the Geolocator.
	 *
	 * Returns TRUE for success; FALSE on failure (eg. if too many were added).
	 * A maximum of 25 may be added to a Geolocator.
	 *
	 * @param $ip The IP or domain to look up
	 * @return bool
	 */ 
	public function addIp($ip) {
		if(count($this->ips) >= 25) {
			return false;
		}
		
		$this->hasData = false;
		$this->attemptedLookup = false;
		
		$ip = $this->cleanIpInput($ip);
		
		if (!array_key_exists($ip, $this->ips)) {
			$this->ips[$ip] = NULL;
		}
		
		return true;
	}
	
	/**
	 * Get the number of IPs/domains represented by the Geolocator.
	 *
	 * Use to make sure you don't add more than 25.
	 *
	 * @return int
	 */
	public function getIpCount() {
		return count($this->ips);
	}
	
	/**
	 * Get all locations represented by the Geolocator.
	 *
	 * Returns an associative array, indexed by IP/domain, of Geolocation objects.
	 *
	 * @return array
	 */
	public function getAllLocations() {
		if (!$this->hasData) {
			$this->lookup();
		}
		return $this->ips;
	}
	
	/**
	 * Get the location for one IP/domain.
	 *
	 * Returns a Geolocation object or NULL.
	 *
	 * @return mixed
	 */
	public function getLocation($ip) {
		if (!$this->hasData) {
			$this->lookup();
		}
		$ip = $this->cleanIpInput($ip);
		if (array_key_exists($ip, $this->ips)) {
			return $this->ips[$ip];
		}
		return NULL;
	}
	
	/**
	 * Tells the Geolocator whether to lookup the IP on the backup server first.
	 *
	 * This could be ueful for Europe-based services, since the backup server
	 * is located in Germany and the primary is located in Canada.  Using the backup
	 * server for European apps will give you slightly higher performance.
	 *
	 * @param $useBackupFirst
	 * @throws GeolocatorException
	 * @return void
	 */
	public function setUseBackupFirst($useBackupFirst) {
		if (!is_bool($useBackupFirst)) {
			throw new GeolocatorException('Invalid choice specified for useBackupFirst');
		}
		$this->useBackupFirst = $useBackupFirst;
	}
	
	/**
	 * Sets the desired connect or transfer timeout.
	 *
	 * (Defaults are 2s for connect, 3s for transfer.)
	 *
	 * @param $timeoutType one of Geolocator::CONNECT_TIMEOUT , Geolocator::TRANSFER_TIMEOUT
	 * @param $time timeout value
	 * @throws GeolocatorException
	 * @return void
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
	 * Sets the desired lookup precision.
	 *
	 * (Default is city precision.)
	 *
	 * @param $precision one of Geolocator::PRECISION_CITY , Geolocator::PRECISION_COUNTRY
	 * @throws GeolocatorException
	 * @return void
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
	 * Gets the IPs/domains represented by this object.
	 *
	 * Returns a string if the object represents one IP/domain.
	 * Otherwise, returns a numerically-indexed array of IPs/domains.
	 *
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
	 * Performs the lookup of all desired IPs/domains.
	 *
	 * Should be called once you know you're done adding IPs so that the lookup data
	 * are ready when you need them.
	 * Returns TRUE if success, FALSE if failure.
	 *
	 * @throws GeolocatorException
	 * @return bool
	 */
	public function lookup() {
		$endpointFile = NULL;
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
		
		if (!$this->useBackupFirst) {
			$endpoint       = self::PRIMARY_SERVER . $endpointFile;
			$backupEndpoint = self::BACKUP_SERVER . $endpointFile;
		} else {
			$backupEndpoint = self::PRIMARY_SERVER . $endpointFile;
			$endpoint       = self::BACKUP_SERVER . $endpointFile;
		}
		
		$ipString = '';
		if (count($this->ips) == 1) {
			$ipString = $this->firstIp();
		} else {
			foreach ($this->ips as $key=>$value) {
				$ipString .= ',' . $key;
			}
			$ipString = substr($ipString, 1);
		}
		
		$result = NULL;
		try {
			$result = $this->curlRequest($ipString, $endpoint);
		} catch (GeolocatorException $e) {
			try {
				$result = $this->curlRequest($ipString,$backupEndpoint);
			} catch (GeolocatorException $e) {
				throw $e;
			}
		}
		
		$this->attemptedLookup = true;
		
		$this->parseIntoIps($result);
		return true;
	}
	
	/**
	 * Parses a given array of locations into the $this->ips array.
	 *
	 * error_log()s anything for which the API does not return a status of OK.
	 *
	 * @param $locArray array of locations returned from API
	 * @return void
	 */
	private function parseIntoIps($locArray) {
		$i = 0;
		foreach ($this->ips as &$ip) {
			if ($locArray->Locations[$i]->Status == 'OK') {
				$ip = new Geolocation($locArray->Locations[$i], $this->precision);
			} else {
				error_log('API returned error for ' . $locArray->Locations[$i]->Ip . ' : ' . $locArray->Locations[$i]->Status . ' . Precision: ' . $this->precision);
				$ip = NULL;
			}
			$i++;
		}
		$this->hasData = true;
	}
	
	/**
	 * Performs a cURL request to the API.
	 *
	 * @param $ipQueryString the IP string to pass to the API
	 * @param $endpoint the API endpoint to use
	 * @throws GeolocatorException
	 * @return array array of API return
	 */
	private function curlRequest($ipQueryString, $endpoint) {
		$qs = $endpoint . '?ip=' . $ipQueryString . '&output=json';
		$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $qs);
			curl_setopt ($ch, CURLOPT_FAILONERROR, TRUE);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
			curl_setopt ($ch, CURLOPT_TIMEOUT, $this->transferTimeout);
		
		$json = curl_exec($ch);
		
		if(curl_errno($ch) || $json === FALSE) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new GeolocatorException('cURL failed. Error: ' . $err);
		}

		curl_close($ch);
		
		return json_decode($json);
	}
	
	/**
	 * Gets the first IP in the $this->ips array.
	 *
	 * @return string
	 */
	private function firstIp() {
		reset($this->ips);
		return key($this->ips);
	}
	
	/**
	 * Filters and cleans up an IP/domain input string.
	 *
	 * @param $input
	 * @return string
	 */
	private function cleanIpInput($input) {
		$input = strtolower($input);
		$input = trim($input);
		return $input;
	}
}
