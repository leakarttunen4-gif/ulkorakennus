-- Karelia Ulkorakennus Oy — Ajanvarausjärjestelmä
-- Aja tämä ensin MySQL/MariaDB-tietokannassasi

CREATE TABLE IF NOT EXISTS roolit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nimi VARCHAR(100) NOT NULL,
  kuvaus VARCHAR(255),
  aktiivinen TINYINT(1) DEFAULT 1,
  luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Esimerkkiroolit
INSERT INTO roolit (nimi, kuvaus) VALUES
  ('Suunnittelija', 'Kartoituskäynti ja suunnittelu'),
  ('Rakentaja', 'Rakennustyöt');

CREATE TABLE IF NOT EXISTS ajat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rooli_id INT NOT NULL,
  paivamaara DATE NOT NULL,
  alkuaika TIME NOT NULL,
  loppuaika TIME NOT NULL,
  varattu TINYINT(1) DEFAULT 0,
  luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rooli_id) REFERENCES roolit(id) ON DELETE CASCADE,
  UNIQUE KEY uniikki_aika (rooli_id, paivamaara, alkuaika)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS varaukset (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aika_id INT NOT NULL,
  etunimi VARCHAR(100) NOT NULL,
  sukunimi VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  puhelin VARCHAR(50) NOT NULL,
  osoite VARCHAR(255) NOT NULL,
  palvelut VARCHAR(500),
  lisatiedot TEXT,
  peruutusavain VARCHAR(64) NOT NULL UNIQUE,
  tila ENUM('vahvistettu','peruutettu') DEFAULT 'vahvistettu',
  luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aika_id) REFERENCES ajat(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
