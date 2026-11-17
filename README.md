# Darkroom

An image generation app built on the [Pixelforge](https://pixelforge.promptedit.dev/docs) API.
Users write a prompt, see what it will cost, and watch the image come in live. Every
generation is billed at Pixelforge's exact cost + 25%, down to the nano-dollar.

**Stack:** Laravel 13 · Inertia 3 · Vue 3 · Tailwind 4 · Postgres (prod) / SQLite (local)

## Features

- **Develop** (generate): prompt, size, quality, up to 4 reference image URLs, and a live price
  range (min to max) that updates as you type.
- **Prints** (history): every generation with live status (queued, processing, succeeded, failed),
  estimated vs actual cost, and the image.
- **Webhooks**: signature-verified, idempotent, and order-independent.
- **Balances** _(stretch)_: welcome credit, fake "Buy credits", credit held for in-flight
  images, generation blocked when you can't afford the worst case.
- **Ledger** _(stretch, admin report)_: credit spend, provider cost and profit per day and per user,
  reconciled line by line against Pixelforge's `GET /v1/usage`.

## Accounts

Anyone can create an account at **`/register`** (the **Get $1 free** button on the home page):
name, email and password. There is no email verification, so you are logged in straight away,
on the Develop page, with $1.00 of welcome credit. In production the password needs at least
12 characters, upper and lower case letters, a number and a symbol.

**Admin accounts.** The Ledger (`/admin/report`) is only for operators. An account is an admin
if its email is listed in `ADMIN_EMAILS` (comma-separated). This deployment reserves these
addresses for operators. Register with any of them to get the Ledger:

- `admin@pixelforge.com`
- `operator@pixelforge.com`
- `reviewer@pixelforge.com`

Because emails are not verified, whoever registers an address first owns that account. Claim
the ones you need, and remove unused ones from `ADMIN_EMAILS`. An existing account can also be
made admin with `php artisan app:make-admin you@example.com` (`--revoke` to undo).

## How billing works

|               |                                                                                                                                                    |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit          | Integer **nano-dollars** (1 USD = 1,000,000,000). Pixelforge reports 9 decimals, so this is exact.                                                 |
| Provider cost | `Σ tokens × rate / 1M`, using the **rates stored on the generation** (fixed at creation).                                                          |
| User price    | `provider cost × 1.25`, rounded **up** to the next nano-dollar.                                                                                    |
| Estimate      | Output and reference-image tokens are exact. Text is `ceil(chars/4)` ±15% and reasoning is a per-quality range, so we show a **min to max** range. |
| Hold          | The estimate's **max** is reserved from the balance while the image is in flight.                                                                  |
| Failed        | Costs nothing. Only `succeeded` is charged, exactly once.                                                                                          |

## Running locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Put your Pixelforge key in `.env` (`PIXELFORGE_API_KEY`). Then run the web server, queue
worker and scheduler together:

```bash
composer dev
```

To receive webhooks locally, expose the app with a tunnel (for example
`cloudflared tunnel --url http://localhost:8000`). Set `<tunnel>/webhooks/pixelforge` as the
key's webhook URL in the Pixelforge dashboard and copy the signing secret into
`PIXELFORGE_WEBHOOK_SECRET`. Without webhooks the app still works: it polls
generations it hasn't heard about in 45 to 60 seconds.

Admin access: see [Accounts](#accounts).

### Testing

```bash
php artisan test          # 77 tests: pricing math, webhooks, jobs, balances, report
vendor/bin/phpstan        # Larastan, level 7
npm run types:check
```

Pixelforge test keywords work in prompts: `[fail]`, `[slow]`, `[duplicate]`, `[succeed]`,
`[blocked]`, `[overloaded]`.
