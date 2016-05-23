# Raphple

A mobile phone based online raffle system using PHP, MySQL, Slim Framework and Twilio. You can see a live implementation
as http://raphple.com.

## Setup (non-Docker)

1. Download Composer and install dependencies.
2. Set your document root to /public, with index.php as the default script.
3. Get a Twilio account with a phone number that will serve as your relay and point it to {app root}/twilio for SMS.
4. Import /schema.sql into a MySQL 5.6+ database.
5. Set $_SERVER or $_ENV vars for TWILIO_SID (ACxxxx), TWILIO_TOKEN (some hex string) and TWILIO_NUMBER (+xxxxxxx),
as well as DB_HOST, DB_USER, DB_PASSWORD and DB_NAME vars to connect to your database. If you are
using nginx, add `fastcgi_param TWILIO_XXX {value};` lines after `include fastcgi_params;` to do this.

## Setup (Docker)

You'll still need to do steps 3-5, though for step 5 you'll supply env vars to the container. The included Dockerfile
includes nginx + php-fpm managed via runit, so once you point your container to your database you'll have everything
you need.

For development, comment out everything below VOLUME and uncomment VOLUME in the Dockerfile, then build, so you'll get
live-syncing of any file changes you make without having to rebuild the container. In a production context, you'll
want to turn off the volume mount and let the container build all the way (including file copies) for better
performance and a portable build artifact.

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
