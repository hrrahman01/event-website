<?php
/**
 * Application configuration.
 * Edit the database values below for your environment.
 * This file must never be reachable from the browser (the root .htaccess blocks /includes).
 */
declare(strict_types=1);

defined('APP_BOOT') || exit('Direct access denied.');

// ---------- Environment: 'development' shows errors, 'production' hides them ----------
const APP_ENV      = 'development';
const APP_TIMEZONE = 'Asia/Kolkata';

// ---------- Database (XAMPP defaults: user root, empty password) ----------
// For production, create a dedicated MySQL user with a strong password.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'event_management';
const DB_USER = 'root';
const DB_PASS = '';

// ---------- Paths (private folders, outside the web-served assets) ----------
const ROOT_DIR     = __DIR__ . '/..';
const ABSTRACT_DIR = ROOT_DIR . '/storage/abstracts';
const LOG_DIR      = ROOT_DIR . '/storage/logs';

// ---------- Uploads ----------
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;   // 5 MB

// ---------- Sessions ----------
const SESSION_NAME    = 'evtsid';
const SESSION_TIMEOUT = 1800;               // 30 minutes of inactivity

// ---------- Rate limiting ----------
const LOGIN_MAX_ATTEMPTS = 5;               // failed logins allowed...
const LOGIN_WINDOW       = 900;             // ...within 15 minutes
const FORM_MAX_PER_HOUR  = 10;              // public form submissions per IP per hour
