ALTER TABLE `recipe`
    ADD `buy_price` INT(11) DEFAULT 0 NOT NULL AFTER `sell_price`;
ALTER TABLE `recipe`
    ADD `buy_profit` INT(11) DEFAULT 0 NOT NULL AFTER `profit`;
