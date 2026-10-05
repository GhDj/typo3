CREATE TABLE tx_thwovssp_domain_model_orgunit (
    thw_oe_uid int(11) unsigned NOT NULL DEFAULT 0,
    oe_code varchar(4) NOT NULL DEFAULT '',
    name varchar(255) NOT NULL DEFAULT '',
    mail_address varchar(255) NOT NULL DEFAULT '',
    regionalbereich_code varchar(4) NOT NULL DEFAULT '',
    landesverband_code varchar(4) NOT NULL DEFAULT '',
    active tinyint(1) unsigned NOT NULL DEFAULT 1,

    UNIQUE INDEX idx_thw_oe_uid (thw_oe_uid),
    UNIQUE INDEX idx_oe_code (oe_code),
    KEY idx_regionalbereich (regionalbereich_code),
    KEY idx_landesverband (landesverband_code)
);

CREATE TABLE tx_thwovssp_domain_model_directory (
    name varchar(64) NOT NULL DEFAULT '',
    allows_read tinyint(1) unsigned NOT NULL DEFAULT 0,
    allows_write tinyint(1) unsigned NOT NULL DEFAULT 0,
    sort_key int(11) DEFAULT NULL,
    description varchar(255) NOT NULL DEFAULT '',

    UNIQUE INDEX idx_name (name)
);

CREATE TABLE tx_thwovssp_domain_model_role (
    name varchar(100) NOT NULL DEFAULT '',
    role_group varchar(50) NOT NULL DEFAULT '',
    description text,

    UNIQUE INDEX idx_name (name)
);

CREATE TABLE tx_thwovssp_domain_model_directoryright (
    role int(11) unsigned NOT NULL DEFAULT 0,
    directory int(11) unsigned NOT NULL DEFAULT 0,
    access varchar(5) NOT NULL DEFAULT 'read',

    UNIQUE INDEX idx_role_directory (role, directory),
    KEY idx_directory (directory)
);

CREATE TABLE tx_thwovssp_user_role_mm (
    uid_local int(11) unsigned NOT NULL DEFAULT 0,
    uid_foreign int(11) unsigned NOT NULL DEFAULT 0,
    sorting int(11) unsigned NOT NULL DEFAULT 0,
    sorting_foreign int(11) unsigned NOT NULL DEFAULT 0,

    PRIMARY KEY (uid_local, uid_foreign),
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE fe_users (
    thw_uid int(11) unsigned NOT NULL DEFAULT 0,
    thw_orgunit int(11) unsigned NOT NULL DEFAULT 0,
    thw_realm varchar(2) NOT NULL DEFAULT '',
    thw_birthdate date DEFAULT NULL,
    thw_portal_access tinyint(2) unsigned NOT NULL DEFAULT 0,
    thw_last_import datetime DEFAULT NULL,
    thw_import_missing_since datetime DEFAULT NULL,
    thw_roles int(11) unsigned NOT NULL DEFAULT 0,

    UNIQUE INDEX idx_thw_uid (thw_uid),
    UNIQUE INDEX idx_realm_username (thw_realm, username)
);
