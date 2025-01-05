-- Tracks open trades
CREATE TABLE `trades_open` (
  `id` int(11) NOT NULL,
  `ticket` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
  `open_time` datetime DEFAULT NULL,
  `bid_price` decimal(15,5) DEFAULT NULL,
  `ask_price` decimal(15,5) DEFAULT NULL,
  `last_update` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_open`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


-- Tracks closed trades
CREATE TABLE `trades_closed` (
  `id` int(11) NOT NULL,
  `ticket` int(11) DEFAULT NULL,
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

-- Represents aggregated/grouped trades by account_id, magic_number, pair, and order_type
CREATE TABLE `trades_group` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `weighted_open_price` decimal(15,5) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_group`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  ADD UNIQUE KEY `unique_trade` (`account_id`,`magic_number`,`pair`,`order_type`);

-- Stores trade configurations such as stop_loss, take_profit, and remarks for trading journal.
-- Will implement trailing stop
CREATE TABLE `trades_config` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `stop_loss` decimal(15,5) DEFAULT NULL,
  `take_profit` decimal(15,5) DEFAULT NULL,
  `remarks` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_config`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  ADD UNIQUE KEY `unique_trade` (`account_id`,`magic_number`,`pair`,`order_type`);  

-- Similar to trades_config, but for closed trades.
-- Separate table for easy archived
CREATE TABLE `trades_config_closed` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `stop_loss` decimal(15,5) DEFAULT NULL,
  `take_profit` decimal(15,5) DEFAULT NULL,
  `remarks` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_config_closed`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  ADD UNIQUE KEY `unique_trade` (`account_id`,`magic_number`,`pair`,`order_type`);  

-- Stores details of pending trades manage by trading system
-- no ticket field , self managed pending order
CREATE TABLE `trades_pending` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_pending`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;  

-- real time order to be executed by OrderExecutor EA
CREATE TABLE `trades_order` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `ticket` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_order`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT; 

-- order executed by OrderExecutor EA, move from trades_order
-- separate table for easy archived
CREATE TABLE `trades_order_executed` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `magic_number` int(11) DEFAULT NULL,
  `pair` varchar(20) NOT NULL,
  `order_type` enum('buy','sell') DEFAULT NULL,
  `open_price` decimal(15,5) DEFAULT NULL,
  `volume` decimal(15,2) DEFAULT NULL,
  `ticket` int(11) DEFAULT NULL,
  `action` enum('open','closed') DEFAULT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `trades_order_executed`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;