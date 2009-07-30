<?php

/**
	@file    IPGeolocation.test.php
	@author  Chris Dzombak <chris@chrisdzombak.net> <http://chris.dzombak.name>
	@version dev
	@date    July 21, 2009
	
	@section DESCRIPTION
	
	This script tests the accompanying copy of IPGeolocation.class.php.
	It can be run from the command line or from a Web browser (it will
	not provide HTML output.)
	
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
	
	(The GPL is found in the text file COPYING which should accompany this
	script.)
*/

require_once('IPGeolocation.class.php');

$ip = '69.168.45.34';	/**< IP to check. @todo Change to remote_addr or user input based on isset($_SERVER). */

echo 'Creating IPGeolocation object...\n';
$loc = new IPGeolocation($ip);
checkError();

echo 'Friendly location: ' . $loc->getFriendlyLocation() . '\n';

echo 'Country code: ' . $loc->getCountryCode() . '\n';

echo 'Country: ' . $loc->getCountry() . '\n';

echo 'Region: ' . $loc->getRegion() . '\n';

echo 'Region code: ' . $loc->getRegionCode() . '\n';

echo 'City: ' . $loc->getCity() . '\n';

echo 'Postal code: ' . $loc->getPostalCode() . '\n';

echo 'Latitude: ' . $loc->getLatitude() . '\n';

echo 'Longitude: ' . $loc->getLongitude() . '\n';

echo 'Testing getLatLon...\n';
$ll = $loc->getLatLon();
echo '\tlat: ' . $ll[0] . '\n';
echo '\tlon: ' . $ll[1] . '\n';

echo 'Testing getLatLonAssoc...\n';
$ll = $loc->getLatLonAssoc(('la', 'lo');
echo '\tlat: ' . $ll['la'] . '\n';
echo '\tlon: ' . $ll['lo'] . '\n';

echo 'GMT Offset: ' . $loc->getGmtOffset() . '\n';

echo 'DST Offset: ' . $loc->getDstOffset() . '\n';

echo 'IP: ' . $loc->getIP() . '\n';

/** @todo test getLocationProperties() */


function checkError()
{
	if ($loc->error)
	{
		echo 'IPGeolocation error: ' . $loc->error . '\n';
		exit 0;
	}
}

?>
