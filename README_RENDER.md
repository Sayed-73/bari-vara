# Render-এ (Docker) Laravel চালানো: composer সমস্যা ও timeout সমাধান

## ১. আগে সৎ কথা: Render Free প্ল্যানের সীমা (Render-এর নিজস্ব ডকুমেন্টেশন অনুযায়ী)
- **১৫ মিনিট কেউ না ঢুকলে সার্ভিস ঘুমিয়ে যায়।** পরের প্রথম রিকোয়েস্টে জাগতে প্রায় ৩০-৬০ সেকেন্ড লাগে। এটাই আপনার বেশিরভাগ "timeout"।
- Free সার্ভিসে ৫১২ MB RAM, মাত্র ০.১ CPU। Filament ভারী, তাই ধীর লাগবেই।
- প্রতি মাসে ৭৫০ ঘণ্টা (একটা সার্ভিস সারা মাস জাগিয়ে রাখলে ৭৪৪ ঘণ্টা, ঠিক ঢুকে যায়)।
- সার্ভিস রিস্টার্ট/ঘুমালে **ফাইল সিস্টেমের সব মুছে যায়** (SQLite, লগ, ফাইল-সেশন কিছুই থাকে না)।
- **Free Postgres ডাটাবেস ৩০ দিন পরে মেয়াদ শেষ হয়ে যায়** (১৪ দিনের গ্রেস পিরিয়ড, তারপর মুছে যায়)। Render যেকোনো সময় রিস্টার্টও করতে পারে।
- Free সার্ভিসে Cron Job চালানো যায় না, তাই মাসিক বিল ও SMS রিমাইন্ডার নিজে চলবে না (নিচে সমাধান আছে)।

**ফলাফল:** Render Free শুধু **টেস্ট ও ডেমোর** জন্য ঠিক। **আসল বাড়িওয়ালাদের টাকার হিসাব এখানে রাখবেন না**, ৩০ দিন পর ডাটাবেস মুছে যেতে পারে। আসল ইউজারের আগে সরে যান:
Render Starter (প্রায় $৭/মাস) + টাকা দিয়ে ডাটাবেস, অথবা `DEPLOY.md`-র মতো একটা সস্তা VPS। 

## ২. কী কী ফাইল দেওয়া হলো (প্রজেক্টের রুটে বসান, Replace করুন)
`Dockerfile`, `.dockerignore`, `.gitattributes`, `docker/` ফোল্ডার, `app/Http/Controllers/CronController.php`, `config/cron.php`, `routes/step4.php`, `tests/Feature/CronTest.php`।
আপনার আগের নিজের `Dockerfile` থাকলে এটা দিয়ে বদলে দিন (পুরনোটা আগে কপি করে রাখুন)।

## ৩. "একটু কোড বদলালে composer error, আবার ঠিক করলে চলে": আসল কারণ

| লক্ষণ / error | কারণ | সমাধান |
|---|---|---|
| `Your lock file does not contain a compatible set of packages` / `requires php ^8.x but your php version is 8.y` | লোকালে PHP ভার্সন এক, Docker-এ আরেক | লোকালে `php -v` দেখুন। `Dockerfile`-এর `ARG PHP_VERSION=8.3` লাইনটা সেই ভার্সনের সাথে মেলান (বা `composer.json`-এর `"php"` শর্তের সাথে) |
| `requires ext-intl` / `ext-zip` ইত্যাদি | Docker image-এ extension নেই | এই `Dockerfile`-এ `intl zip bcmath pdo_mysql pdo_pgsql opcache` আছে। আরও লাগলে `install-php-extensions` লাইনে নাম যোগ করুন |
| `composer.lock is not up to date` বা প্যাকেজ বদলে গেছে | `composer.json` বদলে `composer.lock` আপডেট না করে push করেছেন | লোকালে `composer update প্যাকেজের-নাম` (বা `composer require ...`) চালান এবং **`composer.json` ও `composer.lock` দুটোই commit** করুন। কখনো `composer.lock` `.gitignore`-এ রাখবেন না |
| `Class "Laravel\Pail\..." / "...Collision..." not found` (শুধু ডেপ্লয়ে) | Windows-এ তৈরি `bootstrap/cache/*.php` ও `vendor/` ইমেজে ঢুকেছে (dev প্যাকেজসহ) | `.dockerignore`-এ এগুলো বাদ দেওয়া আছে। নিশ্চিত করুন নতুন `.dockerignore` বসেছে |
| build-এর সময় `artisan` error (`No application encryption key`, DB connection) | `composer install` নিজে `artisan` চালায় | `--no-scripts` দেওয়া আছে, `artisan` চলে স্টার্টের সময় |
| `exec /usr/local/bin/start.sh: no such file or directory` | Windows-এর CRLF লাইন-শেষ | `.gitattributes` ও `Dockerfile`-এর `sed` এটা ঠিক করে দেয়। তবু হলে VS Code-এ নিচে "CRLF" ক্লিক করে "LF" করুন |
| প্রতিবার সব প্যাকেজ আবার নামছে (ধীর build) | `COPY . .` আগে হয়ে লেয়ার ক্যাশ ভাঙছে | এই `Dockerfile`-এ `composer.json`+`composer.lock` আগে কপি হয়, তাই কোড বদলালে composer আবার চলে না |

**push করার আগে লোকালে ৩০ সেকেন্ডের পরীক্ষা (এটাই আপনার ঝামেলা বাঁচাবে):**
```bash
composer validate
composer install --no-dev --dry-run
```
দুটো পাস করলে ডেপ্লয়ে composer ফেল করবে না।

## ৪. "timeout" তিন রকম: কোনটা কীসের

| কোথায় | কারণ | সমাধান |
|---|---|---|
| **ডেপ্লয়ের সময়** `No open ports detected` / `Port scan timeout` | Apache ৮০ পোর্টে ছিল, Render চায় `$PORT` (১০০০০) | `docker/start.sh` Apache-কে `$PORT`-এ চালায় |
| **ডেপ্লয়ের সময়** স্টার্ট ফেল | মাইগ্রেশনের সময় ডাটাবেস রেডি না | স্ক্রিপ্ট ১৫ বার (প্রায় ১ মিনিট) চেষ্টা করে, তারপর স্পষ্ট error দিয়ে থামে |
| **চালু থাকার সময়** প্রথম খোলায় ৩০-৬০ সেকেন্ড, অ্যাপে "timeout" | ১৫ মিনিট ঘুমিয়ে ছিল (Free) | নিচের ধাপ ৬: বাইরের pinger দিয়ে প্রতি ৫ মিনিটে ডাকা, সার্ভিস জেগে থাকে |
| **চালু থাকার সময়** হঠাৎ 502/কিলড | RAM শেষ (৫১২ MB) | `docker/apache-tuning.conf` Apache-র ওয়ার্কার ৬টায় সীমিত করে; `php.ini`-তে `memory_limit=256M` |
| সবসময় ধীর | ০.১ CPU | Free-র সীমা, Starter-এ গেলে বদলায় |

## ৫. Render-এ সেটিংস
**New → Web Service → আপনার GitHub repo → Language: Docker।** Health Check Path: `/up` (Laravel-এ আগে থেকেই আছে)।

**Environment Variables (সব ঠিকঠাক বসান):**
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=           # লোকালে `php artisan key:generate --show` চালিয়ে পাওয়া মান (base64:... সহ)
APP_URL=https://আপনার-সার্ভিস.onrender.com
APP_TIMEZONE=Asia/Dhaka
LOG_CHANNEL=stderr           # লগ Render-এর Logs ট্যাবে দেখা যায়
SESSION_DRIVER=database      # ফাইল-সেশন রিস্টার্টে মুছে যায়, সবাই লগআউট হয়
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
CRON_TOKEN=        # ২৪+ অক্ষরের লম্বা এলোমেলো গোপন লেখা
DB_CONNECTION=pgsql
DB_URL=            # Render Postgres-এর "Internal Database URL"
```
আর `MAIL_*`, `SMS_*`, `SUPPORT_*`, `COMPANY_*`, SSLCommerz আপনার আগের মতো। ডাটাবেস MySQL ব্যবহার করলে `DB_CONNECTION=mysql` আর `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`।
(আপনার কোড মানক Laravel, Postgres-এও চলার কথা, কিন্তু আসল ডাটার আগে `php artisan migrate` ও পুরো ফ্লো Postgres-এ একবার নিজে পরীক্ষা করুন।)

**HTTPS-এর পেছনে লিংক ঠিক রাখতে দুটো ছোট এডিট:**
1. `bootstrap/app.php`-এর `->withMiddleware(...)` ভেতরে:
```php
$middleware->trustProxies(at: '*');
```
2. `app/Providers/AppServiceProvider.php`-এর `boot()`-এ:
```php
if (app()->environment('production')) {
    \Illuminate\Support\Facades\URL::forceScheme('https');
}
```
না করলে লিংক `http://` হয়ে যায়: লগইন ঘুরে যাওয়া, CSS না আসা, SSLCommerz ফেরত-লিংক ভাঙা, ভাড়াটিয়ার বিল-লিংক ভাঙা।

## ৬. মাসিক বিল, SMS রিমাইন্ডার আর "জাগিয়ে রাখা": cron-job.org (ফ্রি)
Render Free-তে cron নেই, তাই বাইরের একটা সেবা প্রতি ৫ মিনিটে একটা গোপন লিংকে ডাক দেবে। এতে **সার্ভিস জেগেও থাকে**, **ভাড়ার বিল তৈরি হয়**, **SMS রিমাইন্ডার যায়** (সকাল ৯টা থেকে রাত ৮টা, প্রতিটা একবারই)।
1. cron-job.org-এ ফ্রি অ্যাকাউন্ট।
2. নতুন Cronjob: URL `https://আপনার-সার্ভিস.onrender.com/cron/আপনার-CRON_TOKEN`, ইন্টারভাল **প্রতি ৫ মিনিট**।
3. লিংক ভুল হলে 404 আসে (ইচ্ছাকৃত, গোপন রাখার জন্য)। ঠিক হলে `{"ok":true,...}` আসে।
এই লিংক কাউকে দেবেন না। কারও হাতে গেলে `CRON_TOKEN` বদলে ফেলুন।
(পিংয়ের ঝামেলা: ৭৫০ ঘণ্টায় ঠিক একটা সার্ভিস চলে। দ্বিতীয় Free সার্ভিস চালালে ঘণ্টা ফুরিয়ে সব সাসপেন্ড হবে।)

## ৭. error দেখলে কী করবেন (Logs ট্যাব)
| error | সমাধান |
|---|---|
| `No application encryption key has been specified` | `APP_KEY` env বসান |
| Filament পেজ বেস্টাইলহীন/ভাঙা | লগ দেখুন `filament:assets` চলেছে কিনা; `APP_URL` https কিনা; ধাপ ৫-এর দুটো এডিট |
| `419 Page Expired` | `SESSION_DRIVER=database`, `APP_URL` ঠিক, `SESSION_SECURE_COOKIE=true`, ধাপ ৫-এর trustProxies |
| `SQLSTATE ... connection refused / could not translate host` | `DB_URL`/`DB_HOST` ভুল; ডাটাবেস ও সার্ভিস একই Region-এ আছে কিনা দেখুন |
| `Permission denied` লগ লেখায় | `start.sh` শেষে `chown` করে। `LOG_CHANNEL=stderr` দিলে ফাইলই লাগে না |
| `Allowed memory size exhausted` | বড় ডাটা একসাথে টানা বন্ধ করুন, বা Starter প্ল্যান |
| ডেপ্লয় ঠিক কিন্তু ৫০০ | Logs ট্যাবে প্রথম লাল লাইন দেখুন; লাগলে অস্থায়ীভাবে `APP_DEBUG=true` (কাজ শেষে আবার false) |

## ৮. আসল ইউজারের আগে সরে যাওয়ার চেকলিস্ট
- [ ] Render Free থেকে Starter প্ল্যানে (বা VPS-এ) যান, ডাটাবেস টাকার/স্থায়ী
- [ ] ডাটাবেসের দৈনিক ব্যাকআপ চালু (প্রোভাইডারে বা `DEPLOY.md`-র `mysqldump`)
- [ ] `APP_KEY` ও `.env` সুরক্ষিত জায়গায় আলাদা কপি
- [ ] cron-job.org থাকলেও চলে, তবে Starter/VPS-এ আসল cron ভালো
