CREATE TABLE fraud_detection_logs
(
    id                   UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    account_id           UUID                     NOT NULL,
    customer_id          VARCHAR(255)             NOT NULL,
    fraud_score          DECIMAL(5, 4)            NOT NULL,
    reasons              JSON,
    transaction_amount   INTEGER,
    transaction_currency VARCHAR(3),
    context_data         JSON,
    action_taken         VARCHAR(20)              NOT NULL DEFAULT 'none' CHECK (action_taken IN ('none', 'flagged', 'blocked', 'suspended')),
    created_at           TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (account_id) REFERENCES loyalty_accounts (id) ON DELETE CASCADE,

    INDEX                idx_account_id(account_id),
    INDEX                idx_customer_id(customer_id),
    INDEX                idx_fraud_score(fraud_score),
    INDEX                idx_action_taken(action_taken),
    INDEX                idx_created_at(created_at)
);
