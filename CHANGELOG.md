# Changelog

All notable changes to `filament-billing-console` are documented in this file, in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

## [Unreleased]

### Changed

- The customers column and filter say "current subscriptions" (active, trialing or past due), and the customer sync
  confirmation says it syncs only the subscriptions your Cashier tables already have.
- README: exact sync behaviour (differences from the webhook, no import of Stripe-only subscriptions), which reads are
  cached, the price limit, policy fallback, tenancy and the rate limit.

## [1.0.0] - 2026-10-23

### Added

- Customers resource over Cashier's billable model: payment method, active subscriptions, search, filter, and a
  customer page with their subscriptions, invoices and payments.
- Subscriptions resource over Cashier's tables: status, price, trial and end dates, items, status and grace-period
  filters, and a gray navigation count of past-due and unpaid subscriptions.
- Invoices and Payments pages read from the Stripe API (cached), with PDF and receipt links.
- MRR, active, trialing and 30-day churn summary widget.
- "Open in Stripe" links in test or live mode, and "Sync from Stripe" on customers and subscriptions using Cashier's
  own webhook code. Nothing is ever written to Stripe.
- Authorization through `authorizeUsing()`, a `viewBillingConsole` gate, or model policies.
