-- إنشاء جدول المشرفين الخاص بلوحة التحكم (منفصل عن جداول FreeRADIUS)
-- نفّذ هذا الملف مرة واحدة فقط على نفس قاعدة بيانات radius
-- mysql -u root -p radius < sql/admins.sql

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مشرف افتراضي: اسم المستخدم admin / كلمة السر admin123
-- ⚠️ غيّر كلمة السر فور أول تسجيل دخول (شرح الطريقة في README.md)
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2b$10$GRwjJwxdOD7EQQL4m6x/SumkINwzgdqgL565uAK4AvOIiH847ms1C')
ON DUPLICATE KEY UPDATE username = username;
