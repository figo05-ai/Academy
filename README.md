# 🎓 Academy — Comprehensive Project Documentation

> **Last Updated:** 2026-07-30  
> **Version:** Laravel 13.x  
> **Status:** In Development

---

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Technical Requirements](#technical-requirements)
3. [Project Structure](#project-structure)
4. [Database](#database)
5. [Models](#models)
6. [Routes](#routes)
7. [Controllers](#controllers)
8. [Services Layer](#services-layer)
9. [Middleware](#middleware)
10. [API Resources](#api-resources)
11. [Views](#views)
12. [Security](#security)
13. [Docker & Infrastructure](#docker--infrastructure)
14. [Installation & Setup](#installation--setup)

---

## Project Overview

**Academy** is an integrated Learning Management System (LMS) built with **Laravel**. It allows instructors to manage courses and sessions, and enables students to track their educational progress.

### Key Features

| Feature | Description |
|---------|-------------|
| 🔐 **Authentication System** | Login/Logout (with upcoming Google OAuth support). |
| 🎓 **Course Management** | Create, edit, and delete courses and their respective sessions. |
| 👥 **User Management** | Full control over users and their permissions. |
| 📊 **Progress Tracking** | A sequential tracking system for student session completion. |
| 🗂️ **Project Showcase** | A portfolio page to showcase projects. |
| ⚙️ **Customizable Settings** | External links configurable via the Admin Panel. |
| 🛡️ **Advanced Security** | CSP Headers, Rate Limiting, Admin Guard. |

---

## Technical Requirements

### Backend
| Package | Version | Purpose |
|---------|---------|---------|
| **PHP** | `^8.3` | Core programming language |
| **Laravel Framework** | `^13.8` | Main framework |
| **Laravel Socialite** | `^5.28` | Google OAuth Authentication |
| **Laravel Tinker** | `^3.0` | Development REPL |
| **DarkaOnline L5-Swagger** | `^11.1` | API Documentation |

### Dev Dependencies
| Package | Version | Purpose |
|---------|---------|---------|
| **Larastan** | `^3.10` | Static Analysis (PHPStan for Laravel) |
| **Laravel Pint** | `^1.27` | Code Style Formatter |
| **Laravel Pail** | `^1.2.5` | Real-time Log Viewer |
| **PHPUnit** | `^12.5.12` | Testing Framework |

### Frontend
| Package | Version | Purpose |
|---------|---------|---------|
| **Vite** | `^8.0.0` | Asset Bundler |
| **TailwindCSS** | `^4.0.0` | CSS Framework |
| **Alpine.js** | `^3.15.12` | Lightweight JS Framework |

---

## Project Structure

```
Gorge/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── AdminController.php          # Settings and Projects
│   │   │   │   ├── AdminCourseController.php    # Course CRUD
│   │   │   │   ├── AdminSessionController.php   # Session CRUD
│   │   │   │   └── AdminUserController.php      # User Management
│   │   │   ├── Authentication/
│   │   │   │   └── AuthController.php           # Login/Register/Logout
│   │   │   └── Users/
│   │   │       └── UserController.php           # Student Dashboard
│   │   ├── Middleware/
│   │   │   ├── AdminMiddleware.php              # Admin routes protection
│   │   │   └── SecurityHeadersMiddleware.php    # HTTP Security Headers
│   │   ├── Requests/                            # Form Request Validation
│   │   └── Resources/                           # API JSON Resources
│   ├── Models/
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Session.php
│   │   ├── Project.php
│   │   └── Setting.php
│   └── Services/
│       ├── UserServices/
│       ├── ProjectServices/
│       └── SettingServices/
├── database/
│   └── migrations/                              # Database schema migrations
├── resources/
│   └── views/
│       ├── index.blade.php                     # Landing Page
│       ├── Admin/                              # Admin and Student Dashboards
│       └── Login/                              # Login and Registration pages
├── routes/
│   └── web.php                                 # All application routes
├── docker/                                     # Docker Configuration files
└── docker-compose.yml
```

---

## Database

### Simplified ERD (Entity Relationship Diagram)

- **users**: User data and role identification (Admin vs Student).
- **courses**: Course details (Title, Price, Description, Image).
- **course_sessions**: Course sessions (Order, Links).
- **course_user**: Pivot table for student enrollment in courses (status).
- **session_user**: Pivot table tracking student progress (is_completed).
- **projects**: Projects displayed on the landing page.
- **settings**: Platform settings and external links for resources.

---

## Routes

### 🌐 Public Routes
- `GET /` — Landing page.
- `GET/POST /login`, `/register` — Authentication.
- `POST /logout` — Logout.

### 🔒 Admin Routes (`/admin/*`)
- **Dashboard & Settings**: General management of platform settings and projects.
- **Course Management**: Create, edit, delete, and browse courses.
- **Session Management**: Link sessions to courses and reorder them.
- **User Management**: Enable/disable users, assign courses to students, and delete users.

### 👤 Student Routes (`/user/*`)
- **Dashboard**: View statistics (progress percentage, active courses).
- **Explore Courses**: Browse available new courses.
- **My Courses & Course Details**: Watch sessions and mark available sessions as completed.

---

## Services Layer
The project utilizes the Services pattern to separate Business Logic from Controllers:
- **`UserLifeCycleService`**: Calculates statistics, determines accessible sessions, and sequentially controls student progress.
- **`UserService`**: User querying, registration, and login logic.
- **`ProjectService` & `SettingService`**: Lightweight services for handling settings and projects.

---

## Installation & Setup

### Via Composer & NPM
```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup Environment
cp .env.example .env
php artisan key:generate

# 3. Setup Database (Uses SQLite by default in development)
touch database/database.sqlite
php artisan migrate

# 4. Run the Project
composer run dev
```

### Via Docker
The project includes a `docker-compose.yml` defining the following services:
- `gorge-app` (PHP-FPM)
- `gorge-nginx`
- `gorge-db` (MySQL 8)
- `gorge-redis`
- `gorge-worker` (Queue Worker)

To run via Docker:
```bash
docker-compose up -d
```
