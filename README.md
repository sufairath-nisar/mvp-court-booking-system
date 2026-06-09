# Court Booking System – API

A RESTful backend for booking sports courts, built with **Laravel 12 + MySQL** and **Laravel Sanctum** token auth. Role-based: **Admins** manage courts & slots; **Consumers** browse and book.

> Scope implemented: **Authentication**, **Court Management (Admin)**, **Slot Management (Admin)**, **Booking (Consumer)**.

---

## Tech Stack

| Layer    | Choice                          |
|----------|---------------------------------|
| Backend  | Laravel 12 (PHP 8.2+)           |
| Database | MySQL 8                         |
| Auth     | Laravel Sanctum (Bearer tokens) |
| Patterns | Controller → Service → Repository, Form Requests, API Resources, Enums |

---

## Architecture

```
HTTP Request
   │
   ▼
Route (routes/api.php)  ──►  auth:sanctum + role:{admin|consumer} middleware
   │
   ▼
Controller (thin)            validates via Form Request, formats via API Resource
   │
   ▼
Service (business logic)     transactions, overlap checks, double-booking guard
   │
   ▼
Repository (data access)     Eloquent queries behind an interface (swappable)
   │
   ▼
Model / MySQL
```

**Why these layers**
- **Controllers** stay thin — only HTTP concerns (request → service → response).
- **Services** own business rules (slot overlap, "no cancel after start", atomic booking).
- **Repositories** isolate persistence behind interfaces (Dependency Inversion), bound in `RepositoryServiceProvider`.
- **Form Requests** centralise validation; **API Resources** standardise output.
- **Enums** (`UserRole`, `BookingStatus`) make roles/statuses type-safe.

Every response uses one envelope:
```json
{ "success": true, "message": "...", "data": { ... } }
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

---

## Setup

### Prerequisites
- PHP 8.2+, Composer, MySQL 8

### Steps
```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure DB in .env (DB_DATABASE=court_booking, DB_USERNAME, DB_PASSWORD)
#    Create the database first:
#    mysql -u root -p -e "CREATE DATABASE court_booking;"

# 4. Migrate + seed (creates the admin user + demo courts/slots)
php artisan migrate --seed

# 5. Serve
php artisan serve   # http://localhost:8000
```

### Default admin (seeded — admins are NOT registerable via API)
```
email:    admin@courtbooking.test
password: Password123!
```
Override via `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before seeding.

---

## Authentication flow

1. `POST /api/auth/register` (consumer) or `POST /api/auth/login` → returns a `token`.
2. Send it on protected routes: `Authorization: Bearer {token}`.
3. `POST /api/auth/logout` revokes the current token.

---

## API Endpoints

Base URL: `http://localhost:8000/api`

### Auth
| Method | Endpoint          | Auth        | Description                       |
|--------|-------------------|-------------|-----------------------------------|
| POST   | `/auth/register`  | –           | Register a consumer               |
| POST   | `/auth/login`     | –           | Login (admin or consumer)         |
| GET    | `/auth/me`        | Bearer      | Current user                      |
| POST   | `/auth/logout`    | Bearer      | Revoke current token              |

### Admin — Courts (`role:admin`)
| Method | Endpoint              | Description        |
|--------|-----------------------|--------------------|
| GET    | `/admin/courts`       | List courts        |
| POST   | `/admin/courts`       | Create court       |
| GET    | `/admin/courts/{id}`  | Show court         |
| PUT    | `/admin/courts/{id}`  | Update court       |
| DELETE | `/admin/courts/{id}`  | Delete court       |
| POST   | `/admin/courts/{id}/image` | Upload court image (multipart, field `image`) |

### Admin — Slots (`role:admin`)
| Method | Endpoint             | Description                                            |
|--------|----------------------|--------------------------------------------------------|
| GET    | `/admin/slots`       | List slots (filter: court_id, day_of_week)             |
| POST   | `/admin/slots`       | **Re-sync slots** — regenerate from the court's schedule |
| PUT    | `/admin/slots/{id}`  | Update a single slot                                   |
| DELETE | `/admin/slots/{id}`  | Delete a single slot                                   |

Slots are **generated automatically** whenever the weekly schedule is saved (see below),
so `POST /admin/slots` is normally not needed — it just re-syncs a court's slots to its
current schedule on demand (idempotent). It does **not** create one slot at a time.

### Admin — Schedule & slot creation (`role:admin`)
A court has a **weekly schedule** (recurring open/close hours per day-of-week) plus
**date exceptions** (e.g. Eid/holidays) that override or close specific dates. Slots are
**generated automatically from the schedule** — the admin defines the hours once instead
of adding each slot by hand.

**Saving the schedule regenerates the slots** (in one transaction): new windows are
created, unchanged ones kept, and windows no longer in the schedule become "stale". Use
`PUT` to replace the whole week (one row per weekday — omitted days are removed), or
`PATCH` to change a single day without resending the others. A stale slot is **deleted if it has no bookings**, but if it has
an active booking it is **kept and deactivated** (`is_active = false`) instead — never
deleted, so an existing reservation is never destroyed. Deactivated slots disappear from
availability and can't be booked, but the booking that protected them stays valid.

| Method | Endpoint                                          | Description                                   |
|--------|---------------------------------------------------|-----------------------------------------------|
| GET    | `/admin/courts/{court}/schedule`                  | View the weekly schedule                      |
| PUT    | `/admin/courts/{court}/schedule`                  | Replace the **whole** weekly schedule (all days) |
| PATCH  | `/admin/courts/{court}/schedule`                  | Update **one** weekday, leaving the others as-is |
| GET    | `/admin/courts/{court}/schedule-exceptions`       | List date exceptions                          |
| POST   | `/admin/courts/{court}/schedule-exceptions`       | Add an exception (close or override hours)    |
| DELETE | `/admin/courts/{court}/schedule-exceptions/{id}`  | Remove an exception                           |
| POST   | `/admin/slots`                                    | **Re-sync (regenerate) slots** for a court    |

`day_of_week`: 0=Sun … 6=Sat. Saving the schedule slices each active weekday's open–close
window into fixed-duration recurring slots. Date exceptions are **not** baked into slots —
they apply at booking & availability time (a closed date has no availability; an
hour-override narrows it).

```json
// 1) Set the weekly schedule (Mon–Fri 09:00–21:00, Sat 08:00–12:00).
//    This ALSO generates/reconciles the court's recurring slots automatically.
PUT /api/admin/courts/1/schedule
{
  "schedule": [
    { "day_of_week": 1, "open_time": "09:00", "close_time": "21:00", "slot_duration": 60 },
    { "day_of_week": 2, "open_time": "09:00", "close_time": "21:00", "slot_duration": 60 },
    { "day_of_week": 6, "open_time": "08:00", "close_time": "12:00", "slot_duration": 60 }
  ]
}

// 1b) Update just ONE day (leaves the other days untouched). Also reconciles slots.
PATCH /api/admin/courts/1/schedule
{ "day_of_week": 1, "open_time": "07:30", "close_time": "20:30", "slot_duration": 60 }

// 2) Eid closure (or override hours for a date)
POST /api/admin/courts/1/schedule-exceptions
{ "date": "2026-10-12", "is_closed": true, "reason": "Eid" }
// ...or a half-day override:
{ "date": "2026-10-13", "open_time": "09:00", "close_time": "12:00", "reason": "Eid half day" }

// 3) Optional: re-sync slots to the current schedule on demand (idempotent).
//    No dates — slots recur weekly; the booked date lives on the booking.
POST /api/admin/slots
{ "court_id": 1 }
// -> { "created_count": 0, "existing_count": 18, "deactivated_count": 0, "deleted_count": 0 }
```

**Changing the hours later** re-runs the reconcile. Example: Monday is `09:00–11:00` and a
consumer has booked the `09:00–10:00` slot for one date; the admin shifts Monday to
`09:30–11:30`. The new `09:30–10:30` / `10:30–11:30` slots are created, the booked `09:00`
slot is deactivated (booking preserved), and for that booked date the new `09:30–10:30`
slot is **hidden** because its time overlaps the existing booking — only `10:30–11:30`
shows. Other dates with no conflict show all new slots.

Holidays/closures are **not** baked into slots — they're applied at **booking & availability**
time from the exceptions table (a closed date has no availability and can't be booked; an
hour-override narrows that date's availability).

### Consumer — Booking (`role:consumer`)
| Method | Endpoint                                   | Description                          |
|--------|--------------------------------------------|--------------------------------------|
| GET    | `/consumer/courts`                         | View available (active) courts       |
| GET    | `/consumer/courts/{court}/available-slots?date=YYYY-MM-DD` | Available slots for a court on a date |
| POST   | `/consumer/bookings`                       | Book a slot (`slot_id` + `booking_date`) |
| PATCH  | `/consumer/bookings/{id}/cancel`           | Cancel (before slot start only)      |

---

## Example requests / responses

### Register (consumer)
`POST /api/auth/register`
```json
{
  "name": "Jane Player",
  "email": "jane@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!"
}
```
**201**
```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "user": { "id": 2, "name": "Jane Player", "email": "jane@example.com", "role": "consumer" },
    "token": "1|abc123..."
  }
}
```

### Create a court (admin)
`POST /api/admin/courts`  · `Authorization: Bearer {admin_token}`
```json
{ "name": "Center Court", "location": "Block A", "sport_type": "tennis", "hourly_rate": 25.00, "is_active": true }
```

### Re-sync slots (admin) — regenerate recurring from the schedule
`POST /api/admin/slots`
```json
{ "court_id": 1 }
```
Slots are generated automatically when the schedule is saved; this endpoint just re-syncs
them to the current schedule on demand (idempotent). No dates. See "Schedule & slot
creation" above.

### Book a slot (consumer)
`POST /api/consumer/bookings`
```json
{ "slot_id": 5, "booking_date": "2026-10-12" }
```
The slot is a recurring window (e.g. Monday 9–10am); the consumer picks the **date**.
**201**
```json
{
  "success": true,
  "message": "Booking confirmed successfully.",
  "data": {
    "id": 1, "user_id": 2, "court_id": 1, "slot_id": 5,
    "booking_date": "2026-10-12", "status": "booked"
  }
}
```
Slot already booked — or **any slot whose time overlaps an existing booking** — for that
date → **422** `"This slot has already been booked for that date."`

### Cancel a booking
`PATCH /api/consumer/bookings/1/cancel` → frees the slot.
After slot start → **422** `"Bookings can only be cancelled before the slot start time."`

---

## Business rules enforced

- **Admins only via seeder** — no admin registration endpoint.
- **Slot overlap prevention** — interval check in `SlotService` (`start < existing.end AND end > existing.start`).
- **Schedule changes preserve bookings** — saving the weekly schedule reconciles slots; a
  stale slot with an active booking is **deactivated, never deleted** (`is_active = false`),
  so the `cascadeOnDelete` on `bookings.slot_id` can't destroy a reservation.
- **No double booking (time-based)** — `DB::transaction` + `lockForUpdate`; a booking is
  rejected if its slot's time window overlaps **any** active booking on that court+date,
  even one on a now-deactivated slot. Availability hides overlapping slots the same way.
- **Deactivated slots are unbookable** — booking an `is_active = false` slot is rejected.
- **Cancel window** — only before the slot's start datetime on its booked date; cancelling frees that slot for that date (it becomes available again).
- **Role isolation** — `role:admin` / `role:consumer` middleware on every protected route.

---

## Bonus features (optional extras)

Three optional enhancements, added without changing existing behaviour:

1. **Booking notifications** — creating or cancelling a booking fires a domain event
   (`BookingCreated` / `BookingCancelled`); a listener sends the consumer a mail
   notification (`BookingConfirmedNotification` / `BookingCancelledNotification`).
   The default `MAIL_MAILER=log` writes the email to `storage/logs/laravel.log`
   (no SMTP needed); switch to `smtp` in production.
2. **Rate limiting** — a named `api` limiter (in `AppServiceProvider`) is applied to
   every API route via `throttle:api` in `bootstrap/app.php`: 120 req/min per
   authenticated user, 30 req/min per guest IP. Responses carry `X-RateLimit-*`
   headers; over-limit requests get `429`.
3. **Court images** — admins can upload a court image:
   `POST /api/admin/courts/{id}/image` (multipart, field `image`; jpg/png/webp, ≤2 MB).
   Stored on the `public` disk; the path is saved in `courts.image_path` and exposed
   as `image_url` in the court response. Run `php artisan storage:link` once so the
   files are web-accessible.

## Postman

Import `docs/CourtBooking.postman_collection.json`. It includes folders for Auth / Admin / Consumer, and auto-captures the returned token into a `{{token}}` collection variable on login/register.

## ER Diagram

See `docs/ER-DIAGRAM.md`.
