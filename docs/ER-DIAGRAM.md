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
                                     │ date                    │
                                     │ start_time              │
                                     │ end_time                │
                                     │ is_booked               │
                                     │ timestamps              │
                                     └─────────────────────────┘
```

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
- Slot overlap is prevented in `SlotService` (interval check per court/date).
- Double booking is prevented in `BookingService` via a transaction that locks the slot
  row (`lockForUpdate`) before checking/setting `is_booked`.
- All FKs `ON DELETE CASCADE`.

## Indexes (performance)

- `users.role`
- `courts.sport_type`, `courts.is_active`
- `court_slots (court_id, date)`, `court_slots.is_booked`
- `bookings (user_id, status)`, `bookings.status`
