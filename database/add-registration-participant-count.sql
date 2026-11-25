ALTER TABLE registrations
  ADD COLUMN IF NOT EXISTS participant_count SMALLINT UNSIGNED NOT NULL DEFAULT 1
  AFTER registration_category;
