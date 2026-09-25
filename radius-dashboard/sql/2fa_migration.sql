-- ترقية جدول admins الموجود مسبقا لإضافة دعم التحقق بخطوتين (2FA)
-- نفّذ هذا الملف مرة واحدة فقط إذا كان جدول admins قد أُنشئ قبل إضافة هذه الميزة:
-- mysql -u root -p radius < sql/2fa_migration.sql

ALTER TABLE admins
  ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;
