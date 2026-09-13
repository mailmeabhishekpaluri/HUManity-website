-- Apply once to the existing HUManity MySQL database before enabling this fundraiser.
-- Separate from general donations: public totals must never mix campaigns.
CREATE TABLE IF NOT EXISTS cci_fund_donations (
 id CHAR(32) PRIMARY KEY,
 campaign VARCHAR(80) NOT NULL,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(254) NOT NULL,
 phone VARCHAR(20) NOT NULL,
 amount_paise BIGINT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'INR',
 mode VARCHAR(4) NOT NULL,
 razorpay_order_id VARCHAR(100) UNIQUE,
 razorpay_payment_id VARCHAR(100) UNIQUE,
 amount_refunded_paise BIGINT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 confirmed_at DATETIME NULL,
 INDEX campaign_status (campaign, mode, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
