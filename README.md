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
| PUT    | `/admin/slots/{id}`  | Update slot                          |
| DELETE | `/admin/slots/{id}`  | Delete slot                          |

### Admin — Schedule & slot generation (`role:admin`)
A court has a **weekly schedule** (recurring open/close hours per day-of-week) plus
**date exceptions** (e.g. Eid/holidays) that override or close specific dates. Concrete
bookable slots are then **generated** from that template for any date range — so the
admin defines the hours once instead of creating each slot by hand.

| Method | Endpoint                                          | Description                                   |
|--------|---------------------------------------------------|-----------------------------------------------|
| GET    | `/admin/courts/{court}/schedule`                  | View the weekly schedule                      |
| PUT    | `/admin/courts/{court}/schedule`                  | Set the weekly schedule (one row per weekday) |
| GET    | `/admin/courts/{court}/schedule-exceptions`       | List date exceptions                          |
| POST   | `/admin/courts/{court}/schedule-exceptions`       | Add an exception (close or override hours)    |
| DELETE | `/admin/courts/{court}/schedule-exceptions/{id}`  | Remove an exception                           |
| POST   | `/admin/courts/{court}/generate-slots`            | Generate slots for a date range               |

`day_of_week`: 0=Sun … 6=Sat. Generation applies the weekly template, with any exception
for a date overriding it (a closed date produces no slots). Overlapping slots are skipped.

```json
// 1) Set the weekly schedule (Mon–Fri 09:00–21:00, Sat 08:00–12:00)
PUT /api/admin/courts/1/schedule
{
  "schedule": [
    { "day_of_week": 1, "open_time": "09:00", "close_time": "21:00", "slot_duration": 60 },
    { "day_of_week": 2, "open_time": "09:00", "close_time": "21:00", "slot_duration": 60 },
    { "day_of_week": 6, "open_time": "08:00", "close_time": "12:00", "slot_duration": 60 }
  ]
}

// 2) Eid closure (or override hours for a date)
POST /api/admin/courts/1/schedule-exceptions
{ "date": "2026-10-12", "is_closed": true, "reason": "Eid" }
// ...or a half-day override:
{ "date": "2026-10-13", "open_time": "09:00", "close_time": "12:00", "reason": "Eid half day" }

// 3) Generate concrete slots (template + exceptions applied). start_date/end_date are
//    OPTIONAL — omit them to default to a rolling horizon (today → +30 days). Pass
//    exclude_dates for one-off holidays, or preview:true to see counts WITHOUT saving.
POST /api/admin/courts/1/generate-slots
{}                                                  // generate the next 30 days from the schedule

POST /api/admin/courts/1/generate-slots
{ "exclude_dates": ["2026-10-15"] }                 // next 30 days, skipping one holiday

POST /api/admin/courts/1/generate-slots
{ "start_date": "2026-10-12", "end_date": "2026-10-18" }
// -> { "created_count": 52, "skipped_count": 0 }

POST /api/admin/courts/1/generate-slots
{ "start_date": "2026-10-12", "end_date": "2026-10-18", "preview": true }
// -> { "preview": true, "would_create": 52, "would_skip": 0, "by_date": { "2026-10-13": 12, ... } }
```

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
