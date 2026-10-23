# Skischul-Management

Back-office app for ski schools: bookings, students, instructors, grouping and
instructor planning. Built with Laravel so it can be maintained by any Laravel
developer, and prepared to run as SaaS for several schools.

The full plan (build order, data model, decisions) is in the planning document
shared with the owner. The user stories (US-01 … US-31) are referenced in code
comments and tests.

## Status

| Step | Content | Stories | State |
| --- | --- | --- | --- |
| 1 | Foundation: schools, login, roles, invitations, users | US-27, US-31 | **done** |
| 2 | School settings | US-01–06, US-08, US-28 | **done** |
| 3 | Price tables | US-07 | **done** |
| 4 | Students & instructors | US-17–21 | next |
| 5 | Booking (shop, cart, checkout, email) | US-09–13, US-29 | |
| 6 | Booking list | US-14–16 | |
| 7 | Grouping & instructor assignment | US-22–24 | |
| 8 | Dashboard & calendar | US-25, US-26 | |

## Stack

- PHP 8.3, Laravel 12, Livewire 3, Blade
- Tailwind CSS 4 (via Vite); fonts are bundled locally (no Google Fonts, GDPR)
- MySQL 8 in production, SQLite for local development and tests
- Pest 3 for tests, Pint for code style

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Demo logins (seeded, local only): `owner@example.com` and `staff@example.com`,
password `password`. Emails go to `storage/logs/laravel.log` (`MAIL_MAILER=log`).

Run the tests and the code-style check:

```bash
./vendor/bin/pest
./vendor/bin/pint --test
```

## Onboarding a school

There is no self-registration. A school and its first owner are created on the
command line; the owner gets an email to set their password:

```bash
php artisan school:create "Skischule Muster" anna@example.com "Anna Muster"
```

The owner then invites staff under **Users**.

## Architecture notes

### Multi-tenancy (SaaS-ready)

- Single database. Every tenant-owned table has a `school_id`.
- Models use the `App\Models\Concerns\BelongsToSchool` trait: a global scope
  filters queries to the current school, and new records get its id.
- `App\Support\CurrentSchool` holds the current school. The
  `SetCurrentSchool` middleware sets it from the logged-in user on every web
  and Livewire request.
- Console commands and queued jobs have **no** current school, so queries are
  not filtered there: use `CurrentSchool::runAs($school, fn () => …)`.
- `tests/Feature/Tenancy` proves one school cannot see or change another's
  data. Add a case there for every new tenant model.
- A user belongs to exactly one school for now; email addresses are unique
  across all schools.

### Roles and authorization

- `App\Enums\Role`: `owner` (full access) and `staff`.
- `Gate::define('admin')` guards owner-only areas: school settings, price
  tables, users, instructor financial data (US-27).
- Policies (`app/Policies`) are checked on the route **and** inside each
  Livewire action, because Livewire actions can be called directly.

### Authentication

- Login: `App\Http\Requests\Auth\LoginRequest` gives one generic error for
  unknown email, wrong password or deactivated account, and locks after 5
  failed attempts per email + IP.
- Sessions expire after 60 minutes of inactivity (`SESSION_LIFETIME`).
- Invitations use a second password broker, `invites`, valid for 72 hours
  (`config/auth.php`). Invited users have `password = null` until they accept.
- Deactivating a user deletes their database sessions and the
  `EnsureUserIsActive` middleware logs them out on the next request. Users
  are never deleted, so history keeps pointing at them.
- A school always keeps at least one active owner (`App\Actions\Users\ManageUser`).

### School settings (step 2)

- Owner-only pages under `/settings`, one Livewire component each. The simple
  lists share `App\Livewire\Settings\ListSettings` (add, inline edit, delete,
  reorder). `RequiresAdmin` re-checks the owner role on every Livewire request.
- New schools get default timeslots, sports, skill levels and age groups from
  `App\Actions\Schools\CreateSchoolDefaults`.
- `App\Domain\Calendar\SchoolCalendar` is the single place for season and
  closed-day rules (US-01, US-30): a course may only start on an operating
  day, keeps its calendar window, gets no lesson on closed days inside it, and
  may not run past the season end. Without a season nothing is bookable.
- Deleting a sport, level, age group, meeting point, add-on or voucher goes
  through `GuardsDeletion::deleteIfUnused()`. Each model's `usages()` list is
  empty for now and gets filled as price tables (step 3), students (step 4)
  and bookings (step 5) are added.
- Money: `App\Support\Money` parses "12,50", "12.50", "1.234,50" into cents.
  Voucher percentages are stored as hundredths (1000 = 10 %).
- Dates: SQLite stores `date` columns with a time part, so query them with
  `whereDate()`.

### Price tables (step 3)

- `price_tables` + pivots to sports, age groups, skill levels (the pivots use
  `restrictOnDelete`, so the database also refuses to delete a setting in use),
  `price_table_time_options` (which slots are offered, min hours for
  private/guiding) and `price_table_prices` (one row per grid cell).
- Time options store the timeslot **key**, not copied times, so editing a
  course time changes every price table (US-06, US-30).
- `App\Domain\Pricing\PriceTableForm` is the form shape, `PriceTableValidator`
  holds every rule (incl. the switchable full-day rule and "each start day
  must leave room for the minimum days before the end day"), `SavePriceTable`
  stores it.
- `PriceCalculator` (used from step 5): group = price per participant for the
  number of **lesson** days × participants (closed days are not charged, the
  end date stays). If closed days leave fewer lesson days than the table's
  shortest row, the shortest row's day rate is used per lesson day.
  Private/guiding = base price for the hours + extra fee × further participants.
- `SetCurrentSchool` runs **before** route-model binding (see
  `bootstrap/app.php`), so `/settings/price-tables/{id}/edit` can never load
  another school's record. A test proves it.

### Language

German (default) and English. UI strings are English keys in `__()`, German
lives in `lang/de.json` and `lang/de/*.php`. Each user picks a language under
**My account**; otherwise the school's default applies. Dates and times are
stored in UTC and shown in the school's time zone.

### Conventions

- Business logic lives in `app/Actions` (and later `app/Domain` services such
  as the booking validator and price calculator), not in Livewire components.
- Money will be stored as integer cents.
- Sensitive fields (IBAN, social security number) will use the `encrypted` cast.
- Bookings will carry `source` (`office`, `book2ski`, `checkyeti`) and
  `external_id` for the later platform imports.

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, MySQL credentials, a real mail
  service (`MAIL_*`), `SESSION_DRIVER=database`, HTTPS.
- Run `php artisan migrate --force`, `npm run build`, and a queue worker if
  `QUEUE_CONNECTION` is not `sync`.
- Daily database backups.
