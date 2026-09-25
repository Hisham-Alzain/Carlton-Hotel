# Technology Stack

**Analysis Date:** 2026-09-25

## Languages

**Primary:**
- PHP 8.3+ - Backend API and business logic
- JavaScript (ES2025 modules) - Frontend asset bundling and build tooling
- Dart - Flutter mobile application (cross-platform)

**Secondary:**
- HTML/CSS - Web templates and styling
- SQL - Database queries

## Runtime

**Environment:**
- PHP 8.3+ - Laravel backend runtime
- Node.js - Build tooling and asset processing
- Dart SDK - Flutter mobile development and compilation

**Package Manager:**
- Composer 2.x - PHP dependencies
- npm 9.x+ - JavaScript dependencies
- Pub - Dart/Flutter dependencies (pubspec.yaml)
- Lockfiles: `composer.lock`, `package-lock.json`, `pubspec.lock` (all present)

## Frameworks

**Core:**
- Laravel 13.8 - REST API framework, routing, ORM, migrations, authentication
- Laravel Sanctum 4.0 - API token authentication and session management
- Flutter 3.x - Cross-platform mobile application (Dart)
- Vite 8.0.0 - Frontend asset bundler for web

**Testing:**
- PHPUnit 12.5.12 - PHP unit and integration testing
- Mockery 1.6 - PHP mocking framework
- Flutter Test - Dart unit and widget testing

**Build/Dev:**
- Vite 8.0.0 - Development server, asset bundling
- Tailwind CSS 4.0.0 - Utility-first CSS framework
- @tailwindcss/vite 4.0.0 - Vite plugin for Tailwind
- Laravel Vite Plugin 3.1 - Integration between Laravel and Vite
- Concurrently 9.0.1 - Run multiple npm scripts in parallel
- Laravel Pint 1.27 - PHP code style fixer
- Laravel Pail 1.2.5 - Tail Laravel logs
- Laravel PAO 1.0.6 - Assisted output for Artisan commands

## Key Dependencies

**Critical:**
- laravel/framework 13.8 - Complete framework
- laravel/sanctum 4.0 - API authentication via tokens/cookies
- kreait/laravel-firebase 7.2 - Firebase integration for auth, messaging, storage
- spatie/laravel-permission 8.3 - Role-based access control
- spatie/laravel-activitylog 5.0 - Audit trail and activity tracking
- spatie/laravel-translatable 6.14 - Multilingual model support
- mpdf/mpdf 8.3 - PDF generation for invoices/documents

**Infrastructure:**
- giggsey/libphonenumber-for-php 9.0 - International phone number validation
- laravel/tinker 3.0 - Interactive shell for Laravel
- fakerphp/faker 1.23 - Seeding test data
- firebase/php-jwt - JWT token handling
- guzzlehttp/guzzle - HTTP client for external API calls

## Configuration

**Environment:**
- `.env` file (git-ignored, customized per environment)
- `.env.example` - Template for environment variables
- Configuration files in `config/` directory (app.php, database.php, auth.php, firebase.php, etc.)

**Build:**
- `vite.config.js` - Vite build configuration (resources/assets compilation)
- `tailwind.config.js` - Tailwind CSS customization
- `.editorconfig` - Code style consistency
- `.prettierrc` or similar - Code formatting (not detected)
- `eslint.config.js` - JavaScript linting for dashboard/frontend

**PHP Configuration:**
- `composer.json` - PHP dependency manifest
- `.phpunit.result.cache` - Test result cache
- `artisan` - Laravel CLI entry point

**Node Configuration:**
- `package.json` - JavaScript dependencies and build scripts
- `.npmrc` - npm registry configuration

## Platform Requirements

**Development:**
- PHP 8.3 or higher
- Composer (dependency manager)
- Node.js 18+ (for Vite)
- npm 9+ or yarn
- Dart SDK 3.x (for mobile development)
- SQLite (built-in, for local database)
- Redis (optional, for advanced caching)
- Memcached (optional, for distributed caching)

**Production:**
- PHP 8.3+ with extensions: pdo_mysql, curl, json, mbstring, xml
- MySQL 8.0+ or MariaDB 10.4+
- Redis (recommended for caching, queue processing)
- Web server: nginx or Apache with PHP-FPM
- Node.js (only for asset compilation in CI/CD)
- Google Cloud SDK credentials (for Firebase)
- AWS credentials (for S3 file storage, if used)

**Mobile (Flutter):**
- Dart SDK 3.x
- Flutter SDK (includes Dart)
- iOS: Xcode 14+, macOS 12+
- Android: Android Studio, SDK API 21+ (minSdkVersion)
- Web: Compatible with all modern browsers

---

*Stack analysis: 2026-09-25*
