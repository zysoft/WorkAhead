# WorkAhead

This is a library you can use to perform background operations.
For example, when the user requests a page, this library can 
determine what page this user will visit next (by statistical 
data it gathers) and run a separate thread where you can perform 
data caching or other non critical operations.

Unfortunately, this library may not work on Windows platform 
because it uses PCNTL functions.
I didn't hear about PCNTL extension release for windows, but if 
you did - you are welcome to try.

Check [Wiki](https://github.com/zysoft/WorkAhead/wiki) for details.