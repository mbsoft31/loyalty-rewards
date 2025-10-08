# Loyalty Rewards — Laravel Adapter (Skeleton)

This package provides a minimal Laravel service provider to wire the core Loyalty Rewards library into a Laravel application.

## Install (local path during development)

Add a path repository to your app's `composer.json` and require the adapter:

```json
{
  "repositories": [
    { "type": "path", "url": "../loyalty-rewards/packages/loyalty-rewards-laravel" }
  ],
  "require": {
    "mbsoft31/loyalty-rewards": "*",
    "mbsoft31/loyalty-rewards-laravel": "*"
  }
}
```

Then run `composer update`.

## Usage

- The service provider auto-registers via Laravel's package discovery.
- Configure DB via `.env`:
  - `LOYALTY_DB_DRIVER=pgsql|mysql|sqlite`
  - `LOYALTY_DB_HOST=127.0.0.1`, `LOYALTY_DB_PORT=5432`, `LOYALTY_DB_DATABASE=loyalty_rewards`, `LOYALTY_DB_USERNAME=app`, `LOYALTY_DB_PASSWORD=...`
- Publish config:

```bash
php artisan vendor:publish --tag=loyalty-rewards-config
```

Then resolve `LoyaltyRewards\\Core\\Services\\LoyaltyService` from the container.

Note: Migrations are Postgres SQL files in the core repo; port or implement Laravel migrations before production.

