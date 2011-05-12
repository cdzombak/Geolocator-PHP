# Geolocator-PHP

Geolocator provides an easy-to-use, versatile, object-oriented interface to the IP Location API provided by [ipinfodb.com](http://ipinfodb.com/ip_location_api.php). This API allows you to find the city, state/region, country, and approximate latitude/longitude associated with an IP address or domain.

It requires PHP [5.3.0](http://php.net/releases/5_3_0.php) or greater, with [cURL](http://php.net/manual/en/book.curl.php) and [JSON](http://php.net/manual/en/book.json.php) support. (It may work with less than v5.3.0, but this is not supported.)

## Features

* Versatile interface for location lookups of one or many domains/IPs
* Allows some array and iterator behavior for simplicity when working with large datasets (not yet totally implemented)
* Most methods are chainable, meaning you can do things like `$location = Geolocator::instantiate($apiKey, $myIp)->setPrecision(Geolocator::PRECISION_COUNTRY)->getLocation();` on one line
* User can control when blocking (network) operations occur.

## Usage

General guide to using the class:

1. Create the Geolocator object. Set the API key in the constructor.
2. Add one or multiple IPs/domains.
3. Access the results.

For complete documentation, see the documentationn (in JavaDoc format) in the Geolocator class.

For examples of using this class and all its features, see the examples directory in this distribution.

## Current Versions

The current and development versions [are maintained on Github](http://github.com/cdzombak/Geolocator-PHP).

There is not currently a stable working version. v3 (beta) is currently in development and is stable enough for development.

# Licensing

The project is dual-licensed under the MIT license and the GPLv3.

# Author

This software was written by [Chris Dzombak](http://chris.dzombak.name).
