-- MovieHub database schema
-- Run automatically via docker-entrypoint-initdb.d on first MySQL startup

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  remember_token VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tmdb_id INT UNSIGNED DEFAULT NULL,
  imdb_id VARCHAR(20) DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  original_title VARCHAR(255) DEFAULT NULL,
  category ENUM('movie','series') NOT NULL DEFAULT 'movie',
  overview TEXT,
  release_date DATE DEFAULT NULL,
  release_year SMALLINT UNSIGNED DEFAULT NULL,
  genres VARCHAR(255) DEFAULT NULL,
  runtime SMALLINT UNSIGNED DEFAULT NULL,
  rating_average DECIMAL(3,1) DEFAULT NULL,
  vote_count INT UNSIGNED DEFAULT NULL,
  poster_url VARCHAR(255) DEFAULT NULL,
  backdrop_url VARCHAR(255) DEFAULT NULL,
  accent_color CHAR(7) DEFAULT NULL,
  status ENUM('pending','published','archived') NOT NULL DEFAULT 'published',
  source ENUM('tmdb','manual') NOT NULL DEFAULT 'tmdb',
  submitted_by INT UNSIGNED DEFAULT NULL,
  approved_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tmdb (tmdb_id),
  UNIQUE KEY uq_imdb (imdb_id),
  CONSTRAINT fk_movies_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_movies_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS watchlist_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  movie_id INT UNSIGNED NOT NULL,
  status ENUM('planned','watching','watched') NOT NULL DEFAULT 'planned',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_watchlist_user_movie (user_id, movie_id),
  CONSTRAINT fk_watchlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_watchlist_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movie_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reviews_user_movie (user_id, movie_id),
  INDEX idx_reviews_movie (movie_id),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movie_activity (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movie_id INT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_movie_activity_movie (movie_id),
  CONSTRAINT fk_activity_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
