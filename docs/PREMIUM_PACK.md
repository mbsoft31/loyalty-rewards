# Premium Rules Pack — Draft Structure & Private Composer Workflow

This document outlines how to structure a commercial add‑on and distribute it via a private Composer repository.

## Package Structure

- Package name: `mbsoft31/loyalty-rewards-premium`
- Namespace: `LoyaltyRewards\\Premium\\`
- Suggested layout:

```
loyalty-rewards-premium/
├── composer.json
└── src/
    └── Rules/
        ├── Earning/
        │   ├── DynamicTierRule.php
        │   └── PartnerCatalogRule.php
        └── Redemption/
            └── CampaignRedemptionRule.php
```

## Composer (Premium)

Example `composer.json` for the premium pack:

```json
{
  "name": "mbsoft31/loyalty-rewards-premium",
  "description": "Premium rules for Loyalty Rewards (advanced tiers, partners, campaigns)",
  "type": "library",
  "license": "Proprietary",
  "require": {
    "php": "^8.3",
    "mbsoft31/loyalty-rewards": "^1.0"
  },
  "autoload": {"psr-4": {"LoyaltyRewards\\\\Premium\\\\": "src/"}}
}
```

## Private Composer Repository Options

1) GitHub Packages
- Create a private repo; enable GitHub Packages.
- In client project `composer.json`:
```json
{
  "repositories": [
    {"type": "composer", "url": "https://repo.packagist.org"},
    {"type": "composer", "url": "https://github.com/OWNER/packages"}
  ]
}
```
- Authenticate via `composer config --global --auth github-oauth.github.com <TOKEN>`.

2) Satis / Private Packagist / Verdaccio (proxy)
- Host your own Composer index with Satis or use Private Packagist.
- Add the repository URL under `repositories` in client projects.

## License Key Check (Lightweight)

- Deliver updates via private repo.
- Add an optional updater that checks a license key from ENV and pings your license server before enabling auto‑update.

## Release Flow

- Tag releases in the premium repo: `v1.0.0`, `v1.0.1`.
- Update CHANGELOG and notify customers by email.
- Provide installation snippet:
```json
{
  "require": {"mbsoft31/loyalty-rewards-premium": "^1.0"},
  "repositories": [{"type": "composer", "url": "https://<your-private-repo>"}]
}
```

## Roadmap Ideas

- Advanced Promotions (stacks, coupons, exclusion rules)
- Expiry Policies (rolling windows, inactivity)
- Webhooks & Exporters (CSV/S3/Kafka)
- Analytics & Metrics (per‑rule effectiveness)

