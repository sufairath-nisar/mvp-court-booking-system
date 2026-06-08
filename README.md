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
| Method | Endpoint             | Description                          |
|--------|----------------------|--------------------------------------|
| GET    | `/admin/slots`       | List slots (filter: court_id, date)  |
| POST   | `/admin/slots`       | Create slot (overlap-protected)      |
| POST   | `/admin/slots/bulk`  | Bulk-generate slots for a date range (see below) |
| PUT    | `/admin/slots/{id}`  | Update slot                          |
| DELETE | `/admin/slots/{id}`  | Delete slot                          |

**Bulk slot generation** — instead of creating each slot by hand, one request builds
an entire schedule across `[start_date, end_date]`. Existing/overlapping slots are
skipped (not errored); the response returns `created_count` and `skipped_count`.
Days-of-week use 0=Sun … 6=Sat.

*Flat form* — same hours every day (optionally restricted to `days_of_week`):
```json
POST /api/admin/slots/bulk
{
  "court_id": 1,
  "start_date": "2026-07-01",
  "end_date": "2026-07-07",
  "daily_start_time": "09:00",
  "daily_end_time": "21:00",
  "slot_duration": 60,
  "days_of_week": [1, 2, 3, 4, 5]
}
```

*Per-day form* — different hours/durations per day via `schedules` (takes precedence
over the flat form). e.g. Mondays 09:00–21:00 but Fridays only 08:00–12:00:
```json
POST /api/admin/slots/bulk
{
  "court_id": 1,
  "start_date": "2026-07-01",
  "end_date": "2026-07-31",
  "schedules": [
    { "days_of_week": [1], "start_time": "09:00", "end_time": "21:00", "slot_duration": 60 },
    { "days_of_week": [5], "start_time": "08:00", "end_time": "12:00", "slot_duration": 60 }
  ]
}
```
Each schedule steps from `start_time` to `end_time` in `slot_duration`-minute blocks
on its `days_of_week`; omit `days_of_week` to apply to every day in the range.

### Consumer — Booking (`role:consumer`)
| Method | Endpoint                                   | Description                          |
|--------|--------------------------------------------|--------------------------------------|
| GET    | `/consumer/courts`                         | View available (active) courts       |
| GET    | `/consumer/courts/{court}/available-slots` | View available slots for a court     |
| POST   | `/consumer/bookings`                       | Book a slot                          |
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

### Create a slot (admin)
`POST /api/admin/slots`
```json
{ "court_id": 1, "date": "2026-06-10", "start_time": "08:00", "end_time": "09:00" }
```
Overlapping a court's existing slot → **422** `"This time slot overlaps an existing slot for the court."`

### Book a slot (consumer)
`POST /api/consumer/bookings`
```json
{ "slot_id": 5 }
```
**201**
```json
{
  "success": true,
  "message": "Booking confirmed successfully.",
  "data": {
    "id": 1, "user_id": 2, "court_id": 1, "slot_id": 5,
    "booking_date": "2026-06-10", "status": "booked"
  }
}
```
Already booked → **422** `"This slot has already been booked."`

### Cancel a booking
`PATCH /api/consumer/bookings/1/cancel` → frees the slot.
After slot start → **422** `"Bookings can only be cancelled before the slot start time."`

---

## Business rules enforced

- **Admins only via seeder** — no admin registration endpoint.
- **Slot overlap prevention** — interval check in `SlotService` (`start < existing.end AND end > existing.start`).
- **No double booking** — `DB::transaction` + `lockForUpdate` on the slot row, then check/set `is_booked` atomically.
- **Cancel window** — only before the slot's start datetime; cancelling re-opens the slot.
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
