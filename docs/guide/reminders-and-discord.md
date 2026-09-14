# Reminders, Discord, and the calendar feed

Reminder emails, the mailer, the scheduler, the Discord webhook, and the per-user calendar feed.

A GM turns on a reminder email in campaign settings: a day, two days, or a week before each session with a date. Every member who wants one gets one, in the campaign's timezone, with a link back to the session to say whether they are coming. A member who said no is not reminded, and every member has their own switch on the members page.

**With `MAIL_MAILER=log`, which is the default, a reminder is written to the log and nobody receives it.** Set a real mailer before a GM turns reminders on. In `.env.docker`:

```sh
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=you
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=demgem@example.com
```

The compose stack runs the scheduler for you. Outside Docker, run `php artisan schedule:work` beside the queue worker, or add `php artisan schedule:run` to cron every minute. To see what would go out right now, run `php artisan demgem:send-reminders` by hand.

**Discord.** A GM pastes a channel's webhook URL into campaign settings and sends a test message. From then on the channel gets one line when a recap is published and one when a reminder goes out: the campaign, the session, and a link. Never the recap itself. The URL is stored encrypted and never exported, and the server only ever posts to `discord.com`; any other address is refused when it is pasted.

Every session you can see is also available as a calendar feed. Get the link from your profile and subscribe to it in Google Calendar, Apple Calendar, or Outlook; it covers every campaign you belong to, and the times land in your own timezone. The feed carries the session's number, title, and campaign, and never its prep or recap.
