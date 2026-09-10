CREATE TABLE tx_thwovssp_domain_model_orgunit (
    uid int(11) unsigned NOT NULL AUTO_INCREMENT,
    pid int(11) unsigned NOT NULL DEFAULT 0,
    tstamp int(11) unsigned NOT NULL DEFAULT 0,
    crdate int(11) unsigned NOT NULL DEFAULT 0,
    deleted tinyint(1) unsigned NOT NULL DEFAULT 0,
    hidden tinyint(1) unsigned NOT NULL DEFAULT 0,
    sorting int(11) NOT NULL DEFAULT 0,

    thw_oe_uid int(11) unsigned NOT NULL DEFAULT 0,
    oe_code varchar(4) NOT NULL DEFAULT '',
    name varchar(255) NOT NULL DEFAULT '',
    mail_address varchar(255) NOT NULL DEFAULT '',
    regionalbereich_code varchar(4) NOT NULL DEFAULT '',
    landesverband_code varchar(4) NOT NULL DEFAULT '',
    active tinyint(1) unsigned NOT NULL DEFAULT 1,

    PRIMARY KEY (uid),
    KEY parent (pid),
    UNIQUE INDEX idx_thw_oe_uid (thw_oe_uid),
    UNIQUE INDEX idx_oe_code (oe_code),
    KEY idx_regionalbereich (regionalbereich_code),
    KEY idx_landesverband (landesverband_code)
);

CREATE TABLE tx_thwovssp_domain_model_directory (
    uid int(11) unsigned NOT NULL AUTO_INCREMENT,
    pid int(11) unsigned NOT NULL DEFAULT 0,
    tstamp int(11) unsigned NOT NULL DEFAULT 0,
    crdate int(11) unsigned NOT NULL DEFAULT 0,
    deleted tinyint(1) unsigned NOT NULL DEFAULT 0,
    hidden tinyint(1) unsigned NOT NULL DEFAULT 0,
    sorting int(11) NOT NULL DEFAULT 0,

    name varchar(64) NOT NULL DEFAULT '',
    allows_read tinyint(1) unsigned NOT NULL DEFAULT 0,
    allows_write tinyint(1) unsigned NOT NULL DEFAULT 0,
    sort_key int(11) DEFAULT NULL,
    description varchar(255) NOT NULL DEFAULT '',

    PRIMARY KEY (uid),
    KEY parent (pid),
    UNIQUE INDEX idx_name (name)
);

CREATE TABLE fe_users (
    thw_uid int(11) unsigned NOT NULL DEFAULT 0,
    thw_orgunit int(11) unsigned NOT NULL DEFAULT 0,
    thw_realm varchar(2) NOT NULL DEFAULT '',
    thw_last_import datetime DEFAULT NULL,
    thw_import_missing_since datetime DEFAULT NULL,

    UNIQUE INDEX idx_thw_uid (thw_uid),
    UNIQUE INDEX idx_realm_username (thw_realm, username)
);
