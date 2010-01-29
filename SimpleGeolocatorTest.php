<?php

require_once('Geolocator.php');

// Test with multiple lookups; some domain, some IP

$ipgeo = new Geolocator('67.194.133.148');
$ipgeo->addIp('google.com');
$ipgeo->addIp('cdzombak.net');
$ipgeo->addIp('67.194.132.148');

echo '<pre>';
var_dump($ipgeo->getAllLocations());
$ipgeo->setPrecision(Geolocator::PRECISION_COUNTRY);
echo '<br /><br />';
var_dump($ipgeo->getAllLocations());
echo '</pre>';
