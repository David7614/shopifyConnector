# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Structure

This is a monorepo with two separate applications:

- `front-app/` — Remix + React frontend (Shopify App, TypeScript, Prisma/MySQL)
- `public_html/` — Yii2 backend (PHP, XML feed generator, queue processor)

## Commands

### Frontend (`front-app/`)

```bash
npm run dev          # Start local dev (Shopify CLI tunnel + Vite)
npm run build        # Production build (remix vite:build)
npm run setup        # Init DB: prisma generate && prisma migrate deploy
npm run lint         # ESLint + Prettier checks
npm run deploy       # Deploy to Shopify
```

### Backend (`public_html/`)

```bash
composer install
php yii migrate

# Manual queue processing:
php yii xml-generator/prepare-queue          # Create daily queue entries (runs at 23:01)
php yii xml-generator/generate-products      # Fetch + store products from Shopify API
php yii xml-generator/generate-customers
php yii xml-generator/generate-orders

# Cron scripts (run every 10 min in production):
bash integration-bash-shopify-products.sh
bash integration-bash-shopify-customers.sh
bash integration-bash-shopify-orders.sh
```

### Backend tests (Codeception)

```bash
cd public_html
php vendor/bin/codecept run
php vendor/bin/codecept run unit
```

## Architecture

### System Purpose

Shopify merchants install this app. It syncs their product/customer/order data from the Shopify GraphQL API and generates XML feeds consumed by the SAMBAai marketing automation platform. Feeds are served at `/xml/{uuid}/{type}.xml`.

### Data Flow

```
Shopify Store → Shopify API (GraphQL v2025-07)
    → Yii2 queue processor → MySQL (product/customers/orders tables)
    → XML generator → files at modules/xml_generator/src/feeds/{type}/{uuid}/{type}.xml
    → SAMBAai polls those XML URLs
```

### Two-Phase Queue System (Core of `public_html/`)

The `xml_feed_queue` table drives all data sync. Each queue row represents one sync job:

- **Phase 1** (`parameters = []`): Fetches data from Shopify GraphQL API, saves to MySQL. Handles cursor-based pagination and incremental updates via `integration_data` table.
- **Phase 2** (`parameters = ['objects_done' => 1]`): Reads MySQL data, generates XML in 100-record chunks, writes final feed files.

Phase 1 must complete before Phase 2 runs (enforced by the `prepare-queue` command scheduling them 10 minutes apart). This separation ensures XML files are never written from partial data.

Queue states: `PENDING → RUNNING → EXECUTED` (or `MISSED`/`ERROR`).

### Frontend (`front-app/`)

Standard Remix app with Shopify App Bridge:
- `app/routes/` — Remix routes with loaders/actions for server-side data
- `app/models/` — Database queries via Prisma
- `app/shopify.server.ts` — Shopify API client (authenticated admin GraphQL)
- `app/db.server.ts` — Prisma client singleton
- `prisma/schema.prisma` — MySQL schema

### Backend (`public_html/`)

Yii2 application with the main logic in:
- `commands/XmlGeneratorController.php` + `XmlGeneratorService.php` — CLI commands and orchestration
- `modules/shopify/` — Shopify API integration (ProductFeed, CustomerFeed, OrderFeed) + model transformers
- `modules/xml_generator/src/` — XML file generation (FeedGenerator, ProductFeed, CustomerFeed, OrderFeed)
- `models/Queue.php` — Queue state machine
- `integration-bash-*.sh` — Cron entry points; each runs for ~50 seconds, looping over queue items

### Multi-Platform Support

Beyond Shopify, `public_html/modules/` includes Shoper and Idosell integrations with the same queue/XML pattern. New platform integrations follow this same structure.

## Key Environment Variables

**front-app:**
- `DATABASE_URL` — Prisma MySQL connection
- `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`

**public_html:**
- `public_html/config/db.php` — database credentials (not committed)

## Database

Both apps share the same MySQL server (different tables/schemas). Prisma manages the `Session` table and app-specific tables; Yii2 migrations manage the rest (`user`, `product`, `customers`, `orders`, `xml_feed_queue`, `integration_data`, `disabled_feeds`, etc.).
