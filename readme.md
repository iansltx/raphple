# Raphple

A mobile phone based online raffle system using PHP, MySQL, Aerys (from AMPHP) and Twilio or Nexmo. You can see a live
implementation (using Twilio) as http://raphple.com.

## Setup (non-Docker)

0. For higher performance, consider installing the `ev` or `php-uv` extensions. See the Dockerfile for how to install
`php-uv`, assuming a Debian-based system.
1. Download Composer and install dependencies.
2. Set up an SMS provider account (Twilio or Nexmo) and point it to the appropriate webhook, or use a dummy account.
See Setup (SMS) for more details.
3. Import /schema.sql into a MySQL 5.6+ database.
4. Set $_ENV vars for your SMS provider, as well as DB_HOST, DB_USER, DB_PASSWORD and DB_NAME vars to 
connect to your database, and APP_PORT for how you want to access the web server.
5. Run `vendor/bin/aerys -d -c public/index.php`

Note that the above command starts the server in debug (verbose) mode. Remove the `-d` flag to start in non-debug mode.

## Setup (Docker)

You'll still need to do steps 2-4, though for step 5 you'll supply env vars to the container. Once you point your 
container to your database you'll have everything you need.

*NOTE:* Port must be what you actually access the container on, and must be explicitly declared in addition
to Docker port-forwarding.

For development, mount a volume with your code to `/var/app`. Otherwise your code stay at whatever state it was when you
built your container.

So for a dev build running in the foreground on port 8080 with a dummy SMS wait of 500ms you'd run

```bash
docker build . -t raphple-aerys
docker run -p 8080:8080 -v "$PWD":/var/app -e DUMMY_SMS_WAIT_MS=500 \
-e DB_HOST=hostname -e DB_USER=user -e DB_PASSWORD=password -e DB_NAME=db -e APP_PORT=8080 raphple-aerys
```

*NOTE:* Unlike a standard PHP application, once the container is running, modifying code, even via a volume mount, won't
change what runs; once the web server reads in a given file, it's in memory until the server process/container is
terminated.

The container starts in debug mode by default; set a custom entrypoint to start in non-debug mode.

## Setup (SMS)

Raphple now supports Twilio and Nexmo, and requires libraries for neither (doesn't require curl either). Just set the
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
| Dummy | DUMMY_SMS_WAIT_MS | n/a |

## Contributing

This app is licensed as BSD 2-clause. PRs welcome.
