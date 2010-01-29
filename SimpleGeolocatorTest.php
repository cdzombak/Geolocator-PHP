<?php

require_once('Geolocator.php');

// Test with two lookups; one domain, one IP

$ipgeo = new Geolocator('67.194.133.148');
$ipgeo->addIp('google.com');
$ipgeo->addIp('cdzombak.net');
$ipgeo->addIp('67.194.132.148');

echo '<pre>';
$ipgeo->lookup();
echo '</pre>';
