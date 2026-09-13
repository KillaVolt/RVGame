CREATE TABLE IF NOT EXISTS rvgame_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash BINARY(32) NOT NULL,
    channel VARCHAR(10) NOT NULL DEFAULT 'main',
    analytics_excluded TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS rvgame_ratings (
    session_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    CONSTRAINT fk_rvgame_ratings_session FOREIGN KEY (session_id) REFERENCES rvgame_sessions(id) ON DELETE CASCADE,
    CONSTRAINT ck_rvgame_ratings_value CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rvgame_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(10) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_rvgame_feedback_session_created (session_id, created_at),
    CONSTRAINT fk_rvgame_feedback_session FOREIGN KEY (session_id) REFERENCES rvgame_sessions(id) ON DELETE CASCADE,
    CONSTRAINT ck_rvgame_feedback_category CHECK (category IN ('bug', 'idea', 'other'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rvgame_analytics_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(10) NOT NULL,
    visit_token_hash BINARY(32) NOT NULL,
    source VARCHAR(80) NOT NULL,
    medium VARCHAR(40) NOT NULL,
    campaign VARCHAR(80) NOT NULL,
    referrer_host VARCHAR(190) NOT NULL,
    landing_path VARCHAR(255) NOT NULL,
    device VARCHAR(20) NOT NULL,
    browser VARCHAR(40) NOT NULL,
    operating_system VARCHAR(40) NOT NULL,
    language VARCHAR(12) NOT NULL,
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    page_views INT UNSIGNED NOT NULL DEFAULT 1,
    event_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rvgame_analytics_visit_token (visit_token_hash),
    KEY ix_rvgame_analytics_visits_channel_started (channel, started_at),
    KEY ix_rvgame_analytics_visits_session_started (session_id, started_at),
    CONSTRAINT fk_rvgame_analytics_visits_session FOREIGN KEY (session_id) REFERENCES rvgame_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rvgame_analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(10) NOT NULL,
    campaign_id CHAR(36) NULL,
    event_name VARCHAR(60) NOT NULL,
    event_value INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_rvgame_analytics_events_channel_created (channel, created_at),
    KEY ix_rvgame_analytics_events_name_created (event_name, created_at),
    CONSTRAINT fk_rvgame_analytics_events_visit FOREIGN KEY (visit_id) REFERENCES rvgame_analytics_visits(id) ON DELETE CASCADE,
    CONSTRAINT fk_rvgame_analytics_events_session FOREIGN KEY (session_id) REFERENCES rvgame_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
