# Loyalty Rewards — Premium Pack (Advanced Promotions & Tiers)

This package is a commercial add‑on for advanced earning/redemption rules.

## Features (initial)
- Advanced Tier Rule: tier map with configurable multipliers
- Partner Catalog Rule: partner‑specific multipliers
- Campaign Redemption Rule: campaign‑based redemption values

## Install (via Private Composer Repository)

```json
{
  "repositories": [
    {"type": "composer", "url": "https://<your-private-repo>"}
  ],
  "require": {
    "mbsoft31/loyalty-rewards-premium": "^1.0"
  }
}
```

## Usage

Register premium rules in your application bootstrapping or via the Laravel adapter’s config‑driven rules.

```php
$engine->addEarningRule(new LoyaltyRewards\Premium\Rules\Earning\AdvancedTierRule([
    'silver' => 1.1,
    'gold' => 1.25,
    'platinum' => 1.5,
]));

$engine->addEarningRule(new LoyaltyRewards\Premium\Rules\Earning\PartnerCatalogRule([
    'nike' => 2.0,
    'sony' => 1.5,
]));
```

> Licensing: Proprietary. Distribute via Private Packagist, Satis, or GitHub Packages.

