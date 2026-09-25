# External Integrations

**Analysis Date:** 2026-09-25

## APIs & External Services

**Firebase (Google Cloud):**
- Firebase Admin SDK - Authentication, messaging, real-time database access
  - SDK/Client: `kreait/laravel-firebase` (7.2), `google/cloud-*` packages
  - Auth: `FIREBASE_CREDENTIALS` (service account JSON) or `GOOGLE_APPLICATION_CREDENTIALS`
  - Configuration: `config/firebase.php`
  - Location: `app/Services/Firebase/` - `FirebaseService.php`, `NullFirebaseService.php`
  - Features: Firebase Auth, Firestore, Cloud Storage, Real-time Database
  - Notification service: `app/Services/Notification/NotificationService.php` uses Firebase Cloud Messaging (FCM)

**Phone Number Validation:**
- libphonenumber - International phone number validation and formatting
  - SDK/Client: `giggsey/libphonenumber-for-php` (9.0)
  - Location: Used in validation rules and user models

## Data Storage

**Primary Database:**
- SQLite (`database/database.sqlite`)
  - Connection: `DB_CONNECTION=sqlite`
  - Environment: Development only
  - Client: Laravel Eloquent ORM

**Production Database:**
- MySQL 8.0+
  - Connection: `DB_CONNECTION=mysql` (configured, environment vars: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
  - Fallback: MariaDB 10.4+
  - Client: PDO/Laravel Eloquent ORM
  - Alternative support: PostgreSQL, SQL Server (configured in `config/database.php`)

**Cache Storage:**
- Database-backed cache (default)
  - `CACHE_STORE=database`
  - Laravel cache table
- Redis (optional)
  - Configuration: `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`, `REDIS_PASSWORD`, `REDIS_CLIENT=phpredis`
  - Two databases: default (0) and cache (1)
- Memcached (optional)
  - `MEMCACHED_HOST=127.0.0.1`

**File Storage:**
- Local filesystem (development)
  - `FILESYSTEM_DISK=local`
  - Location: `storage/app/` (Laravel default)
- AWS S3 (production)
  - Configuration: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION=us-east-1`, `AWS_BUCKET`
  - Environment var: `AWS_USE_PATH_STYLE_ENDPOINT=false`
  - Client: `aws-sdk-php` (available via guzzle HTTP client)

**Real-time Database:**
- Firebase Realtime Database
  - URL: `FIREBASE_DATABASE_URL`
  - Configuration: `config/firebase.php` database section
  - Purpose: Real-time data synchronization

**Firestore:**
- Google Cloud Firestore
  - Configuration: `config/firebase.php` firestore section
  - Optional: Specify alternate database via `FIREBASE_FIRESTORE_DATABASE`

## Authentication & Identity

**Auth Provider:**
- Custom multi-guard system (Laravel Sanctum)
  - Guards: `users` (token-based), `guests` (token-based), `web` (session-based)
  - Implementation: `config/auth.php`
  - User models: `App\Models\User`, `App\Models\Guest`
  - Password reset: Token-based with 60-minute expiry, 60-second throttle
  - Location: `app/Http/Controllers/Auth/`, `app/Services/Auth/`

**Firebase Authentication:**
- Optional Firebase-based auth as fallback
  - SDK: `kreait/laravel-firebase`
  - Tenant ID: `FIREBASE_AUTH_TENANT_ID` (optional, for multi-tenant setups)
  - Service: `app/Services/Firebase/FirebaseService.php`

**Role-Based Access Control:**
- Spatie Laravel Permissions
  - Package: `spatie/laravel-permission` (8.3)
  - Configuration: `config/permission.php`
  - Location: `app/Policies/` for authorization policies

## Monitoring & Observability

**Error Tracking:**
- Not detected - likely relies on standard logging

**Logs:**
- Laravel logging stack
  - Driver: `LOG_CHANNEL=stack` (default: single file)
  - Stack includes: single, daily, errorlog channels
  - Level: `LOG_LEVEL=debug` (development)
  - Location: `storage/logs/`

**Activity Logging:**
- Spatie Laravel Activity Log
  - Package: `spatie/laravel-activitylog` (5.0)
  - Configuration: `config/activitylog.php`
  - Purpose: Audit trail for model changes
  - Location: `app/Traits/MirrorsToFirestore.php` - mirrors Laravel activity to Firestore

**Firebase HTTP Logging:**
- Optional Firebase HTTP interaction logging
  - Config: `FIREBASE_HTTP_LOG_CHANNEL`, `FIREBASE_HTTP_DEBUG_LOG_CHANNEL`

## CI/CD & Deployment

**Hosting:**
- Not specified - Supports any PHP hosting with MySQL
- AWS compatibility: S3 storage, credentials system in place
- GCP compatibility: Firebase integration, Google Cloud SDKs present

**CI Pipeline:**
- Not detected in backend
- Dashboard has Python scripts: `scripts/smoke_carlton.py`, `scripts/capture_carlton_screens.py`

**Deployment Readiness:**
- Database: Migrations ready (`config/database.php` supports MySQL, PostgreSQL, SQL Server)
- Assets: Vite production build configured
- Environment: `.env.example` template provided for all services

## Environment Configuration

**Required env vars:**
- `APP_NAME`, `APP_KEY`, `APP_URL`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_TIMEZONE`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `FIREBASE_CREDENTIALS` or `GOOGLE_APPLICATION_CREDENTIALS` (if using Firebase)
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` (if using S3)
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` (if using Redis)

**Secrets location:**
- `.env` file (git-ignored, never committed)
- Service account credentials: Can be loaded from filesystem or env var (`FIREBASE_CREDENTIALS`, `GOOGLE_APPLICATION_CREDENTIALS`)
- AWS credentials: Environment variables

**Firebase Credentials:**
- Auto-discovery priority (per `config/firebase.php`):
  1. `FIREBASE_CREDENTIALS` env var
  2. `GOOGLE_APPLICATION_CREDENTIALS` env var
  3. Google's well-known credential file
  4. GCP metadata server (if running on GCP)

## Webhooks & Callbacks

**Incoming:**
- Not explicitly detected
- Likely used for: Firebase Cloud Messaging (FCM) registration, payment confirmations

**Outgoing:**
- Firebase Cloud Messaging (FCM) - Push notifications to mobile clients
  - Implementation: `app/Services/Firebase/FirebaseService.php`
  - Notification service: `app/Services/Notification/NotificationService.php`
  - Handled by: `app/Listeners/` event listeners

## Queue & Background Jobs

**Queue System:**
- Database-backed queue (default)
  - `QUEUE_CONNECTION=database`
  - Jobs table: `jobs`, `failed_jobs`
  - Listener command: `php artisan queue:listen` (dev script: `php artisan queue:listen --tries=1`)

**Job Locations:**
- `app/Jobs/` - Job classes

**Broadcasting:**
- Log driver (development only)
  - `BROADCAST_CONNECTION=log`
  - No real-time broadcast setup detected

## Session & State Management

**Session:**
- Database-backed sessions (default)
  - `SESSION_DRIVER=database`
  - Table: `sessions`
  - Lifetime: `SESSION_LIFETIME=120` minutes
  - Encryption: `SESSION_ENCRYPT=false`
  - Domain: `SESSION_DOMAIN=null` (defaults to auto-detect)
  - Path: `SESSION_PATH=/`

## Mail Configuration

**Mail Transport:**
- Log driver (development)
  - `MAIL_MAILER=log` - Logs emails to file instead of sending
- Production: Configured for SMTP
  - Host: `MAIL_HOST=127.0.0.1` (changeable)
  - Port: `MAIL_PORT=2525`
  - From: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

## Multilingual Support

**Translation System:**
- Laravel localization framework
- Locales: English (en), Arabic (ar), with support for French (fr), Turkish (tr), Spanish (es)
- Implementation: `spatie/laravel-translatable` (6.14) for database translations
- Configuration: `config/cms.php`, `lang/` directory
- Location: `app/Services/Cms/` - CMS content management with translations

## PDF Generation

**PDF Engine:**
- mPDF 8.3
  - Purpose: Invoice generation, document creation
  - Library: `mpdf/mpdf`

---

*Integration audit: 2026-09-25*
