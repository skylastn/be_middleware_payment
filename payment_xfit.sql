-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 03, 2022 at 12:18 PM
-- Server version: 5.7.39-log
-- PHP Version: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `payment_xfit`
--

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log__6`
--

CREATE TABLE `log__6` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log__7`
--

CREATE TABLE `log__7` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log__7`
--

INSERT INTO `log__7` (`id`, `key`, `name`, `ip`, `created_at`, `updated_at`) VALUES
(1, 'callback_order', '{\"id\":\"579c8d61f23fa4ca35e52da4\",\"external_id\":\"OTX-20220912-00009\",\"user_id\":\"5781d19b2e2385880609791c\",\"is_high\":true,\"payment_method\":\"WALLET\",\"status\":\"PAID\",\"merchant_name\":\"Xendit\",\"amount\":25000,\"paid_amount\":12000,\"bank_code\":\"QRIS\",\"paid_at\":\"2016-10-12T08:15:03.404Z\",\"payer_email\":\"wildan@xendit.co\",\"description\":\"This is a description\",\"adjusted_received_amount\":12000,\"fees_paid_amount\":0,\"updated\":\"2016-10-10T08:15:03.404Z\",\"created\":\"2016-10-10T08:15:03.404Z\",\"currency\":\"IDR\",\"payment_channel\":\"QRIS\",\"payment_destination\":\"888888888888\"}', '103.157.27.234', '2022-10-26 16:07:31', '2022-10-26 16:07:31'),
(2, 'request_order', '{\"external_id\":\"OTX-20221026-00005\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-26 16:22:23', '2022-10-26 16:22:23'),
(3, 'request_order', '{\"id\":\"63595ec3f366642ebb70a95b\",\"external_id\":\"OTX-20221026-00005\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-26T16:32:27.021Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63595ec3f366642ebb70a95b\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-26T16:22:27.728Z\",\"updated\":\"2022-10-26T16:22:27.728Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-26 16:22:27', '2022-10-26 16:22:27'),
(4, 'callback_order', '{\"id\":\"63595ec3f366642ebb70a95b\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221026-00005\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-26T16:22:27.728Z\",\"updated\":\"2022-10-26T16:23:04.317Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_4cb38de0-56b0-4ab9-a5aa-df86a0c5f4d1\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-26T16:22:56.737Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-56de51fd-9cba-4402-8073-9919cdbd3500\"}', '52.89.130.89', '2022-10-26 16:23:06', '2022-10-26 16:23:06'),
(5, 'request_order', '{\"external_id\":\"OTX-20221027-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 07:42:40', '2022-10-27 07:42:40'),
(6, 'request_order', '{\"id\":\"635a36721654a07696154083\",\"external_id\":\"OTX-20221027-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T07:52:42.352Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a36721654a07696154083\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T07:42:42.971Z\",\"updated\":\"2022-10-27T07:42:42.971Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 07:42:43', '2022-10-27 07:42:43'),
(7, 'callback_order', '{\"id\":\"635a36721654a07696154083\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T07:42:42.971Z\",\"updated\":\"2022-10-27T07:43:09.718Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_93f913fa-1a13-40c9-9d88-d0273cc9d204\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T07:43:01.799Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-9c90f613-d30f-4b38-80ed-d1ae5fbd5681\"}', '52.89.130.89', '2022-10-27 07:43:11', '2022-10-27 07:43:11'),
(8, 'request_order', '{\"external_id\":\"OTX-20221027-00002\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 07:44:58', '2022-10-27 07:44:58'),
(9, 'request_order', '{\"id\":\"635a36fb4224c909b65d3801\",\"external_id\":\"OTX-20221027-00002\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T07:54:59.498Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a36fb4224c909b65d3801\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T07:45:00.251Z\",\"updated\":\"2022-10-27T07:45:00.251Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 07:45:00', '2022-10-27 07:45:00'),
(10, 'callback_order', '{\"id\":\"635a36fb4224c909b65d3801\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00002\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T07:45:00.251Z\",\"updated\":\"2022-10-27T07:45:34.115Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_78b8c089-b59c-4a3c-aec8-de0a0ffdffb9\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T07:45:26.962Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-a22bfd30-119e-44fe-85b9-e325721377fa\"}', '52.89.130.89', '2022-10-27 07:45:35', '2022-10-27 07:45:35'),
(11, 'request_order', '{\"external_id\":\"OTX-20221027-00003\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 14:19:02', '2022-10-27 14:19:02'),
(12, 'request_order', '{\"id\":\"635a935818cc7b4ee6047416\",\"external_id\":\"OTX-20221027-00003\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T14:29:04.218Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a935818cc7b4ee6047416\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T14:19:04.779Z\",\"updated\":\"2022-10-27T14:19:04.779Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-27 14:19:04', '2022-10-27 14:19:04'),
(13, 'callback_order', '{\"id\":\"635a935818cc7b4ee6047416\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00003\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T14:19:04.779Z\",\"updated\":\"2022-10-27T14:20:08.092Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_f953ec62-c1f7-4f2c-89d6-551f6466adeb\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T14:19:46.189Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-7fe42a61-0c8b-463f-925c-f3745856d232\"}', '52.89.130.89', '2022-10-27 14:20:10', '2022-10-27 14:20:10'),
(14, 'request_order', '{\"external_id\":\"OTX-20221030-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-29 21:13:51', '2022-10-29 21:13:51'),
(15, 'request_order', '{\"id\":\"635d9790ad59d4a4c5ffc1c8\",\"external_id\":\"OTX-20221030-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-29T21:23:52.303Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635d9790ad59d4a4c5ffc1c8\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-29T21:13:53.047Z\",\"updated\":\"2022-10-29T21:13:53.047Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-10-29 21:13:53', '2022-10-29 21:13:53'),
(16, 'callback_order', '{\"id\":\"635d9790ad59d4a4c5ffc1c8\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221030-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-29T21:13:53.047Z\",\"updated\":\"2022-10-29T21:14:23.909Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_b774c3ab-50b6-4a7b-8363-e1cff950c7a5\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-29T21:14:14.342Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-6e658c19-bc02-46c4-a95a-5386764824b9\"}', '52.89.130.89', '2022-10-29 21:14:25', '2022-10-29 21:14:25'),
(17, 'request_order', '{\"external_id\":\"OTX-20221102-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-11-02 06:34:40', '2022-11-02 06:34:40'),
(18, 'request_order', '{\"id\":\"63620f82798a2f0d06c25cd1\",\"external_id\":\"OTX-20221102-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-11-02T06:44:42.494Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63620f82798a2f0d06c25cd1\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-11-02T06:34:43.269Z\",\"updated\":\"2022-11-02T06:34:43.269Z\",\"currency\":\"IDR\"}', '8.215.42.156', '2022-11-02 06:34:43', '2022-11-02 06:34:43'),
(19, 'callback_order', '{\"id\":\"63620f82798a2f0d06c25cd1\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221102-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-11-02T06:34:43.269Z\",\"updated\":\"2022-11-02T06:36:00.953Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_c9135a39-8a6a-4199-a251-397a6e46fcfd\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-11-02T06:35:52.583Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-5f584cd3-a90d-471f-b85c-3226fb2df641\"}', '52.89.130.89', '2022-11-02 06:36:03', '2022-11-02 06:36:03');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2019_08_19_000000_create_failed_jobs_table', 1),
(3, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(4, '2022_09_02_195636_create_projects_table', 1),
(5, '2022_09_03_053052_create_orders_table', 1),
(6, '2022_09_03_071514_create_settings_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `reference` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `payment_method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request` text COLLATE utf8mb4_unicode_ci,
  `response` text COLLATE utf8mb4_unicode_ci,
  `callback` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `type`, `status`, `reference`, `mode`, `payment_method`, `request`, `response`, `callback`, `url`, `created_at`, `updated_at`) VALUES
('20221026-00001', 'TPA', '', 'TPA-20220912-00009', 'sanbox', '', '{\"external_id\":\"TPA-20220912-00009\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"635949a02320721e2a79813b\",\"external_id\":\"TPA-20220912-00009\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-26T15:02:16.913Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635949a02320721e2a79813b\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"created\":\"2022-10-26T14:52:17.664Z\",\"updated\":\"2022-10-26T14:52:17.664Z\",\"currency\":\"IDR\"}', NULL, 'https://checkout-staging.xendit.co/web/635949a02320721e2a79813b', '2022-10-26 14:52:14', '2022-10-26 14:52:14'),
('20221026-00002', 'TPA', '', 'TPA-20220912-00009', 'sanbox', '', '{\"external_id\":\"TPA-20220912-00009\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"63594a6d1ee3f77bb5035256\",\"external_id\":\"TPA-20220912-00009\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-26T15:05:41.877Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63594a6d1ee3f77bb5035256\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"created\":\"2022-10-26T14:55:42.662Z\",\"updated\":\"2022-10-26T14:55:42.662Z\",\"currency\":\"IDR\"}', NULL, 'https://checkout-staging.xendit.co/web/63594a6d1ee3f77bb5035256', '2022-10-26 14:55:39', '2022-10-26 14:55:39'),
('20221026-00003', 'OTX', 'PAID', 'OTX-20220912-00009', 'sanbox', 'QRIS', '{\"external_id\":\"TPA-20220912-00009\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"63594a7cf366647de970a72c\",\"external_id\":\"TPA-20220912-00009\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":55004,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-26T15:05:56.255Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63594a7cf366647de970a72c\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":55004,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/ojol.arthopay.id\\/api\\/wallet\\/topup\\/callback\",\"created\":\"2022-10-26T14:55:57.037Z\",\"updated\":\"2022-10-26T14:55:57.037Z\",\"currency\":\"IDR\"}', '{\"id\":\"579c8d61f23fa4ca35e52da4\",\"external_id\":\"OTX-20220912-00009\",\"user_id\":\"5781d19b2e2385880609791c\",\"is_high\":true,\"payment_method\":\"WALLET\",\"status\":\"PAID\",\"merchant_name\":\"Xendit\",\"amount\":25000,\"paid_amount\":12000,\"bank_code\":\"QRIS\",\"paid_at\":\"2016-10-12T08:15:03.404Z\",\"payer_email\":\"wildan@xendit.co\",\"description\":\"This is a description\",\"adjusted_received_amount\":12000,\"fees_paid_amount\":0,\"updated\":\"2016-10-10T08:15:03.404Z\",\"created\":\"2016-10-10T08:15:03.404Z\",\"currency\":\"IDR\",\"payment_channel\":\"QRIS\",\"payment_destination\":\"888888888888\"}', 'https://checkout-staging.xendit.co/web/63594a7cf366647de970a72c', '2022-10-26 14:55:54', '2022-10-26 16:07:31'),
('20221026-00004', 'OTX', 'PAID', 'OTX-20221026-00005', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221026-00005\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"63595ec3f366642ebb70a95b\",\"external_id\":\"OTX-20221026-00005\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-26T16:32:27.021Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63595ec3f366642ebb70a95b\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-26T16:22:27.728Z\",\"updated\":\"2022-10-26T16:22:27.728Z\",\"currency\":\"IDR\"}', '{\"id\":\"63595ec3f366642ebb70a95b\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221026-00005\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-26T16:22:27.728Z\",\"updated\":\"2022-10-26T16:23:04.317Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_4cb38de0-56b0-4ab9-a5aa-df86a0c5f4d1\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-26T16:22:56.737Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-56de51fd-9cba-4402-8073-9919cdbd3500\"}', 'https://checkout-staging.xendit.co/web/63595ec3f366642ebb70a95b', '2022-10-26 16:22:23', '2022-10-26 16:23:06'),
('20221027-00001', 'OTX', 'PAID', 'OTX-20221027-00001', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221027-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"635a36721654a07696154083\",\"external_id\":\"OTX-20221027-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T07:52:42.352Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a36721654a07696154083\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T07:42:42.971Z\",\"updated\":\"2022-10-27T07:42:42.971Z\",\"currency\":\"IDR\"}', '{\"id\":\"635a36721654a07696154083\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T07:42:42.971Z\",\"updated\":\"2022-10-27T07:43:09.718Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_93f913fa-1a13-40c9-9d88-d0273cc9d204\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T07:43:01.799Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-9c90f613-d30f-4b38-80ed-d1ae5fbd5681\"}', 'https://checkout-staging.xendit.co/web/635a36721654a07696154083', '2022-10-27 07:42:40', '2022-10-27 07:43:11'),
('20221027-00002', 'OTX', 'PAID', 'OTX-20221027-00002', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221027-00002\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"635a36fb4224c909b65d3801\",\"external_id\":\"OTX-20221027-00002\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T07:54:59.498Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a36fb4224c909b65d3801\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T07:45:00.251Z\",\"updated\":\"2022-10-27T07:45:00.251Z\",\"currency\":\"IDR\"}', '{\"id\":\"635a36fb4224c909b65d3801\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00002\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T07:45:00.251Z\",\"updated\":\"2022-10-27T07:45:34.115Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_78b8c089-b59c-4a3c-aec8-de0a0ffdffb9\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T07:45:26.962Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-a22bfd30-119e-44fe-85b9-e325721377fa\"}', 'https://checkout-staging.xendit.co/web/635a36fb4224c909b65d3801', '2022-10-27 07:44:58', '2022-10-27 07:45:35'),
('20221027-00003', 'OTX', 'PAID', 'OTX-20221027-00003', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221027-00003\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"635a935818cc7b4ee6047416\",\"external_id\":\"OTX-20221027-00003\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-27T14:29:04.218Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635a935818cc7b4ee6047416\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-27T14:19:04.779Z\",\"updated\":\"2022-10-27T14:19:04.779Z\",\"currency\":\"IDR\"}', '{\"id\":\"635a935818cc7b4ee6047416\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221027-00003\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-27T14:19:04.779Z\",\"updated\":\"2022-10-27T14:20:08.092Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_f953ec62-c1f7-4f2c-89d6-551f6466adeb\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-27T14:19:46.189Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-7fe42a61-0c8b-463f-925c-f3745856d232\"}', 'https://checkout-staging.xendit.co/web/635a935818cc7b4ee6047416', '2022-10-27 14:19:02', '2022-10-27 14:20:10'),
('20221030-00001', 'OTX', 'PAID', 'OTX-20221030-00001', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221030-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"635d9790ad59d4a4c5ffc1c8\",\"external_id\":\"OTX-20221030-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-10-29T21:23:52.303Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/635d9790ad59d4a4c5ffc1c8\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-10-29T21:13:53.047Z\",\"updated\":\"2022-10-29T21:13:53.047Z\",\"currency\":\"IDR\"}', '{\"id\":\"635d9790ad59d4a4c5ffc1c8\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221030-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-10-29T21:13:53.047Z\",\"updated\":\"2022-10-29T21:14:23.909Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_b774c3ab-50b6-4a7b-8363-e1cff950c7a5\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-10-29T21:14:14.342Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-6e658c19-bc02-46c4-a95a-5386764824b9\"}', 'https://checkout-staging.xendit.co/web/635d9790ad59d4a4c5ffc1c8', '2022-10-29 21:13:50', '2022-10-29 21:14:25'),
('20221102-00001', 'OTX', 'PAID', 'OTX-20221102-00001', 'sanbox', 'DANA', '{\"external_id\":\"OTX-20221102-00001\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"invoice_duration\":600,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"currency\":\"IDR\"}', '{\"id\":\"63620f82798a2f0d06c25cd1\",\"external_id\":\"OTX-20221102-00001\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"status\":\"PENDING\",\"merchant_name\":\"XFIT\",\"merchant_profile_picture_url\":\"https:\\/\\/xnd-merchant-logos.s3.amazonaws.com\\/business\\/production\\/633fa2f274d9b97e451e6992-1665115640630.jpeg\",\"amount\":11000,\"description\":\"Pembayaran X-Fit\",\"expiry_date\":\"2022-11-02T06:44:42.494Z\",\"invoice_url\":\"https:\\/\\/checkout-staging.xendit.co\\/web\\/63620f82798a2f0d06c25cd1\",\"available_banks\":[{\"bank_code\":\"MANDIRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BRI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BNI\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"PERMATA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0},{\"bank_code\":\"BCA\",\"collection_type\":\"POOL\",\"transfer_amount\":11000,\"bank_branch\":\"Virtual Account\",\"account_holder_name\":\"XFIT\",\"identity_amount\":0}],\"available_retail_outlets\":[{\"retail_outlet_name\":\"ALFAMART\"},{\"retail_outlet_name\":\"INDOMARET\"}],\"available_ewallets\":[{\"ewallet_type\":\"OVO\"},{\"ewallet_type\":\"DANA\"},{\"ewallet_type\":\"SHOPEEPAY\"},{\"ewallet_type\":\"LINKAJA\"},{\"ewallet_type\":\"ASTRAPAY\"}],\"available_direct_debits\":[{\"direct_debit_type\":\"DD_BRI\"}],\"available_paylaters\":[],\"should_exclude_credit_card\":false,\"should_send_email\":false,\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"created\":\"2022-11-02T06:34:43.269Z\",\"updated\":\"2022-11-02T06:34:43.269Z\",\"currency\":\"IDR\"}', '{\"id\":\"63620f82798a2f0d06c25cd1\",\"user_id\":\"633fa2f274d9b97e451e6992\",\"external_id\":\"OTX-20221102-00001\",\"is_high\":false,\"status\":\"PAID\",\"merchant_name\":\"XFIT\",\"amount\":11000,\"created\":\"2022-11-02T06:34:43.269Z\",\"updated\":\"2022-11-02T06:36:00.953Z\",\"description\":\"Pembayaran X-Fit\",\"payment_id\":\"ewc_c9135a39-8a6a-4199-a251-397a6e46fcfd\",\"paid_amount\":11000,\"payment_method\":\"EWALLET\",\"ewallet_type\":\"DANA\",\"currency\":\"IDR\",\"paid_at\":\"2022-11-02T06:35:52.583Z\",\"payment_channel\":\"DANA\",\"success_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/test\",\"failure_redirect_url\":\"https:\\/\\/panel.xfitindonesia.id\\/api\\/order\\/payment\\/middleware\\/callback\",\"payment_method_id\":\"pm-5f584cd3-a90d-471f-b85c-3226fb2df641\"}', 'https://checkout-staging.xendit.co/web/63620f82798a2f0d06c25cd1', '2022-11-02 06:34:40', '2022-11-02 06:36:03');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secure` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `callback` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `type`, `key`, `secure`, `value`, `callback`, `created_at`, `updated_at`) VALUES
(6, 'Top Up XFIT', 'TUX', 'q9iiryur6L', 'lVmaoNJ42BC2p0Jpd9C6', 'e4AzvMfIFPrVEubkhR0CTffDDgZ6v4JDCcwl6KjN22qRXPAMpmMtKVmGRbTp', 'https://panel.xfitindonesia.id/api/wallet/topup/callback', '2022-09-13 15:46:58', '2022-09-13 15:46:58'),
(7, 'Order Transaction XFIT', 'OTX', 'cdLUJTzQQg', 'MEAhO6cpzTrCQ2lQfyU0', 'yV0zkJkgGWYMGmJTiJP2nKZGWw1asbsZaHUkznVPfSVRsoDLMKsKAfhAYPAJ', 'https://panel.xfitindonesia.id/api/order/payment/middleware/callback', '2022-09-13 15:47:55', '2022-09-13 15:47:55');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'xendit_publickey', 'xnd_public_development_2vNvQzu1x2UxEmLSYQ1lTvilAqtNP753CvezGGKEFmfEStLcZDJLfx3tU7JjQH', '2022-09-03 00:21:05', '2022-09-03 00:21:05'),
(2, 'xendit_secretkey_sanbox', 'xnd_development_YkBmICLH0wEJ6dPn50b6JO2TcOFtEvkeLOgnJOQSjfieCR8xKxrbKZhhMAPzrj', '2022-09-03 00:21:05', '2022-09-03 00:21:05'),
(3, 'xendit_tokencallback', '5e949270191351b3d093c72bfea80955b7ea80195a90ec813178c81cb7f16140', '2022-09-03 00:21:05', '2022-09-03 00:21:05'),
(4, 'xendit_secretkey_prod', 'xnd_production_eU95wz6WGU5hEEOMJhlZf9G2j5rUs5QCHPDqr9cipEy69gOrpiZfOQLIJbz4f', '2022-09-03 00:21:05', '2022-09-03 00:21:05'),
(5, 'xendit_tokencallback_sanbox', '8r4BUoShZrZ8nv6BkZf6cvwdEnxhuFrJRQ5HlNYEHlgXJxiW', '2022-09-03 00:21:05', '2022-09-03 00:21:05'),
(6, 'url_success', 'https://panel.xfitindonesia.id/test', '2022-09-04 16:32:44', '2022-09-04 16:32:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `log__6`
--
ALTER TABLE `log__6`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log__7`
--
ALTER TABLE `log__7`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log__6`
--
ALTER TABLE `log__6`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log__7`
--
ALTER TABLE `log__7`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
