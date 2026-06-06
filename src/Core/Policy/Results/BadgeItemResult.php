<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy\Results;

final readonly class BadgeItemResult
{
    /**
     * @param  array<string, mixed>  $requirement
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $id,
        public string $badgeKey,
        public string $label,
        public string $description,
        public string $iconKey,
        public string $rarity,
        public string $state,
        public ?string $earnedAt,
        public int $progress,
        public int $target,
        public string $category,
        public mixed $rewardKey,
        public array $requirement,
        public string $plan,
        public array $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'badge_key' => $this->badgeKey,
            'label' => $this->label,
            'description' => $this->description,
            'icon_key' => $this->iconKey,
            'rarity' => $this->rarity,
            'state' => $this->state,
            'earned_at' => $this->earnedAt,
            'progress' => $this->progress,
            'target' => $this->target,
            'category' => $this->category,
            'reward_key' => $this->rewardKey,
            'requirement' => $this->requirement,
            'plan' => $this->plan,
            'meta' => $this->meta,
        ];
    }
}
