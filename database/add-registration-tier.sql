ALTER TABLE registrations
  ADD COLUMN IF NOT EXISTS registration_tier VARCHAR(12) DEFAULT NULL
  AFTER registration_category;
