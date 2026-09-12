CREATE TABLE IF NOT EXISTS rvgame_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash BINARY(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rvgame_campaigns (
    id CHAR(36) NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    ruleset_id VARCHAR(40) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    state_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campaigns_session (session_id),
    CONSTRAINT fk_rvgame_campaigns_session FOREIGN KEY (session_id) REFERENCES rvgame_sessions(id) ON DELETE CASCADE,
    CONSTRAINT ck_rvgame_campaigns_state_json CHECK (JSON_VALID(state_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rvgame_command_receipts (
    command_id CHAR(36) NOT NULL,
    campaign_id CHAR(36) NOT NULL,
    request_hash BINARY(32) NOT NULL,
    response_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (command_id),
    KEY ix_receipts_campaign (campaign_id),
    CONSTRAINT fk_rvgame_receipts_campaign FOREIGN KEY (campaign_id) REFERENCES rvgame_campaigns(id) ON DELETE CASCADE,
    CONSTRAINT ck_rvgame_receipts_response_json CHECK (JSON_VALID(response_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
