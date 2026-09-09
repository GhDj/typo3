CREATE TABLE tx_thwselfservice_domain_model_organizationalunit (
    title varchar(255) NOT NULL DEFAULT '',
    code varchar(16) NOT NULL DEFAULT '',
    parent int(11) unsigned NOT NULL DEFAULT 0,
    active tinyint(1) unsigned NOT NULL DEFAULT 1,

    UNIQUE INDEX idx_code (code)
);

CREATE TABLE fe_users (
    thw_uid varchar(32) NOT NULL DEFAULT '',
    thw_ou int(11) unsigned NOT NULL DEFAULT 0,
    thw_birthdate date DEFAULT NULL,
    thw_active tinyint(1) unsigned NOT NULL DEFAULT 0,
    thw_portal_access tinyint(1) unsigned NOT NULL DEFAULT 0,
    thw_sso_subject varchar(255) NOT NULL DEFAULT '',
    thw_last_import datetime DEFAULT NULL,

    UNIQUE INDEX idx_thw_uid (thw_uid),
    UNIQUE INDEX idx_thw_sso_subject (thw_sso_subject)
);
