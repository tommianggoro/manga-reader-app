-- Skema database untuk manga reader pribadi
-- Import file ini lewat phpMyAdmin di hosting kamu

CREATE TABLE IF NOT EXISTS mangas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manga_id VARCHAR(36) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    cover_image_url VARCHAR(500),
    latest_chapter_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id VARCHAR(36) UNIQUE NOT NULL,
    manga_id VARCHAR(36) NOT NULL,
    chapter_number INT NOT NULL,
    chapter_title VARCHAR(255),
    base_url VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manga_id) REFERENCES mangas(manga_id) ON DELETE CASCADE,
    INDEX idx_manga_chapter (manga_id, chapter_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chapter_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id VARCHAR(36) NOT NULL,
    page_number INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE,
    INDEX idx_chapter_page (chapter_id, page_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
