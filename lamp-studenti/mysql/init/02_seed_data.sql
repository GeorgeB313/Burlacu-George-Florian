SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Seed baseline users and curated movies

INSERT INTO users (username, email, password_hash, role) VALUES
  ('admin', 'admin@moviehub.local', '$2y$10$43gacRt5jRH7paZaQ9F8jubdYtnZmBK0FpfcSGawm6ChCZVxpeDRK', 'admin'),
  ('demo', 'demo@moviehub.local', '$2y$10$aG2x9WwD1KgxAx3ceKoGGevHNoeBM.U1abtNwqGru88opzjUISrcu', 'user')
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role);

INSERT INTO movies (tmdb_id, imdb_id, title, original_title, category, overview, release_date, release_year, genres, rating_average, vote_count, accent_color)
VALUES
  (27205, 'tt1375666', 'Inception', NULL, 'movie', 'Dom Cobb este un hoț care fură secrete din subconștient.', '2010-07-15', 2010, 'Sci-Fi, Acțiune', 8.8, 2000000, '#1a1d24'),
  (157336, 'tt0816692', 'Interstellar', NULL, 'movie', 'O echipă de exploratori călătorește printr-o gaură de vierme pentru a salva omenirea.', '2014-11-05', 2014, 'SF, Aventură', 8.6, 1900000, '#1a2536'),
  (155, 'tt0468569', 'The Dark Knight', NULL, 'movie', 'Batman se confruntă cu Joker pentru sufletul orașului Gotham.', '2008-07-16', 2008, 'Acțiune, Crimă', 9.0, 2600000, '#111318'),
  (603, 'tt0133093', 'The Matrix', NULL, 'movie', 'Un hacker descoperă adevărul despre realitate și își află destinul.', '1999-03-31', 1999, 'SF, Acțiune', 8.7, 2100000, '#0c1e22'),
  (550, 'tt0137523', 'Fight Club', NULL, 'movie', 'Un insomniac și un vânzător de săpun pornesc un club subteran.', '1999-10-15', 1999, 'Dramă, Thriller', 8.8, 1900000, '#222222'),
  (496243, 'tt6751668', 'Parasite', NULL, 'movie', 'Două familii se intersectează cu consecințe neașteptate.', '2019-05-30', 2019, 'Thriller, Dramă', 8.5, 900000, '#252210'),
  (238, 'tt0068646', 'The Godfather', NULL, 'movie', 'Saga familiei Corleone în lumea crimei organizate.', '1972-03-14', 1972, 'Crimă, Dramă', 9.2, 1800000, '#1b1b1b'),
  (680, 'tt0110912', 'Pulp Fiction', NULL, 'movie', 'Povești interconectate despre crimă și răscumpărare.', '1994-09-10', 1994, 'Crimă, Dramă', 8.9, 1800000, '#2a1a1a'),
  (278, 'tt0111161', 'The Shawshank Redemption', NULL, 'movie', 'Prietenia a doi deținuți și speranța dincolo de ziduri.', '1994-09-23', 1994, 'Dramă', 9.3, 2800000, '#1b263b'),
  (98, 'tt0172495', 'Gladiator', NULL, 'movie', 'Un general roman devine gladiator pentru a se răzbuna.', '2000-05-01', 2000, 'Acțiune, Dramă', 8.5, 1500000, '#231a14'),
  (693134, 'tt15239678', 'Dune: Part Two', NULL, 'movie', 'Paul Atreides își continuă drumul alături de fremeni.', '2024-02-27', 2024, 'SF, Aventură', 8.6, 400000, '#0f1d21'),
  (475557, 'tt7286456', 'Joker', NULL, 'movie', 'Originea personajului Joker.', '2019-10-02', 2019, 'Dramă, Thriller', 8.4, 1500000, '#1a1f1d'),
  (13, 'tt0109830', 'Forrest Gump', NULL, 'movie', 'Povestea extraordinară a lui Forrest Gump.', '1994-06-23', 1994, 'Dramă, Romance', 8.8, 2200000, '#1c2428'),
  (19995, 'tt0499549', 'Avatar', NULL, 'movie', 'Un paraplegic este trimis pe Pandora într-o misiune unică.', '2009-12-10', 2009, 'SF, Aventură', 7.9, 2600000, '#0d1f2d'),
  (1124, 'tt0482571', 'The Prestige', NULL, 'movie', 'Doi magicieni rivali într-o competiție periculoasă.', '2006-10-19', 2006, 'Thriller, Mister', 8.5, 1400000, '#1a1410')
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  category = VALUES(category),
  overview = VALUES(overview),
  release_date = VALUES(release_date),
  release_year = VALUES(release_year),
  genres = VALUES(genres),
  rating_average = VALUES(rating_average),
  vote_count = VALUES(vote_count),
  accent_color = VALUES(accent_color);
