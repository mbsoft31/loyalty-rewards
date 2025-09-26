CREATE TABLE audit_logs
(
    id          UUID PRIMARY KEY                  DEFAULT gen_random_uuid(),
    entity_type VARCHAR(50)              NOT NULL,
    entity_id   VARCHAR(255)             NOT NULL,
    action      VARCHAR(50)              NOT NULL,
    user_id     VARCHAR(255),
    data        JSON,
    ip_address  INET,
    user_agent  TEXT,
    created_at  TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX       idx_entity_type_id(entity_type, entity_id),
    INDEX       idx_action(action),
    INDEX       idx_user_id(user_id),
    INDEX       idx_created_at(created_at),
    INDEX       idx_entity_created(entity_type, entity_id, created_at)
);
