CREATE TABLE IF NOT EXISTS `#__targetpay_ideal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cart_id` varchar(11) NOT NULL DEFAULT '0',
  `rtlo` int(11) NOT NULL,
  `paymethod` varchar(8) NOT NULL DEFAULT 'IDE',
  `transaction_id` varchar(255) NOT NULL,
  `bank_id` varchar(8) NOT NULL,
  `description` int(64) NOT NULL,
  `amount` decimal(11,2) NOT NULL,
  `bankaccount` varchar(25) DEFAULT NULL,
  `name` varchar(35) DEFAULT NULL,
  `city` varchar(25) DEFAULT NULL,
  `status` int(5) NOT NULL,
  `via` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_id` (`cart_id`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;
