# Raphple

A mobile phone based online raffle system using PHP, MySQL, Slim Framework and Twilio, Nexmo, or SignalWire.
You can see a live implementation (using Twilio) as http://raphple.com.

## Setup (non-Docker)

1. Set up an SMS provider account and point it to the appropriate webhook, or use a dummy account.
See Setup (SMS) for more details.
2. Download Composer and install dependencies.
3. Set your document root to /public, with index.php as the default script.
4. Import /schema.sql into a MySQL 5.6+ database.
5. Set $_SERVER or $_ENV vars for your SMS provider,
as well as DB_HOST, DB_USER, DB_PASSWORD and DB_NAME vars to connect to your database. If you are
using nginx, add `fastcgi_param DB_HOST {value};` lines after `include fastcgi_params;` to do this.

## Setup (Docker)

After performing step 1 in the above, copy `docker-compose.override-example.yml` to `docker-compose.override.yml` and
set up the environment variables specific to your SMS provider. Once that's done, `docker-compose up` will get you web
and database containers, with the former running nginx + php-fpm via runit, and the latter having the schema
automatically imported on-start.

For development, mount a volume with your code to `/var/app`. Otherwise your code stay at whatever state it was when you
built your container.

## Setup (SMS)

Raphple now supports Twilio, Nexmo, or SignalWire, and requires libraries for neither (doesn't require curl either).
Just set the appropriate env vars for your provider, and point that provider's webhook endpoint at the proper URL. Phone
number formats also vary between providers, but as long as you only use one at a time that doesn't matter.

In addition to the below env vars, set `PHONE_NUMBER` to the number you'll use to receive (and send) raffle-related
texts.

You can choose instead to stub out outbound SMSes. Either of the two webhooks will still respond, but no SMSes will
be sent. As part of the dummy SMS provider, an arbitrary wait, in milliseconds, is injected on each fake message
send event (you can set this to zero if you want). Messages that would have been sent will be logged to STDERR.

| Provider | Env Vars | Webhook Endpoint |
| --- | --- | --- |
| Twilio | TWILIO_SID, TWILIO_TOKEN | /twilio |
| Nexmo | NEXMO_KEY, NEXMO_SECRET | /nexmo |
| SignalWire | SIGNALWIRE_SPACE, SIGNALWIRE_PROJECT, SIGNALWIRE_TOKEN | /twilio (yes, response format is identical) |
| Dummy | DUMMY_SMS_WAIT_MS | n/a |

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
