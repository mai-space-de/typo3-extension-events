CREATE TABLE tx_maievents_event (
    title varchar(255) DEFAULT '' NOT NULL,
    description text,
    location varchar(255) DEFAULT '' NOT NULL,
    start_date int(11) DEFAULT 0 NOT NULL,
    end_date int(11) DEFAULT 0 NOT NULL,
    registration_deadline int(11) DEFAULT 0 NOT NULL,
    recurrence_frequency varchar(20) DEFAULT '' NOT NULL,
    recurrence_until int(11) DEFAULT 0 NOT NULL,
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
