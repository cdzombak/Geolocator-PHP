<?php

require_once('Geolocator.php');

switch ($_GET['test']) {
	
	case 'array':
		// Test with multiple lookups; some domain, some IP, and array behavior
		
		$ipgeo = new Geolocator('67.194.133.148');
		$ipgeo[] = 'google.com';
		$ipgeo['67.194.132.148'] = NULL;
		$ipgeo['cdzombak.net'] = 'x';
		
		echo '<pre>';
		
		echo 'count: ' . count($ipgeo) . "\n";
		
		var_dump($ipgeo['google.com']);
		
		unset($ipgeo['google.com']);
		echo "\nremoved google.\n";
		
		var_dump($ipgeo->getAllLocations());
		
		foreach($ipgeo as $key=>$val) {
			echo "\n---------------------\n$key\n";
			var_dump($val);
		}
		
		echo '</pre>';
		
		break;
	
	case 'serialize':
		$ipgeo = new Geolocator('67.194.133.148');
		$ipgeo->addIp('google.com');
		$ipgeo->addIp('cdzombak.net');
		
		$ipgeo = serialize($ipgeo);
		$ipgeo = unserialize($ipgeo);
		
		$ipgeo->addIp('67.194.132.148');
		
		echo '<pre>';
		var_dump($ipgeo->getAllLocations());
		echo '</pre>';
		
		break;
	
	default:
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
		
		break;
}
