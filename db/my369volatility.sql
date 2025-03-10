-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Mar 10, 2025 at 06:44 AM
-- Server version: 5.7.44
-- PHP Version: 8.2.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `my369volatility`
--

-- --------------------------------------------------------

--
-- Table structure for table `currency_volatility`
--

CREATE TABLE `currency_volatility` (
  `id` int(11) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `value1` double NOT NULL,
  `value4` double NOT NULL,
  `value24` double NOT NULL,
  `dataTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `currency_volatility`
--


--
-- Table structure for table `volatility`
--

CREATE TABLE `volatility` (
  `id` int(11) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `value1` double NOT NULL,
  `value4` double NOT NULL,
  `value24` double NOT NULL,
  `avg_value1` double NOT NULL,
  `avg_value4` double NOT NULL,
  `avg_value24` double NOT NULL,
  `dataTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `volatility`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `currency_volatility`
--
ALTER TABLE `currency_volatility`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `volatility`
--
ALTER TABLE `volatility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_symbol_dataTime` (`symbol`,`dataTime`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `currency_volatility`
--
ALTER TABLE `currency_volatility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1297;

--
-- AUTO_INCREMENT for table `volatility`
--
ALTER TABLE `volatility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59472;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
