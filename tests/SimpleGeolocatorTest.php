<?php

require_once('../src/Geolocator.php');

// add tests/TEST_API_KEY to .gitignore
$apiKey = trim(file_get_contents('TEST_API_KEY'));

// Test with multiple lookups; some domain, some IP, and array behavior

$ipgeo = new Geolocator($apiKey, '67.194.133.148');
$ipgeo[] = 'google.com';
$ipgeo['67.194.132.148'] = NULL;
$ipgeo['cdzombak.net'] = 'x';

echo 'count: ' . count($ipgeo) . "\n";

echo "result for google.com:\n";
echo var_export($ipgeo['google.com'], true);

unset($ipgeo['google.com']);
echo "\nremoved google.\n";

echo var_export($ipgeo->getAllLocations(), true);

/*foreach($ipgeo as $key=>$val) {
	echo "\n---------------------\n$key\n";
	echo var_export($val, true);
}*/

echo "\nSerialize Test\n";

$ipgeo = new Geolocator($apiKey);
$ipgeo[] = '67.194.133.148';
$ipgeo->addIp('google.com');
$ipgeo->addIp('cdzombak.net');

$ipgeo = serialize($ipgeo);
$ipgeo = unserialize($ipgeo);

$ipgeo->addIp('67.194.132.148');

echo var_export($ipgeo->getAllLocations(), true);

// Test with multiple lookups; some domain, some IP

$ipgeo = new Geolocator($apiKey, '67.194.133.148');
$ipgeo->addIp('google.com');
$ipgeo->addIp('cdzombak.net');
$ipgeo->addIp('67.194.132.148');

echo var_export($ipgeo->getAllLocations(), true);

$ipgeo->setPrecision(Geolocator::PRECISION_COUNTRY);

echo "\n";
echo var_export($ipgeo->getAllLocations(), true);

// Test chaining API methods

echo "chaining test:\n";
$location = Geolocator::instantiate($apiKey, 'chris.dzombak.name')->setPrecision(Geolocator::PRECISION_COUNTRY)->getLocation();
echo $location->getFriendlyName();
echo "\nlatitude:\n";
echo $location->countryCode;
echo "\n";

// Test no IPs

$ipgeo = new Geolocator($apiKey);

var_export($ipgeo->getAllLocations(), true);
