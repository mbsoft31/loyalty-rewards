CREATE TABLE loyalty_accounts
(
    id               UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    customer_id      VARCHAR(255)             NOT NULL UNIQUE,
    available_points INTEGER                  NOT NULL DEFAULT 0 CHECK (available_points >= 0),
    pending_points   INTEGER                  NOT NULL DEFAULT 0 CHECK (pending_points >= 0),
    lifetime_points  INTEGER                  NOT NULL DEFAULT 0 CHECK (lifetime_points >= 0),
    status           VARCHAR(20)              NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended', 'closed')),
    created_at       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP WITH TIME ZONE,

    INDEX            idx_customer_id(customer_id),
    INDEX            idx_status(status),
    INDEX            idx_last_activity(last_activity_at),
    INDEX            idx_created_at(created_at)
);
