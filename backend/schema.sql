-- HUManity Foundation — MySQL Schema for Hostinger
-- Run this in hPanel → phpMyAdmin → SQL tab

CREATE TABLE IF NOT EXISTS donations (
    id                      VARCHAR(36)  PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    email                   VARCHAR(255) NOT NULL,
    phone                   VARCHAR(20)  NOT NULL,
    amount                  INT          NOT NULL,
    id_type                 VARCHAR(50)  NOT NULL,
    id_number               VARCHAR(100) NOT NULL,
    donation_type           VARCHAR(10)  NOT NULL DEFAULT 'once',
    razorpay_order_id       VARCHAR(100),
    razorpay_payment_id     VARCHAR(100),
    razorpay_signature      VARCHAR(255),
    razorpay_subscription_id VARCHAR(100),
    razorpay_plan_id        VARCHAR(100),
    status                  VARCHAR(20)  NOT NULL DEFAULT 'pending',
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS volunteer_applications (
    id             VARCHAR(36)  PRIMARY KEY,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    city           VARCHAR(100) NOT NULL,
    dob            VARCHAR(20)  NOT NULL,
    occupation     VARCHAR(100) NOT NULL,
    volunteer_type TEXT         NOT NULL,
    projects       TEXT         NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS corporate_partnerships (
    id                 VARCHAR(36)  PRIMARY KEY,
    poc_name           VARCHAR(255) NOT NULL,
    company_name       VARCHAR(255) NOT NULL,
    email              VARCHAR(255) NOT NULL,
    phone              VARCHAR(20)  NOT NULL,
    interest           TEXT         NOT NULL,
    engagement_type    VARCHAR(100) NOT NULL,
    location           VARCHAR(255),
    expected_employees VARCHAR(50)  NOT NULL,
    additional_notes   TEXT,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id         VARCHAR(36)  PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS partnership_inquiries (
    id               VARCHAR(36)  PRIMARY KEY,
    project_interest VARCHAR(255) NOT NULL,
    partnership_type VARCHAR(100) NOT NULL,
    name             VARCHAR(255) NOT NULL,
    email            VARCHAR(255) NOT NULL,
    phone            VARCHAR(20)  NOT NULL,
    company          VARCHAR(255) NOT NULL,
    city             VARCHAR(100) NOT NULL,
    number_of_schools VARCHAR(50) NOT NULL,
    message          TEXT,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ceo_contacts (
    id          VARCHAR(36)  PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    company     VARCHAR(255) NOT NULL,
    designation VARCHAR(100),
    message     TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
