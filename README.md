# Drug Tracker API

A Laravel-based API service for drug information search and user-specific medication tracking.  
It integrates with the **National Library of Medicine's RxNorm APIs** for drug data.

---

## Features

- **User Authentication** (Register, Login, Logout)
- **Public Drug Search** (using RxNorm APIs)
- **Private Medication Management** (Add, Delete, List user drugs)
- **Rate Limiting** for public search endpoint
- **Caching** of API responses for performance
- **RxNorm Integration** for ingredient and dose form details

---

## Requirements

- PHP >= 8.0
- Composer
- MySQL
- Laravel 10.x
- Internet connection (for RxNorm API calls)

---

## Installation

```bash
# Clone repository 
git clone https://github.com/yourusername/drug-tracker.git
cd drug-tracker

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Install Laravel Sanctum
composer require laravel/sanctum

# Publish Sanctum configuration and migration files
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Update database credentials in .env
DB_DATABASE=drug_tracker
DB_USERNAME=root
DB_PASSWORD=

# Add RxNorm API configuration in .env
RXNAV_BASE_URL=https://rxnav.nlm.nih.gov/REST
RXNAV_TIMEOUT_MS=8000
RXNAV_CACHE_SECONDS=3600

# Run migrations
php artisan migrate

# (Optional) Seed database
php artisan db:seed

# Run tests
php artisan test

