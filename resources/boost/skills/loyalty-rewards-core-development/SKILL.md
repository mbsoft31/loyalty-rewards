---
name: loyalty-rewards-core-development
description: Build and improve the framework-agnostic Loyalty Rewards core package, including domain models, value objects, rules, services, repositories, tests, and package quality gates.
---

# Loyalty Rewards Core Development

## When To Use

Use this skill when working in `loyalty-rewards` on framework-agnostic loyalty behavior, package quality, rule contracts, services, repositories, or tests.

## Workflow

1. Check dirty state before editing.
2. Keep Laravel-specific behavior out of the core package.
3. Start with a focused Pest test for behavior changes.
4. Preserve existing public APIs unless the task explicitly allows a breaking change.
5. Validate with:

```bash
composer test
composer stan
composer lint
```

## Boundaries

Core owns domain behavior and extension contracts. Laravel Pro owns client-facing Laravel API workflows.

