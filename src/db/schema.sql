CREATE TABLE IF NOT EXISTS laws (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  article_number VARCHAR(100) NOT NULL,
  content LONGTEXT NOT NULL,
  tags JSON,
  document_title VARCHAR(255) NULL,
  law_reference VARCHAR(100) NULL,
  category VARCHAR(100) NULL,
  source_name VARCHAR(255) NULL,
  source_url VARCHAR(512) NULL,
  language VARCHAR(10) NOT NULL DEFAULT 'fr',
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS law_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  law_id INT NOT NULL,
  source_language VARCHAR(10) NOT NULL,
  target_language VARCHAR(10) NOT NULL,
  translated_title TEXT NOT NULL,
  translated_content LONGTEXT NOT NULL,
  provider VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_law_translation (law_id, target_language),
  CONSTRAINT fk_law_translations_law
    FOREIGN KEY (law_id) REFERENCES laws(id)
    ON DELETE CASCADE
);
