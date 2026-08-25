-- LifeQuest schema (MySQL 8+)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_key VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  locale VARCHAR(16) NOT NULL DEFAULT 'es_AR',
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Argentina/Buenos_Aires',
  theme VARCHAR(16) NOT NULL DEFAULT 'light',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS life_areas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  area_key VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(16) NOT NULL,
  icon VARCHAR(64) NOT NULL DEFAULT 'circle',
  sort_order INT NOT NULL DEFAULT 0,
  scope ENUM('annual','horizon') NOT NULL DEFAULT 'annual',
  annual_goals_only TINYINT(1) NOT NULL DEFAULT 0,
  detail_system VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_life_areas_user_key (user_id, area_key),
  KEY idx_life_areas_scope (user_id, scope),
  CONSTRAINT fk_life_areas_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annual_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  year SMALLINT NOT NULL,
  title VARCHAR(160) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_annual_plans_user_year (user_id, year),
  CONSTRAINT fk_annual_plans_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annual_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annual_plan_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_annual_sections (annual_plan_id, area_id),
  CONSTRAINT fk_annual_sections_plan FOREIGN KEY (annual_plan_id) REFERENCES annual_plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_annual_sections_area FOREIGN KEY (area_id) REFERENCES life_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_series (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  series_key VARCHAR(64) NOT NULL,
  title_template VARCHAR(255) NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  external_system VARCHAR(64) NULL,
  target_value DECIMAL(12,2) NULL,
  unit VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_goal_series_user_key (user_id, series_key),
  CONSTRAINT fk_goal_series_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_goal_series_area FOREIGN KEY (area_id) REFERENCES life_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  goal_key VARCHAR(96) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  goal_type ENUM('goal','project') NOT NULL DEFAULT 'goal',
  area_id BIGINT UNSIGNED NULL,
  impact_area_id BIGINT UNSIGNED NULL,
  parent_goal_id BIGINT UNSIGNED NULL,
  series_id BIGINT UNSIGNED NULL,
  horizon ENUM('largo_plazo','anual','trimestral','mensual') NOT NULL DEFAULT 'anual',
  period_year SMALLINT NULL,
  period_quarter TINYINT NULL,
  period_month TINYINT NULL,
  status ENUM('idea','planned','active','paused','completed','cancelled','archived') NOT NULL DEFAULT 'planned',
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  progress_mode ENUM('manual','milestones','quantity','binary','linked_habits','months') NOT NULL DEFAULT 'months',
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  target_value DECIMAL(12,2) NULL,
  current_value DECIMAL(12,2) NULL,
  unit VARCHAR(64) NULL,
  start_date DATE NULL,
  due_date DATE NULL,
  success_criteria TEXT NULL,
  motivation TEXT NULL,
  next_action VARCHAR(255) NULL,
  external_system VARCHAR(64) NULL,
  external_url VARCHAR(500) NULL,
  source_system VARCHAR(32) NOT NULL DEFAULT 'lifequest',
  source_page VARCHAR(500) NULL,
  source_text TEXT NULL,
  source_checked TINYINT(1) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_goals_user_key (user_id, goal_key),
  KEY idx_goals_year_status (user_id, period_year, status),
  KEY idx_goals_area (area_id),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_goals_area FOREIGN KEY (area_id) REFERENCES life_areas(id),
  CONSTRAINT fk_goals_impact_area FOREIGN KEY (impact_area_id) REFERENCES life_areas(id),
  CONSTRAINT fk_goals_parent FOREIGN KEY (parent_goal_id) REFERENCES goals(id),
  CONSTRAINT fk_goals_series FOREIGN KEY (series_id) REFERENCES goal_series(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_milestones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  due_date DATE NULL,
  is_completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_milestones_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  due_date DATE NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  done_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_actions_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_progress_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  progress_percent DECIMAL(5,2) NULL,
  value_delta DECIMAL(12,2) NULL,
  comment TEXT NULL,
  evidence_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_progress_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_month_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  month_num TINYINT UNSIGNED NOT NULL,
  is_checked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_goal_month (goal_id, month_num),
  CONSTRAINT fk_goal_month_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(32) NULL,
  to_status VARCHAR(32) NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_status_hist_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  habit_key VARCHAR(96) NULL,
  display_number INT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  area_id BIGINT UNSIGNED NULL,
  frequency_type ENUM('daily','weekdays','weekly','monthly','interval','custom_days') NOT NULL DEFAULT 'daily',
  tracking_mode ENUM('months','units','daily') NOT NULL DEFAULT 'months',
  target_per_period INT NOT NULL DEFAULT 1,
  current_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  unit VARCHAR(64) NOT NULL DEFAULT 'vez',
  minimum_value DECIMAL(12,2) NULL,
  preferred_time ENUM('morning','afternoon','evening','anytime') NOT NULL DEFAULT 'anytime',
  start_date DATE NULL,
  end_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  linked_goal_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_habits_user_key (user_id, habit_key),
  KEY idx_habits_active_number (user_id, active, display_number),
  CONSTRAINT fk_habits_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_habits_area FOREIGN KEY (area_id) REFERENCES life_areas(id),
  CONSTRAINT fk_habits_goal FOREIGN KEY (linked_goal_id) REFERENCES goals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habit_schedule_days (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  habit_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_habit_day (habit_id, weekday),
  CONSTRAINT fk_schedule_habit FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habit_month_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  habit_id BIGINT UNSIGNED NOT NULL,
  year_num SMALLINT NOT NULL,
  month_num TINYINT UNSIGNED NOT NULL,
  is_checked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_habit_year_month (habit_id, year_num, month_num),
  CONSTRAINT fk_habit_month_habit FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS books (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  year_num SMALLINT NOT NULL,
  planned_month TINYINT UNSIGNED NULL,
  finished_at DATE NULL,
  pages INT UNSIGNED NULL,
  pages_read INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('planned','reading','finished') NOT NULL DEFAULT 'planned',
  goodreads_url VARCHAR(500) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_books_user_year (user_id, year_num),
  CONSTRAINT fk_books_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  habit_id BIGINT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  status ENUM('completed','partial','skipped_justified','missed') NOT NULL DEFAULT 'completed',
  quantity DECIMAL(12,2) NULL,
  note TEXT NULL,
  logged_at TIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_habit_log_date (habit_id, log_date),
  CONSTRAINT fk_habit_logs_habit FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS period_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  review_type ENUM('weekly','monthly','quarterly','annual') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  answers_json JSON NULL,
  status ENUM('draft','completed') NOT NULL DEFAULT 'draft',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_reviews_user_type (user_id, review_type, period_start),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  system_name VARCHAR(64) NOT NULL,
  title VARCHAR(160) NOT NULL,
  url VARCHAR(500) NOT NULL,
  related_goal_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_ext_links_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ext_links_goal FOREIGN KEY (related_goal_id) REFERENCES goals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_metric_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  filters_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_metric_views_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(32) NOT NULL,
  checksum VARCHAR(128) NULL,
  status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
  report_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_import_batches_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_staging_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_batch_id BIGINT UNSIGNED NOT NULL,
  notion_block_id VARCHAR(128) NULL,
  source_page_id VARCHAR(128) NULL,
  parent_block_id VARCHAR(128) NULL,
  source_text TEXT NOT NULL,
  block_type VARCHAR(64) NULL,
  is_checked TINYINT(1) NULL,
  source_url VARCHAR(500) NULL,
  period_year SMALLINT NULL,
  period_month TINYINT NULL,
  classification VARCHAR(64) NULL,
  canonical_goal_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staging_block (import_batch_id, notion_block_id),
  CONSTRAINT fk_staging_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_staging_goal FOREIGN KEY (canonical_goal_id) REFERENCES goals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legacy_monthly_pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  period_year SMALLINT NOT NULL,
  period_month TINYINT NOT NULL,
  source_url VARCHAR(500) NULL,
  item_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legacy_page (user_id, period_year, period_month),
  CONSTRAINT fk_legacy_pages_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legacy_monthly_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED NOT NULL,
  source_text TEXT NOT NULL,
  is_checked TINYINT(1) NULL,
  classification VARCHAR(64) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_legacy_items_page FOREIGN KEY (page_id) REFERENCES legacy_monthly_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annual_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  year SMALLINT NOT NULL,
  visible_controls INT NOT NULL DEFAULT 0,
  checked_controls INT NOT NULL DEFAULT 0,
  source_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_annual_sources (user_id, year),
  CONSTRAINT fk_annual_sources_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekly_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  task_date DATE NOT NULL,
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_weekly_tasks_user_date (user_id, task_date, deleted_at),
  CONSTRAINT fk_weekly_tasks_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rule_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_rule_categories_user (user_id, deleted_at, sort_order),
  CONSTRAINT fk_rule_categories_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS own_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_own_rules_user_cat (user_id, category_id, deleted_at, sort_order),
  CONSTRAINT fk_own_rules_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_own_rules_category FOREIGN KEY (category_id) REFERENCES rule_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
