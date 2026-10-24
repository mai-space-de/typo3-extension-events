CREATE TABLE tx_maievents_event (
    title varchar(255) DEFAULT '' NOT NULL,
    description text,
    location varchar(255) DEFAULT '' NOT NULL,
    link varchar(2048) DEFAULT '' NOT NULL,
    start_date int(11) DEFAULT 0 NOT NULL,
    end_date int(11) DEFAULT 0 NOT NULL,
    registration_deadline int(11) DEFAULT 0 NOT NULL,
    recurrence_frequency varchar(20) DEFAULT '' NOT NULL,
    recurrence_until int(11) DEFAULT 0 NOT NULL,
    recurrence_month_weekday tinyint(4) DEFAULT 0 NOT NULL,
    max_attendees int(11) DEFAULT 0 NOT NULL,
    has_waiting_list tinyint(4) unsigned DEFAULT '0' NOT NULL,
    image int(11) unsigned DEFAULT '0' NOT NULL,
    categories int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_maievents_registration (
    event int(11) DEFAULT 0 NOT NULL,
    occurrence_start int(11) DEFAULT 0 NOT NULL,
    first_name varchar(100) DEFAULT '' NOT NULL,
    last_name varchar(100) DEFAULT '' NOT NULL,
    email varchar(255) DEFAULT '' NOT NULL,
    status varchar(20) DEFAULT 'registered' NOT NULL,
    waiting_list tinyint(4) unsigned DEFAULT '0' NOT NULL,
    confirmation_token varchar(64) DEFAULT '' NOT NULL,
    registered_at int(11) DEFAULT 0 NOT NULL,
    confirmed_at int(11) DEFAULT 0 NOT NULL
);

# Event doktype (121) metadata on pages — Content BGM-19
CREATE TABLE pages (
    tx_maievents_location varchar(255) DEFAULT '' NOT NULL,
    tx_maievents_start_date int(11) DEFAULT 0 NOT NULL,
    tx_maievents_end_date int(11) DEFAULT 0 NOT NULL,
    tx_maievents_registration_deadline int(11) DEFAULT 0 NOT NULL,
    tx_maievents_recurrence_frequency varchar(20) DEFAULT '' NOT NULL,
    tx_maievents_recurrence_until int(11) DEFAULT 0 NOT NULL,
    tx_maievents_recurrence_month_weekday tinyint(4) DEFAULT 0 NOT NULL,
    tx_maievents_max_attendees int(11) DEFAULT 0 NOT NULL,
    tx_maievents_has_waiting_list tinyint(4) unsigned DEFAULT '0' NOT NULL,
    tx_maievents_image int(11) unsigned DEFAULT '0' NOT NULL,
    tx_maievents_legacy_event_uid int(11) DEFAULT 0 NOT NULL
);
