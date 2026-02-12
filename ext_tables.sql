CREATE TABLE tx_okazurelogin_configuration (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,

    site_root_page_id int(11) unsigned DEFAULT '0' NOT NULL,
    enabled tinyint(1) unsigned DEFAULT '1' NOT NULL,
    show_label tinyint(1) unsigned DEFAULT '1' NOT NULL,
    backend_login_label varchar(255) DEFAULT '' NOT NULL,
    tenant_id varchar(255) DEFAULT '' NOT NULL,
    client_id varchar(255) DEFAULT '' NOT NULL,
    client_secret_encrypted text,
    redirect_uri_frontend varchar(1024) DEFAULT '' NOT NULL,
    redirect_uri_backend varchar(1024) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY site_root (site_root_page_id)
);
