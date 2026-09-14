# Local setup

Run demgem on your own machine for development.

Requirements: PHP 8.4, Composer, Node 20+, PostgreSQL 17+.

```sh
composer install
cp .env.example .env
php artisan key:generate
# Point DB_* at your Postgres, then:
php artisan migrate
php artisan demgem:import-srd   # the SRD compendium; skip it for system-agnostic campaigns
php artisan storage:link
npm install && npm run build
```

For the live table locally, run a queue worker and Reverb beside the app:

```sh
php artisan queue:work
php artisan reverb:start
```

`php artisan dev` runs both for you, along with Vite. Without them, screens fall back to their sixty-second poll. Reminder emails also need `php artisan schedule:work`, and go to the log until `MAIL_MAILER` is a real mailer.

Optional demo world with a GM and a player:

```sh
php artisan db:seed --class=DemoCampaignSeeder
```

It creates `dev@demgem.test` and `tobin@demgem.test`, both with the password `password`.
