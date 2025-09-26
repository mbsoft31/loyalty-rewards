CREATE TABLE points_transactions
(
    id           UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    account_id   UUID                     NOT NULL,
    type         VARCHAR(20)              NOT NULL CHECK (type IN ('earn', 'redeem', 'expire', 'refund', 'adjustment')),
    points       INTEGER                  NOT NULL,
    context_data JSON,
    created_at   TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP WITH TIME ZONE,

    FOREIGN KEY (account_id) REFERENCES loyalty_accounts (id) ON DELETE CASCADE,

    INDEX        idx_account_id(account_id),
    INDEX        idx_type(type),
    INDEX        idx_created_at(created_at),
    INDEX        idx_processed_at(processed_at),
    INDEX        idx_account_type(account_id, type),
    INDEX        idx_account_created(account_id, created_at)
);
