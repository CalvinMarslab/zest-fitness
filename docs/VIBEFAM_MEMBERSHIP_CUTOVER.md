# VibeFam Membership Cutover Runbook

## Scope and safety boundary

- VibeFam remains the booking source of truth through **September 26, 2026**.
- Do not migrate September 18–26 bookings or waitlist entries. This workflow imports members and memberships only.
- Do not run the live import before September 27, 2026.
- Never hand-edit the generated package map.
- A successful dry run is evidence for review, not authorization to write production data.

## Required inputs

On September 27, export fresh, all-time reports from VibeFam:

1. Memberships Report CSV (required).
2. Complete Members CSV (recommended, so members with no package are included).

Keep the originals read-only. Record their filenames, export time, row counts, and SHA-256 hashes in the cutover record. Store all command output with the same record; it contains member PII and must not be placed in Git.

## Production rehearsal (no writes)

Run these commands from the deployed application release using the production database connection. The map generator reads the package catalog; `--dry-run` prevents it from writing a file. The importer dry run performs no database writes.

```bash
php artisan vibefam:generate-package-map --dry-run
php artisan vibefam:generate-package-map --output=/secure/path/vibefam-package-map.json
shasum -a 256 /secure/path/memberships.csv /secure/path/members.csv /secure/path/vibefam-package-map.json
php artisan vibefam:import \
  --memberships-csv=/secure/path/memberships.csv \
  --members-csv=/secure/path/members.csv \
  --package-map=/secure/path/vibefam-package-map.json \
  --dry-run | tee /secure/path/vibefam-dry-run.txt
```

Stop if any command exits non-zero. The importer blocks invalid identities, dates or credit values; unknown package names; missing or malformed map entries; map entries pointing at the wrong package; missing packages; unlimited packages with non-zero credits; and Limited Plan packages without the exact weekly cap of 2.

## Reconciliation and approval gate

Before approval, a second person must compare the dry-run report to the source exports and confirm:

- source row count, exact duplicate count, and distinct member count;
- every source package is classified and maps to the intended production package name and ID;
- active/expired subscription counts by package;
- total remaining finite credits and per-member finite credit balances;
- unlimited memberships have zero Zest credits and the correct expiry dates;
- members with both finite and unlimited entitlements are expected;
- zero-package members are accounted for;
- identity exceptions are resolved explicitly;
- the database backup/restore procedure has been tested and an immediate pre-import backup is scheduled;
- there are no booking or waitlist records in the import scope.

**Live-import approval must be explicit and recorded after reviewing the final September 27 dry-run artifacts.** Record approver, timestamp, CSV hashes, package-map hash, release commit, database target, and the accepted reconciliation totals. Without that record, do not answer the live confirmation prompt.

## Live import (only after recorded approval)

Take and verify the production backup immediately before the import. Then run the exact reviewed files and map:

```bash
php artisan vibefam:import \
  --memberships-csv=/secure/path/memberships.csv \
  --members-csv=/secure/path/members.csv \
  --package-map=/secure/path/vibefam-package-map.json
```

The command requires an interactive confirmation and writes the import in one database transaction. Source keys in `vibefam_import_map` prevent duplicate subscriptions and opening-balance transactions if the exact export is rerun.

## Post-import checks

Immediately compare production to the approved report:

- users created or matched;
- subscriptions by package, status, and expiry;
- per-member and total active finite credits;
- unlimited subscriptions with `credits_remaining=0`;
- one migration opening-balance transaction per imported finite subscription with a positive balance;
- import-map rows corresponding to subscriptions and opening balances;
- no new class bookings or waitlist rows;
- a small, approved sample of finite, unlimited, expired, and mixed-entitlement members in the UI.

Do not open Zest booking access until reconciliation passes.

## Failure and recovery

An exception during the command rolls back the whole import transaction. Preserve the error output and inspect the database before retrying. If the command succeeds but reconciliation fails, stop the cutover and do not attempt ad-hoc deletes or edits. Restore the verified pre-import backup or use a separately reviewed rollback procedure, then diagnose and repeat the dry-run and approval cycle with new artifact hashes.

The import map is part of idempotency state. Never delete or alter it to force a rerun without a reviewed recovery plan.
