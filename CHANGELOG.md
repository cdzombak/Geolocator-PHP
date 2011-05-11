# 2.0-alpha-1 -> 2.0-alpha-2.5

* Implements iterator functionality
* Better functionality when only one location is represented
* cleanIpInput() is now a public static method
* Implements general array functionality
* Improves documentation
* Returns Geolocation objects instead of arrays
* Some other small changes; see the docs for full details

# 1.0 -> 2.0-alpha-1

* Not API-compatible with v1
* Name changed to Geolocator PHP
* one Geolocator can be used to find multiple locations in one lookup
* Supports country precision
* Supports looking up domains
* More efficient internal operation
* More intuitive, object-oriented API usage
* Fix PHP notice about $this->timeout being undefined. (actually fixed a while ago)
* Note: I considered adding some form of caching to this system earlier, but I've since 
  decided caching would be out of this project's scope.

# 0.99 -> 1.0

* Fixed some whitespace/formatting issues
* Add some @doc tags to documentation
* Removed problematic invisible characters from beginning of file
* Moved to git/github for SCM

# 0.9 -> 0.99

* Added a bit of documentation about region codes.
  Basically, they seem to be internally useful to the API, but your app
  should not need to use them.
* Various documentation improvements.
* Updated API endpoint URL.
* Set a few more cURL options.
* Added error handling around the cURL code.
* Added capability to use the API's backup server.
* A 1.0 release will follow barring any bug discoveries.
