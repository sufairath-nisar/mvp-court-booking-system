# ER Diagram – Court Booking System

## Entities & Relationships

```
┌─────────────────────────┐
│         users           │
├─────────────────────────┤
│ id (PK)                 │
│ name                    │
│ email (UNIQUE)          │
│ password                │
│ role  ('admin'|'consumer')
│ timestamps              │
└───────────┬─────────────┘
            │ 1
            │
            │ N
┌───────────┴─────────────┐         ┌─────────────────────────┐
│        bookings         │  N    1 │         courts          │
├─────────────────────────┤────────►├─────────────────────────┤
│ id (PK)                 │         │ id (PK)                 │
│ user_id (FK → users)    │         │ name                    │
│ court_id (FK → courts)  │         │ location                │
│ slot_id (FK → slots)    │         │ sport_type              │
│ booking_date            │         │ hourly_rate             │
│ status ('booked'|       │         │ is_active               │
│         'cancelled')    │         │ timestamps              │
│ timestamps              │         └───────────┬─────────────┘
└───────────┬─────────────┘                     │ 1
            │ N                                  │
            │                                    │ N
            │ 1                      ┌───────────┴─────────────┐
            └───────────────────────┤      court_slots        │
                                     ├─────────────────────────┤
                                     │ id (PK)                 │
                                     │ court_id (FK → courts)  │
                                     │ day_of_week  (0–6)      │  ← RECURRING (no date)
                                     │ start_time              │
                                     │ end_time                │
                                     │ timestamps              │
                                     └─────────────────────────┘
```

A slot is a **recurring weekly window** (no date / no is_booked). Two supporting tables
drive it: **`court_schedules`** (court_id, day_of_week, open_time, close_time,
slot_duration — the weekly template that generates the slots) and
**`court_schedule_exceptions`** (court_id, date, is_closed, open_time, close_time —
holiday closures / hour overrides applied at availability & booking time). The booked
**date** lives on `bookings.booking_date`.

## Relationship summary

| Relationship              | Type        | Notes                                             |
|---------------------------|-------------|---------------------------------------------------|
| User → Bookings           | 1 : N       | A consumer has many bookings                      |
| Court → CourtSlots        | 1 : N       | A court has many time slots                        |
| Court → Bookings          | 1 : N       | Denormalised FK for fast court-level reporting     |
| CourtSlot → Bookings      | 1 : N       | A slot may be booked, cancelled, re-booked over time |
| Booking → User/Court/Slot | N : 1 each  | Each booking references one of each                |

## Key constraints

- `users.email` — **UNIQUE**.
- `court_slots (court_id, day_of_week, start_time)` — **UNIQUE** (one recurring window per slot).
- Double booking is prevented in `BookingService` via a transaction that locks existing
  bookings for the same `(slot_id, booking_date)` before inserting.
- All FKs `ON DELETE CASCADE`.

## Indexes (performance)

- `users.role`
- `courts.sport_type`, `courts.is_active`
- `court_slots (court_id, day_of_week, start_time)` (unique)
- `bookings (user_id, status)`, `bookings (slot_id, booking_date)`, `bookings.status`
