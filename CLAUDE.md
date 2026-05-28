# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**First-time setup:**
```bash
composer run setup
```

**Start all dev services (Laravel, Vite, queue worker, log viewer) concurrently:**
```bash
composer run dev
```

**Run all tests:**
```bash
composer run test
```

**Run a single test file:**
```bash
php artisan test tests/Feature/ExampleTest.php
```

**Run a specific test by name:**
```bash
php artisan test --filter=test_name
```

**Lint PHP (Laravel Pint):**
```bash
./vendor/bin/pint
```

**Frontend build:**
```bash
npm run build   # production
npm run dev     # watch mode (included in composer run dev)
```

## Architecture

This is a Laravel 12 + React application for a fitness platform. The frontend is not yet React-ified — `resources/js/app.js` currently only bootstraps Axios. React components should be added there and bundled via Vite.

**Stack:**
- Laravel 13 / PHP 8.3
- React (to be wired into `resources/js/`)
- Tailwind CSS v4 (via `@tailwindcss/vite` plugin — no `tailwind.config.js` needed)
- Vite 8 with `laravel-vite-plugin`
- SQLite in-memory for tests; configure `.env` for dev DB

**Domain models and their relationships:**

```
User
 ├── hasMany ClassBooking
 ├── belongsToMany GymClass (through class_bookings)
 └── hasMany Activity

GymClass
 └── hasMany ClassBooking

ClassBooking          ← a user's reservation for a specific GymClass session
 ├── belongsTo User
 ├── belongsTo GymClass
 └── hasOne Activity   ← optional: the GPS/workout recording for that session

Activity              ← GPS/workout data; standalone or linked to a class booking
 ├── belongsTo User
 └── belongsTo ClassBooking (nullable)
```

A `ClassBooking` prevents double-booking via a unique constraint on `(user_id, gym_class_id)`. `GymClass::isFull()` checks `max_capacity` against booking count.

**Key files:**
- `app/Models/` — all four models above
- `database/migrations/` — three domain migrations (gym_classes → class_bookings → activities, in dependency order)
- `routes/web.php` — only a welcome route so far; API routes go in `routes/api.php` (create if needed)
- `resources/js/app.js` — React entry point (add component mounting here)
- `resources/views/welcome.blade.php` — single Blade shell; React will render into it

**Testing:** PHPUnit 12, in-memory SQLite. Tests live in `tests/Unit/` and `tests/Feature/`.
