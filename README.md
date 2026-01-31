<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Docker

The project includes Docker support with PHP 8.4-FPM, Nginx, MySQL 8, and phpMyAdmin.

**Project path:** `~/Documents/GitHub/notification` (or `Documents/GitHub/notification` relative to your home folder).

**Requirements:** Docker and Docker Compose.

**Start all services:**

```bash
cd ~/Documents/GitHub/notification
docker compose up -d --build
```

**URLs:**

- **Laravel app:** http://localhost:8080  
- **phpMyAdmin:** http://localhost:8081 (login: `notification` / `secret`, or root / `rootsecret`)  
- **MySQL:** localhost:3306 (database: `notification`, user: `notification`, password: `secret`)

**First-time setup (run migrations):**

```bash
cd ~/Documents/GitHub/notification
docker compose exec app php artisan migrate
```

**Stop:**

```bash
cd ~/Documents/GitHub/notification
docker compose down
```

## Testing

### 1. Manual testing (run the app)

Start the app with Docker, then open it in your browser:

```bash
cd ~/Documents/GitHub/notification
docker compose up -d --build
docker compose exec app php artisan migrate
```

- **Laravel app:** http://localhost:8080 — you should see the Laravel welcome page.
- **phpMyAdmin:** http://localhost:8081 — check that the `notification` database exists.

### 2. Automated tests (PHPUnit)

Run the test suite inside the app container:

```bash
cd ~/Documents/GitHub/notification
docker compose exec app php artisan test
```

Or run PHPUnit directly:

```bash
docker compose exec app ./vendor/bin/phpunit
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`), so no extra setup is needed.

**Run tests locally** (if you have PHP 8.4+ and Composer installed):

```bash
cd ~/Documents/GitHub/notification
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
