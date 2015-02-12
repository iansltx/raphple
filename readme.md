# Raphple

A mobile phone based online raffle system using PHP, MySQL, Slim Framework and Twilio. You can see a live implementation
as http://raphple.com.

## Setup

1. Download Composer and install dependencies.
2. Set your document root to /public, with index.php as the default script.
3. Get a Twilio account with a phone number that will serve as your relay and point it to {app root}/twilio for SMS.
4. Set $_SERVER vars for TWILIO_SID (ACxxxx), TWILIO_TOKEN (some hex string) and TWILIO_NUMBER (+xxxxxxx). If you are
using nginx, add `fastcgi_param TWILIO_XXX {value};` lines after `include fastcgi_params;` to do this.
5. Import /schema.sql into a database with username raphple, password raphple and database raphple on localhost w\MySQL.

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
