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
| 4 | Students & instructors | US-17–21 | **done** |
| 5 | Booking (shop, cart, checkout, email) | US-09–13, US-29 | **done** |
| 6 | Booking list | US-14–16 | **done** |
| 7 | Grouping & instructor assignment | US-22–24 | **done** |
| 8 | Dashboard & calendar | US-25, US-26 | **done** |

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

The demo bookings are in January 2027. To see them on the dashboard, set
`DEMO_TODAY=2027-01-11` in `.env` (only works with `APP_ENV=local`).

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
  day; closed days are skipped as if they did not exist (a 3-day course
  starting Tuesday with Thursday closed runs Tue, Wed, Fri); it never runs
  past its latest end day (price table) or the season end, so a week with a
  closed day may have fewer lessons. Without a season nothing is bookable.
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

### Students and instructors (step 4)

- Students: `App\Livewire\Forms\StudentForm` (a Livewire Form object) holds
  fields and rules, so the booking screen in step 5 can reuse it as-is.
  Archiving sets `archived_at`; archived students stay in bookings. Search
  covers name, phone (ignoring spaces, `/` and `-`) and accommodation.
  A same-name-and-birthday match is shown as a non-blocking hint.
- Instructors: IBAN and social security number use the `encrypted` cast and
  are in `$hidden`; IBANs are checked with mod-97 (`App\Rules\Iban`). Staff
  can see the list (for planning) but no financial data and no edit links;
  editing is owner only. `instructor_sport` records which sports someone
  teaches (plan question 13, used as a warning in step 7).
- The social security number is only format-checked (4–30 characters) so
  schools outside Austria work too.

### Booking (step 5)

- `App\Domain\Booking\BookingRules::check()` holds every booking rule and
  returns a `Quote` (lesson dates, price, errors, age warnings). It runs live
  in the configurator, for every cart line, and again inside `PlaceOrder`.
  - Group: allowed start weekday, operating day, days within min/max, lessons
    = next N operating days but never past the price table's end weekday;
    price for the number of lessons × participants.
  - Private/guiding: open day, start time + hours inside the private window;
    the time option (morning / afternoon / full day) follows from start and
    length (plan question 8); min hours of that option; base + extra fee.
  - Participants: skill level must match (hard); age outside the age groups
    needs an explicit "book anyway" (stored as `age_override`).
- `Cart` lives in the session per school and stores choices only; prices are
  always recalculated.
- `PlaceOrder` locks the school row (booking numbers `YYYY-NNNN`, voucher
  limits), re-checks everything, writes booking, lines, participants,
  `lesson_days`, add-ons and a history entry in one transaction, then sends
  the confirmation. A failing email is logged as `email_failed` and never
  rolls back the booking (US-29).
- Bookings have `source` + `external_id` (unique per school) for the later
  book2ski / CheckYeti imports.
- Vouchers compare calendar dates in the school's time zone (a bug found by
  the tests: early-morning checks could accept an expired voucher).

### Booking list (step 6)

- `/bookings`: active bookings (not handled, not cancelled) ordered by first
  course date; a second, paginated table holds handled and cancelled ones.
  Each booking is a `<tbody>` with its courses and students underneath.
- `ChangeBookingStatus`: paid/unpaid, handled, cancel (kept and marked, the
  voucher use is given back), delete (owners only, `BookingPolicy`).
  Every change is a `booking_events` row with the user.
- Editing (US-16) reuses the booking screen: `ManagesBookings::editBooking()`
  loads the booking into its own cart (`Cart::forBooking($id)`), the shop and
  checkout run with `?booking={id}`, and `UpdateBooking` re-checks every rule
  and rewrites the courses. The voucher stays; its discount is recalculated
  without counting a second use.
- Edits keep grouping: `BookingWriter::writeContent()` remembers group places
  and private-lesson instructors before rewriting and re-attaches them to the
  matching new lesson days (see step 7).

### Grouping and instructor assignment (step 7)

- `/grouping`: day view (plan one day) and week view (counts per day), with a
  time navigation bar (US-23). The week starts on the school's
  `week_starts_on` setting.
- **Counting** (owner decision): `App\Domain\Grouping\Grouping::cohorts()`
  puts every group-course lesson day of a non-cancelled booking into a
  `Cohort` = date × timeslot × sport × age group. The age group is the price
  table's age group that fits the student's age (else its youngest one).
  Instructors needed = ceil(students ÷ the price table's max participants).
  Skill levels are only shown as information inside a cohort.
- **Groups** (`groups`, `group_members`) exist per day. `GroupManager`:
  `autoSplit` (as many groups as instructors needed, filled by skill level),
  `copyPreviousDay` (looks back up to 7 days, copies names, meeting points,
  members who have a lesson today and the instructor if still free),
  `moveStudent` (only between groups of the same day, time and sport, so
  students can move between age groups), `deleteGroup`.
- **Instructors**: groups have `instructor_id`, private lessons have
  `lesson_days.instructor_id` (plus `meeting_point_id`).
  `AssignmentGuard::check()` refuses inactive instructors and overlapping
  times (back-to-back is fine) and *warns* when the instructor does not teach
  the sport; the screen asks "assign anyway". Step 8 adds calendar absences
  (sick leave …) to the overlap check (done).
- Cancelling a booking removes its students from groups and clears private
  lesson instructors.

### Dashboard and calendar (step 8)

- `/dashboard` is the Livewire component `App\Livewire\Dashboard`.
- Numbers (US-25), `App\Domain\Dashboard\WeekNumbers`: bookings with at
  least one lesson in the school's week (`week_starts_on`, default Sunday)
  or today; cancelled ones never count. Paid/unpaid of this week, the open
  amount and a list of unpaid bookings linking to each booking. The black
  tile links to the booking screen.
- Calendar (US-26), `App\Domain\Calendar\CalendarFeed`: reads groups,
  private lessons and not-yet-grouped students **live** from the same tables
  Grouping uses, so there is only one assignment. Changing the instructor in
  the calendar calls `GroupManager` (same rules and warnings). Day, week and
  month view; filters by course (price table) and instructor. `DayLayout`
  puts overlapping entries side by side.
- Standalone entries: `calendar_events` (title, from/until day, optional
  times = all day if empty, optional instructor). `SaveCalendarEvent`
  refuses an entry for an instructor who teaches at that time;
  `AssignmentGuard` refuses a course during the instructor's entry. Entries
  without an instructor block nobody. They never appear in Grouping.

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
