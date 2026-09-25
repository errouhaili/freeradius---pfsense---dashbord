# لوحة إدارة FreeRADIUS + pfSense

لوحة تحكم ويب (PHP + MySQL/PDO) لإدارة مستخدمي FreeRADIUS، متابعة الجلسات النشطة،
تقارير الاستهلاك، وإدارة أجهزة NAS (مثل pfSense) والمجموعات/السياسات.

## المتطلبات

- خادم ويب (Apache/Nginx) + PHP 8.0 فأعلى (مع امتداد `pdo_mysql`)
- قاعدة بيانات MySQL/MariaDB تحتوي بالفعل على جداول FreeRADIUS القياسية:
  `radcheck`, `radreply`, `radusergroup`, `radgroupcheck`, `radgroupreply`, `radacct`, `nas`
  (هذه الجداول تُنشأ تلقائياً عند تثبيت FreeRADIUS مع دعم SQL، أو تجدها في
  `/etc/freeradius/mods-config/sql/main/mysql/schema.sql`)
- اتصال هذا السيرفر بنفس قاعدة بيانات RADIUS المستخدمة من طرف pfSense
  (سواء كانت محلية على pfSense أو خادم MySQL خارجي)

## خطوات التركيب

1. انسخ محتوى مجلد `radius-dashboard` إلى مجلد الخادم (مثلاً `/var/www/html/radius-dashboard`)

2. عدّل ملف `config.php` وضع فيه بيانات الاتصال بقاعدة بيانات radius:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'radius');
   define('DB_USER', 'radius');
   define('DB_PASS', 'كلمة_السر_الحقيقية');
   ```

3. نفّذ سكريبت إنشاء جدول المشرفين (مرة واحدة فقط):
   ```bash
   mysql -u root -p radius < sql/admins.sql
   ```
   هذا سينشئ حساب دخول افتراضي:
   - **اسم المستخدم:** `admin`
   - **كلمة السر:** `admin123`

   ⚠️ **غيّر كلمة السر فوراً بعد أول دخول** بتنفيذ هذا الأمر لتوليد تشفير جديد:
   ```bash
   php -r "echo password_hash('كلمة_سر_جديدة', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   ثم حدّث القيمة في جدول `admins`:
   ```sql
   UPDATE admins SET password_hash = 'الهاش_الجديد' WHERE username = 'admin';
   ```

4. تأكد أن مستخدم قاعدة البيانات المستعمل في `config.php` عنده صلاحيات
   `SELECT, INSERT, UPDATE, DELETE` على جداول radius.

5. افتح الرابط في المتصفح: `http://عنوان-السيرفر/radius-dashboard/`

## ربط pfSense

في pfSense تحت **Services → FreeRADIUS** (أو حسب الحزمة المستعملة)، تأكد أن FreeRADIUS
يستعمل نفس قاعدة بيانات MySQL (`radius`) كـ SQL backend. أي مستخدم أو مجموعة أو جهاز NAS
تضيفه من هذه اللوحة سيظهر مباشرة في FreeRADIUS بدون الحاجة لإعادة تشغيل الخدمة
(FreeRADIUS يقرأ من قاعدة البيانات مباشرة عند كل طلب مصادقة).

من صفحة **أجهزة NAS**، أضف عنوان IP الخاص بجدار pfSense مع نفس "السر المشترك" (Shared Secret)
المُعرّف في إعدادات RADIUS على pfSense نفسه.

## هيكلة الملفات

```
radius-dashboard/
├── config.php              # إعدادات الاتصال بقاعدة البيانات
├── includes/
│   ├── db.php               # اتصال PDO
│   ├── auth.php             # تسجيل الدخول والحماية (CSRF)
│   ├── functions.php        # دوال مساعدة (CRUD للمستخدمين، تنسيق البيانات...)
│   ├── header.php / sidebar.php / footer.php
├── dashboard.php            # الصفحة الرئيسية بالإحصائيات
├── users.php / user_form.php / user_save.php / user_delete.php   # إدارة المستخدمين
├── sessions.php             # الجلسات النشطة (من جدول radacct)
├── reports.php              # تقارير الاستهلاك مع رسم بياني (Chart.js)
├── nas.php / nas_form.php / nas_save.php / nas_delete.php        # إدارة أجهزة NAS
├── groups.php / group_form.php / group_save.php / group_delete.php  # المجموعات والسياسات
├── assets/css/style.css
└── sql/admins.sql           # إنشاء جدول المشرفين
```

## ملاحظات أمنية مهمة

- كلمات سر مستخدمي الراديوس تُخزَّن حالياً بصيغة `Cleartext-Password` (وهي الطريقة الأبسط
  المتوافقة مع بروتوكول PAP). إن كنت تستعمل CHAP/MSCHAP يُفضَّل التحويل إلى `NT-Password`.
- فعّل HTTPS على خادم الويب قبل استعمال اللوحة في بيئة إنتاج حقيقية.
- غيّر كلمة سر `admin` الافتراضية فوراً.
- تأكد أن مجلد المشروع غير قابل للوصول العمومي لملف `config.php` مباشرة (محمي أصلاً لأنه ملف PHP).
