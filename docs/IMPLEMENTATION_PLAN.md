# SAS – Scholarships Application Services: Implementation Plan

Source of truth: `Requirements.md` v1.1 (21 Oct 2026). The brand name is "Scholarships Application Services" (the owner replaced the original "Scholorship" spelling with the logo spelling on 2026-10-23) and is used
everywhere user-visible. Compact brand: **SAS**.

Scope of this build: **R1 (public launch)**. R2 (paid services, bookings, partner portal) and R3 (feeds, advanced matching)
are *not* exposed as working features; only extensible data shapes are kept in mind (money in minor units is deferred to R2).

## Stack (verified in a clean install, see DECISIONS.md D-01)

Laravel 13.38 · PHP 8.4 · Livewire 4.4 · Filament 5.10 · Tailwind CSS 4 · Vite 8 · PHPUnit 12 · Pint.
Target production DB MySQL 8.4; dev/test default SQLite (migrations are portable). Redis/Horizon, S3 and ClamAV are
production adapters configured through `.env` (not available in the local sandbox).

## Module layout (modular monolith)

`app/Domain/{Scholarship,Identity,StudentWorkspace,Documents,Editorial,Services,Communication,Reporting}` hold actions,
services and enums. Controllers / Livewire components / Filament pages call the same actions; jobs call the same actions.
Models live in `app/Models`. Policies in `app/Policies`.

## Dependency-ordered backlog

| # | Increment | Requirement IDs | Status |
|---|-----------|-----------------|--------|
| 1 | Foundation: auth, email verification, roles/permissions, audit, rate limits, migrations | AUTH-01..05, SEC-01, SCHEMA-07 | see PROGRESS.md |
| 2 | Scholarship data: taxonomies, programmes, cycles, routes, deadlines, evidence, workflow, import | DATA-01..04, DEAD-01..04, SCHEMA-01..03, IMP-01..03, ADM-02 | |
| 3 | Public site: homepage, directory, detail, destinations, services, resources, contact, SEO | PUB-01..07, DIR-01..07, SEO-01..02 | |
| 4 | Student: profile, saves, application plans + checklist snapshots, document vault, matching | STU-01..03, APP-01..02, FILE-01..03, MATCH-01..02 | |
| 5 | Services: enquiries, consultation requests, assignment, threads, reminders & notifications | SVC-01..05 (enquiry-led), BOOK-01, ORD-01, NOT-01..03, COM-01 | |
| 6 | Hardening: security headers, accessibility, performance, launch runbooks | SEC-*, A11Y-01, NFR-*, OPS-* | |

## Out of scope for R1 (disabled, not shown)

Checkout, payments, invoices, refunds, slot booking, partner self-service portal, feeds, search engine, WhatsApp/SMS,
calendar integrations, automatic application submission, scraping.

## Release gate for R1

All UAT-01..UAT-10 and UAT-14/15 equivalents covered by automated tests where feasible; remaining items (UAT-11..13,
UAT-16 and load/restore drills) belong to R2 or to infrastructure and are listed in PROGRESS.md as not executed.
