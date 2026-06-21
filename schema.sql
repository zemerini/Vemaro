-- Database creation script for Vemaro
-- Formatted for MySQL / XAMPP / MySQL Workbench / IONOS

CREATE DATABASE IF NOT EXISTS `vemaro_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vemaro_db`;

-- Table structure for table `jobs`
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `workdays` VARCHAR(100) NOT NULL,
  `startDate` VARCHAR(100) NOT NULL,
  `employmentTypes` TEXT NOT NULL, -- Stored as a JSON-encoded string (array of strings)
  `description` TEXT NOT NULL,
  `tasks` TEXT NOT NULL,           -- Stored as a JSON-encoded string (array of strings)
  `requirements` TEXT NOT NULL,    -- Stored as a JSON-encoded string (array of strings)
  `benefits` TEXT NOT NULL,        -- Stored as a JSON-encoded string (array of strings)
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserting default jobs for a smooth transition from jobs.json
INSERT INTO `jobs` (`id`, `title`, `location`, `workdays`, `startDate`, `employmentTypes`, `description`, `tasks`, `requirements`, `benefits`) VALUES
('JOB-WV-001', 
 'Warenverräumer (m/w/d)', 
 'Bremen und Umgebung (z. B. Bremen-Mitte, Bremen-Nord, Huchting, Hemelingen, Findorff)', 
 'Mo-Sa', 
 'ab sofort', 
 '[\"Minijob\",\"Teilzeit\",\"Werkstudent\",\"Vollzeit\"]', 
 'Als Warenverräumer:in bist du für die ordnungsgemäße und ansprechende Präsentation der Waren im Markt verantwortlich. Du sorgst dafür, dass die Regale stets gut gefüllt sind und die Produkte ordentlich und verkaufsfördernd präsentiert werden. Außerdem kontrollierst du Qualität, Frische und Mindesthaltbarkeitsdatum und hilfst Kund:innen bei Fragen auf der Verkaufsfläche.', 
 '[\"Verräumen von Waren aus dem Lager in die Regale und auf die Verkaufsflächen\",\"Pflege und Ordnung der Regalflächen\",\"Kontrolle von Frische, Qualität und MHD\",\"Aussortieren nicht mehr verkaufsfähiger Waren\",\"Freundliche Unterstützung der Kund:innen bei Fragen\"]', 
 '[\"Teamfähigkeit und Flexibilität\",\"Grundkenntnisse Deutsch wünschenswert\",\"Zuverlässiges, gepflegtes Auftreten\",\"Belastbarkeit und körperliche Fitness\",\"Erste Erfahrungen im Einzelhandel von Vorteil, aber kein Muss\"]', 
 '[\"Persönliche Ansprechpartner\",\"Unbefristeter Arbeitsvertrag\",\"Fahrtkostenzuschuss\",\"Gehaltsvorschuss\",\"Faire und pünktliche Bezahlung\",\"Einen zentralen und sicheren Arbeitsplatz\",\"Halbjahres-Geschäftsessen\",\"Arbeitskleidung\",\"Ausführliche Einarbeitungen\"]'),

('JOB-KA-002', 
 'Kassierer:in / Verkäufer:in (m/w/d)', 
 'Bremen und Umgebung (z. B. Bremen-Mitte, Bremen-Nord, Huchting, Hemelingen, Findorff)', 
 'Mo-Sa', 
 'ab sofort', 
 '[\"Minijob\",\"Teilzeit\",\"Werkstudent\",\"Vollzeit\"]', 
 'Als Kassenkraft bist du das freundliche Gesicht im Kassenbereich unserer Partnerfilialen. Du sorgst für einen zügigen und exakten Zahlungsvorgang, prüfst Waren und Gutscheine, pflegst die Kasse und stehst Kund:innen bei Fragen hilfsbereit zur Seite.', 
 '[\"Abwicklung aller Zahlungsarten (Bargeld, EC-, Kreditkarte, Gutscheine)\",\"Korrekte Bedienung des Kassensystems und Kassenabschluss\",\"Prüfung von Waren auf Vollständigkeit und Unversehrtheit\",\"Unterstützung bei Kund:innenanfragen und Reklamationen\",\"Ordnung und Sauberkeit im Kassenbereich\"]', 
 '[\"Erste Erfahrungen an der Kasse oder im direkten Kundenkontakt von Vorteil\",\"Freundliches, serviceorientiertes Auftreten\",\"Sehr gute Deutschkenntnisse in Wort und Schrift\",\"Zuverlässigkeit und Verantwortungsbewusstsein\",\"Belastbarkeit und Teamfähigkeit\"]', 
 '[\"Persönliche Ansprechpartner\",\"Unbefristeter Arbeitsvertrag\",\"Fahrtkostenzuschuss\",\"Gehaltsvorschuss\",\"Faire und pünktliche Bezahlung\",\"Einen zentralen und sicheren Arbeitsplatz\",\"Halbjahres-Geschäftsessen\",\"Arbeitskleidung\",\"Ausführliche Einarbeitungen\"]'),

('JOB-LA-003', 
 'Lagerhelfer (m/w/d)', 
 'Bremen und Umgebung (z. B. Bremen-Mitte, Bremen-Nord, Huchting, Hemelingen, Findorff)', 
 'Mo-Sa', 
 'ab sofort', 
 '[\"Minijob\",\"Teilzeit\",\"Werkstudent\",\"Vollzeit\"]', 
 'Als Lagerhelfer:in unterstützt du unsere Partnerlogistikbereiche tatkräftig bei allen anfallenden Tätigkeiten. Du sorgst dafür, dass Waren eingelagert, kommissioniert und für den Weitertransport vorbereitet werden. Dabei arbeitest du eng mit dem Team zusammen und stellst einen reibungsloser Warenfluss sicher.', 
 '[\"Warenannahme und Eingangskontrolle\",\"Einlagerung und sachgerechte Lagerplatzpflege\",\"Kommissionierung von Kundenaufträgen\",\"Verpacken und Versandvorbereitung\",\"Kontrolle von Beständen und Durchführung von Inventuren\",\"Ordnung und Sauberkeit im Lagerbereich\"]', 
 '[\"Körperliche Fitness und Belastbarkeit\",\"Teamfähigkeit und Zuverlässigkeit\",\"Grundkenntnisse Deutsch wünschenswert\",\"Sorgfalt und Genauigkeit\",\"Erste Erfahrungen im Lager oder Logistikbereich von Vorteil, aber kein Muss\"]', 
 '[\"Persönliche Ansprechpartner\",\"Unbefristeter Arbeitsvertrag\",\"Fahrtkostenzuschuss\",\"Gehaltsvorschuss\",\"Faire und pünktliche Bezahlung\",\"Einen zentralen und sicheren Arbeitsplatz\",\"Halbjahres-Geschäftsessen\",\"Arbeitskleidung\",\"Ausführliche Einarbeitungen\"]');
