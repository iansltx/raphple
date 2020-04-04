# Raphple

A mobile phone based online raffle system using PHP, MySQL, AMPHP components, and Twilio, Nexmo, or SignalWire. You can
see a live implementation (using Twilio) as http://raphple.com.

## Setup (non-Docker)

For higher performance, consider installing the `libevent`, `ev`, or `php-uv` extensions. The included Dockerfile
uses `uv`.

1. Set up an SMS provider account and point it to the appropriate webhook, or use a dummy account. See Setup (SMS) for
more details.
2. Download Composer and install dependencies.
3. Import /db/schema.sql into a MySQL 5.6+ database.
4. Set $_ENV vars for your SMS provider, as well as DB_HOST, DB_USER, DB_PASSWORD and DB_NAME vars to 
connect to your database, and APP_PORT for how you want to access the web server (defaults to port 80).
5. Run `php public/index.php` (you'll need sudo if you're keeping the port 80 default) or, for clustered operation,
`vendor/bin/cluster public/index.php`.

## Setup (docker-compose)

After completing step 1 of the above, copy docker-compose.override-example.yml to docker-compose.override.yml and
set the appropriate environment variables for the SMS provider you're using. Then run `docker-compose build` and
`docker-compose up`. The web server will be available at port 80, and is managed by amphp/cluster for parallelism.

If you want to reflect code updates without a rebuild (though you'll still need to restart the container due to how
amphp works), run `composer install` on your local directory, then volume-mount that directory into `/var/app` in the
web container.

## Setup (SMS)

Raphple supports Twilio, Nexmo, and SignalWire directly (no need for SDKs or curl). To use either, set the
appropriate env vars for your provider, and point that provider's webhook endpoint at the proper URL. Phone number
formats also vary between providers, but as long as you only use one at a time that doesn't matter.

In addition to the below env vars, set `PHONE_NUMBER` to the number you'll use to receive (and send) raffle-related
texts.

You can choose instead to stub out outbound SMSes. Either of the two webhooks will still respond, but no SMSes will
be sent. As part of the dummy SMS provider, an arbitrary wait, in milliseconds, is injected on each fake message
send event (you can set this to zero if you want). Messages that would have been sent will be logged to STDERR. Unlike
the Twilio and Nexmo handlers, the dummy handler blocks execution across the board, otherwise you wouldn't see the
delay actually happening.

| Provider | Env Vars | Webhook Endpoint |
| --- | --- | --- |
| Twilio | TWILIO_SID, TWILIO_TOKEN | /twilio |
| Nexmo | NEXMO_KEY, NEXMO_SECRET | /nexmo |
| SignalWire | SIGNALWIRE_SPACE, SIGNALWIRE_PROJECT, SIGNALWIRE_TOKEN | /twilio (yes, response format is identical) |
| Dummy | DUMMY_SMS_WAIT_MS | n/a |

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
