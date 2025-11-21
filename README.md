# 🔐 Laravel API Authentication

A robust, production-ready RESTful API authentication system built with Laravel 12 and Laravel Sanctum. Features comprehensive rate limiting, secure password handling, and extensive test coverage.

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-3.8-25C9D0?style=for-the-badge&logo=pest&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

---

## ✨ Features

### 🔑 Authentication & Security
- **User Registration** - Secure user registration with email validation and uniqueness checks
- **User Login** - Email and password-based authentication
- **Token-Based Auth** - Laravel Sanctum for stateless API authentication
- **Password Security** - Automatic password hashing using bcrypt
- **Email Normalization** - Automatic lowercase conversion and whitespace trimming

### 🛡️ Rate Limiting & Protection
- **Registration Rate Limiting** - IP-based rate limiting (5 attempts per hour per IP)
- **Login Rate Limiting** - Email-based rate limiting (5 attempts per minute per email)
- **Automatic Rate Limit Clearing** - Rate limits cleared on successful login
- **429 Status Codes** - Proper HTTP status codes for rate limit violations

### 📝 Request Validation
- **Comprehensive Validation** - Form request validation for all endpoints
- **Password Requirements** - Minimum 8 characters with confirmation
- **Email Validation** - RFC-compliant email format validation
- **Unique Email Enforcement** - Database-level uniqueness checks

### 🧪 Testing
- **Comprehensive Test Suite** - Full test coverage using Pest PHP
- **Feature Tests** - Complete authentication flow testing
- **Rate Limiting Tests** - Verification of rate limiting behavior
- **Validation Tests** - All validation rules tested
- **Edge Case Coverage** - Email normalization, error handling, and exception scenarios

### 📊 API Resources
- **User Resource** - Clean JSON responses with only necessary user data
- **Consistent Response Format** - Standardized JSON response structure
- **Error Handling** - Graceful error handling with proper logging

---

## 🚀 Tech Stack

- **Framework**: Laravel 12.x
- **Authentication**: Laravel Sanctum 4.x
- **Testing**: Pest PHP 3.x
- **PHP**: 8.2+
- **Database**: MySQL/PostgreSQL/SQLite (configurable)

---

## 📦 Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and NPM (for frontend assets)
- MySQL/PostgreSQL or SQLite

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/api-authentication.git
cd api-authentication
```

### Step 2: Install Dependencies

```bash
composer install
npm install
```

### Step 3: Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Build Frontend Assets (Optional)

```bash
npm run build
```

### Step 6: Start the Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

---

## 📚 API Endpoints

### Base URL
```
http://localhost:8000/api
```

### 🔹 Register User

Register a new user account.

**Endpoint:** `POST /api/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Success Response (201):**
```json
{
  "status": true,
  "message": "Registration completed successfully.",
  "data": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

**Error Response (422):**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

**Rate Limit Response (429):**
```json
{
  "status": false,
  "message": "Too many registration attempts from this IP. Please try again later"
}
```

---

### 🔹 Login

Authenticate an existing user.

**Endpoint:** `POST /api/login`

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Login successful.",
  "data": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

**Error Response (401):**
```json
{
  "status": false,
  "message": "Invalid credentials."
}
```

**Rate Limit Response (429):**
```json
{
  "status": false,
  "message": "Too many login attempts. Please try again later."
}
```

---

### 🔹 Get Authenticated User

Get the currently authenticated user's information.

**Endpoint:** `GET /api/user`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified_at": null,
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Unauthorized Response (401):**
```json
{
  "message": "Unauthenticated."
}
```

---

## 🧪 Running Tests

This project includes comprehensive test coverage using Pest PHP.

### Run All Tests

```bash
php artisan test
```

or

```bash
./vendor/bin/pest
```

### Run Specific Test Suites

```bash
# Run only feature tests
php artisan test --testsuite=Feature

# Run only unit tests
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/Auth/RegistrationTest.php
php artisan test tests/Feature/Auth/LoginTest.php
```

### Test Coverage

The test suite includes:

- ✅ User registration with valid credentials
- ✅ Email normalization (lowercase, trim)
- ✅ Password validation and hashing
- ✅ Rate limiting for registration (IP-based)
- ✅ Rate limiting for login (email-based)
- ✅ Validation error handling
- ✅ Token generation and authentication
- ✅ Error handling and logging
- ✅ Edge cases and exception scenarios

---

## 🔒 Security Features

- **Password Hashing**: All passwords are automatically hashed using bcrypt
- **Rate Limiting**: Protection against brute force attacks
- **Email Normalization**: Prevents duplicate accounts with different email formats
- **Token-Based Auth**: Secure, stateless authentication
- **Input Validation**: Comprehensive request validation
- **Error Logging**: Detailed error logging for debugging and monitoring

---

## 📋 Rate Limiting Details

### Registration Rate Limiting
- **Limit**: 5 attempts per IP address
- **Window**: 1 hour (3600 seconds)
- **Scope**: IP-based

### Login Rate Limiting
- **Limit**: 5 attempts per email address
- **Window**: 1 minute (60 seconds)
- **Scope**: Email-based
- **Auto-Clear**: Rate limit is cleared on successful login

---

## 🛠️ Development

### Code Style

This project uses Laravel Pint for code formatting:

```bash
./vendor/bin/pint
```

### Database Migrations

```bash
# Create a new migration
php artisan make:migration create_example_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback
```

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 👤 Author

**Your Name**

- GitHub: [@yourusername](https://github.com/yourusername)
- Email: your.email@example.com

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP Framework
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - API Authentication
- [Pest PHP](https://pestphp.com) - Testing Framework

---

<div align="center">

**⭐ If you find this project helpful, please give it a star! ⭐**

Made with ❤️ using Laravel

</div>

