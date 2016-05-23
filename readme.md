# Raphple

A mobile phone based online raffle system using PHP, MySQL, Slim Framework and Twilio. You can see a live implementation
as http://raphple.com.

## Setup (non-Docker)

1. Download Composer and install dependencies.
2. Set your document root to /public, with index.php as the default script.
3. Set up an SMS provider account (Twilio or Nexmo) and point it to the appropriate webhook, or use a dummy account.
See Setup (SMS) for more details.
4. Import /schema.sql into a MySQL 5.6+ database.
5. Set $_SERVER or $_ENV vars for your SMS provider,
as well as DB_HOST, DB_USER, DB_PASSWORD and DB_NAME vars to connect to your database. If you are
using nginx, add `fastcgi_param DB_HOST {value};` lines after `include fastcgi_params;` to do this.

## Setup (Docker)

You'll still need to do steps 3-5, though for step 5 you'll supply env vars to the container. The included Dockerfile
includes nginx + php-fpm managed via runit, so once you point your container to your database you'll have everything
you need.

For development, comment out everything below VOLUME and uncomment VOLUME in the Dockerfile, then build, so you'll get
live-syncing of any file changes you make without having to rebuild the container. In a production context, you'll
want to turn off the volume mount and let the container build all the way (including file copies) for better
performance and a portable build artifact.

## Setup (SMS)

Raphple now supports Twilio and Nexmo, and requires libraries for neither (doesn't require curl either). Just set the
appropriate env vars for your provider, and point that provider's webhook endpoint at the proper URL. Phone number
formats also vary between providers, but as long as you only use one at a time that doesn't matter.

In addition to the below env vars, set `PHONE_NUMBER` to the number you'll use to receive (and send) raffle-related
texts.

You can choose instead to stub out outbound SMSes. EIther of the two webhooks will still respond, but no SMSes will
be sent. As part of the dummy SMS provider, an arbitrary wait, in milliseconds, is injected on each fake message
send event (you can set this to zero if you want). Messages that would have been sent will be logged to STDERR.

| Provider | Env Vars | Webhook Endpoint |
| --- | --- | --- |
| Twilio | TWILIO_SID, TWILIO_TOKEN | /twilio |
| Nexmo | NEXMO_KEY, NEXMO_SECRET | /nexmo |
| Dummy | DUMMY_SMS_WAIT_MS | n/a |

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
