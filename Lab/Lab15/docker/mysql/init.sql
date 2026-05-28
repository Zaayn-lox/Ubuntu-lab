CREATE DATABASE IF NOT EXISTS boardy_laravel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS boardy_api
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON boardy_laravel.* TO 'boardy'@'%';
GRANT ALL PRIVILEGES ON boardy_api.* TO 'boardy'@'%';

USE boardy_api;

CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    body TEXT NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    author_name VARCHAR(255) NOT NULL DEFAULT 'Unknown',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY comments_post_id_index (post_id),
    KEY comments_author_id_index (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

FLUSH PRIVILEGES;
