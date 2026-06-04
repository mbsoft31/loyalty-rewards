## Loyalty Rewards Core

This package provides framework-agnostic loyalty domain primitives, services, rules, repositories, and value objects.

### Package Boundary

- Keep Laravel HTTP/API/workflow concerns out of this package.
- Put Laravel routes, controllers, Eloquent models, idempotency middleware, and preset client workflows in `mbsoft31/loyalty-laravel-pro`.
- Keep core behavior portable across Laravel, Symfony, and standalone PHP applications.

### Core Development

- Prefer value objects for public inputs: `CustomerId`, `Money`, `Currency`, `Points`, `TransactionContext`, and `ConversionRate`.
- Add or update focused tests when changing rules, services, repositories, or value objects.
- Preserve the rule contracts as stable extension points.
- Keep audit and fraud services framework-independent.

### Validation

Run:

```bash
composer test
composer stan
composer lint
```

If PHPStan reports existing generic-array debt, fix typed PHPDoc/return annotations rather than weakening the gate.

