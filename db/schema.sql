CREATE TABLE entrant (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phone_number VARCHAR(255) NOT NULL,
  raffle_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE raffle (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  sid VARCHAR(255) NOT NULL,
  raffle_name varchar(255) NOT NULL,
  is_complete BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `raffle_item` (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  raffle_id INT NOT NULL,
  item VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
