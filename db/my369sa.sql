CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `login` int(11) NOT NULL,
  `account_name` varchar(50) DEFAULT NULL,
  `broker_name` varchar(50) DEFAULT NULL,
  `trade_mode` int(11) DEFAULT '0',
  `leverage` int(11) DEFAULT '0',
  `init_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `init_balance` decimal(15,2) DEFAULT '0.00',
  `day_init_balance` decimal(15,2) NOT NULL,
  `day_init_equity` decimal(15,2) NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `equity` decimal(15,2) DEFAULT '0.00',
  `free_margin` decimal(15,2) DEFAULT '0.00',
  `margin_free` decimal(15,2) DEFAULT '0.00',
  `account_float` decimal(15,2) DEFAULT '0.00',
  `open_count` int(11) DEFAULT '0',
  `positive_float` decimal(15,2) DEFAULT '0.00',
  `total_volume` decimal(15,2) DEFAULT '0.00',
  `name` varchar(50) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `server` varchar(50) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `company` varchar(50) DEFAULT NULL,
  `terminal_path` varchar(100) DEFAULT NULL,
  `terminal_build` varchar(20) DEFAULT NULL,
  `last_update` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(30) DEFAULT NULL,
  `remark` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;



--
-- Tracks open trades
-- idx_group_id for faster lookups
-- 
CREATE TABLE `trades_open` (
  `id` int(11) NOT NULL,
  `ticket` bigint(20) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
  `commission`  decimal(15,2) DEFAULT NULL,
  `open_time` datetime DEFAULT NULL,
  `bid_price` decimal(15,5) DEFAULT NULL,
  `ask_price` decimal(15,5) DEFAULT NULL,
  `current_price` decimal(15,5) DEFAULT NULL,
  `comment` varchar(100) DEFAULT NULL,
  `last_update` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_open`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
CREATE INDEX idx_group_id ON trades_open(group_id);  
ALTER TABLE trades_open MODIFY volume DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE trades_open MODIFY order_type ENUM('buy', 'sell') DEFAULT 'buy';

--
-- Tracks closed trades
--
CREATE TABLE `trades_closed` (
  `id` int(11) NOT NULL,
  `ticket` bigint(20) DEFAULT NULL,
  `config_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `close_price` decimal(15,5) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
  `open_time` datetime DEFAULT NULL,
  `close_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_closed`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
CREATE INDEX idx_close_time ON trades_closed(close_time); 
ALTER TABLE trades_closed MODIFY profit DECIMAL(15,2) DEFAULT 0.00; 

--
-- Represents aggregated/grouped trades by account_id, magic_number, pair, and order_type
--
CREATE TABLE `trades_group` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `total_volume` decimal(15,2) DEFAULT NULL,
  `weighted_open_price` decimal(15,5) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
  `last_update` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trade` (`account_id`,`magic_number`,`pair`,`order_type`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
 
--
-- Stores trade configurations such as stop_loss, take_profit, and remarks for trading journal.
-- Will implement trailing stop
--
CREATE TABLE `trades_config` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `stop_loss` decimal(15,5) DEFAULT NULL,
  `take_profit` decimal(15,5) DEFAULT NULL,
  `remark` varchar(300) DEFAULT NULL,
  `last_update` datetime DEFAULT CURRENT_TIMESTAMP,
  `auth_FT` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'First Trade Authorization',
  `auth_AT` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Add Trade Authorization',
  `auth_CP` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Close Profit Authorization',
  `auth_SL` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Stop Loss Authorization',
  `auth_CL` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Close All Authorization'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trade` (`account_id`,`magic_number`,`pair`,`order_type`),
  ADD KEY `idx_group_id` (`group_id`);
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
--
-- Similar to trades_config, but for closed trades.
-- Separate table for easy archived
--
CREATE TABLE `trades_config_closed` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the trades_config table',
  `group_id` INT(11) DEFAULT NULL COMMENT 'Reference to the trades_group table for grouping trades',
  `account_id` INT(11) NOT NULL COMMENT 'ID of the account to which this trade configuration belongs',
  `magic_number` INT(11) DEFAULT NULL COMMENT 'Unique identifier for strategies or trade groups',
  `pair` VARCHAR(20) NOT NULL COMMENT 'Currency pair (e.g., EUR/USD)',
  `order_type` ENUM('buy','sell') DEFAULT NULL COMMENT 'Type of order: buy or sell',
  `stop_loss` DECIMAL(15,5) DEFAULT NULL COMMENT 'Stop-loss price for the trade',
  `take_profit` DECIMAL(15,5) DEFAULT NULL COMMENT 'Take-profit price for the trade',
  `remarks` VARCHAR(300) DEFAULT NULL COMMENT 'Optional remarks or notes about the trade configuration',
  `last_update` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp of the last update to this record',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_trade_config` (`account_id`, `magic_number`, `pair`, `order_type`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores trade-specific configurations such as stop-loss and take-profit levels';

   
--
-- Stores details of pending trades manage by trading system
-- no ticket field , self managed pending order
--
CREATE TABLE `trades_pending` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `expiration_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_pending`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;  

--
-- real time order to be executed by OrderExecutor EA
--
CREATE TABLE `trades_order` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `ticket` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_order`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT; 
CREATE INDEX idx_ticket ON trades_order(ticket);
 
--
-- order executed by OrderExecutor EA, move from trades_order
-- separate table for easy archived
--
CREATE TABLE `trades_order_executed` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `ticket` bigint(20) DEFAULT NULL,
  `action` enum('open','closed') DEFAULT NULL,
  `execution_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_order_executed`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
CREATE INDEX idx_ticket_executed ON trades_order_executed(ticket);  

CREATE TABLE `risk_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) NOT NULL,
    `day_max_drawdown` DECIMAL(15,2) DEFAULT NULL COMMENT 'Maximum allowable daily drawdown as a percentage',
    `total_max_drawdown` DECIMAL(15,2) DEFAULT NULL COMMENT 'Maximum allowable total drawdown as a percentage',
    `max_loss_per_trade` DECIMAL(15,2) DEFAULT NULL COMMENT 'Maximum allowable loss per trade in currency',
    `max_profit_per_trade` DECIMAL(15,2) DEFAULT NULL COMMENT 'Maximum allowable profit per trade in currency',
    `risk_reduction_threshold` DECIMAL(15,2) DEFAULT NULL COMMENT 'Threshold percentage to trigger alerts before reaching max drawdown',
    `alert_on_threshold` BOOLEAN DEFAULT FALSE COMMENT 'Enable/disable notifications for risk reduction threshold',
    `email_alerts` VARCHAR(255) DEFAULT NULL COMMENT 'Email address for alerts and notifications',
    `hedging_enabled` BOOLEAN DEFAULT FALSE COMMENT 'Allow/disallow hedging for the account',
    `martingale_enabled` BOOLEAN DEFAULT FALSE COMMENT 'Allow/disallow martingale strategy for the account',
    `risk_level` ENUM('low', 'medium', 'high') DEFAULT 'medium' COMMENT 'Risk tolerance level',
    `risk_strategy` VARCHAR(50) DEFAULT NULL COMMENT 'Risk strategy applied to the account (e.g., fixed-risk, aggressive)',
    `max_drawdown_reached` DECIMAL(15,2) DEFAULT NULL COMMENT 'Highest historical drawdown recorded as a percentage',
    `max_exposure_reached` DECIMAL(15,2) DEFAULT NULL COMMENT 'Highest historical exposure recorded as a currency value',
    `dynamic_stop_loss` BOOLEAN DEFAULT FALSE COMMENT 'Enable/disable dynamic stop-loss',
    `dynamic_take_profit` BOOLEAN DEFAULT FALSE COMMENT 'Enable/disable dynamic take-profit',
    `trailing_stop_pips` DECIMAL(15,2) DEFAULT NULL COMMENT 'Trailing stop distance in pips (optional for dynamic stop-loss)',
    `volatility_factor` DECIMAL(15,2) DEFAULT NULL COMMENT 'Multiplier for volatility-based adjustments (optional for dynamic stop-loss)',
    `last_update` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp for the risk configuration',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Risk management configuration for trading accounts';

CREATE TABLE `risk_config_pair` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the risk_config_pair table',
  `pair` VARCHAR(20) NOT NULL COMMENT 'Currency pair (e.g., EUR/USD)',
  `trailing_stop` DECIMAL(15,2) DEFAULT NULL COMMENT 'Trailing stop value in pips for the pair',
  `max_spread` DECIMAL(15,5) DEFAULT NULL COMMENT 'Maximum allowable spread for the pair',
  `risk_multiplier` DECIMAL(15,2) DEFAULT 1.0 COMMENT 'Multiplier for adjusting pair-specific risks',
  `volatility_factor` DECIMAL(15,2) DEFAULT NULL COMMENT 'Multiplier for volatility-based adjustments',
  `last_update` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp of the last update to this record',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`pair`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores universal pair-specific risk configurations';

CREATE TABLE `pair_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the pair_config table',
  `pair` VARCHAR(20) NOT NULL COMMENT 'Currency pair (e.g., EUR/USD)',
  `point_value` DECIMAL(15,5) NOT NULL DEFAULT 0.00010 COMMENT 'Smallest price movement (pip value) for the pair',
  `support_level_1` DECIMAL(15,5) DEFAULT NULL COMMENT 'First support level for the pair',
  `support_level_2` DECIMAL(15,5) DEFAULT NULL COMMENT 'Second support level for the pair',
  `support_level_3` DECIMAL(15,5) DEFAULT NULL COMMENT 'Third support level for the pair',
  `resistance_level_1` DECIMAL(15,5) DEFAULT NULL COMMENT 'First resistance level for the pair',
  `resistance_level_2` DECIMAL(15,5) DEFAULT NULL COMMENT 'Second resistance level for the pair',
  `resistance_level_3` DECIMAL(15,5) DEFAULT NULL COMMENT 'Third resistance level for the pair',
  `trailing_stop` DECIMAL(15,2) DEFAULT NULL COMMENT 'Trailing stop value in pips for the pair',
  `max_spread` DECIMAL(15,5) DEFAULT NULL COMMENT 'Maximum allowable spread for the pair',
  `volatility_factor` DECIMAL(15,2) DEFAULT NULL COMMENT 'Volatility-based multiplier for the pair',
  `average_pip_value` DECIMAL(15,2) DEFAULT NULL COMMENT 'Average pip value for the pair (optional future use)',
  `correlation_score` DECIMAL(15,2) DEFAULT NULL COMMENT 'Correlation score with other pairs (optional future use)',
  `last_update` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp of the last update to this record',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`pair`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores configuration and properties for specific currency pairs';
