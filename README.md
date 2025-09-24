# URL Shortener

A simple PHP-based URL shortener that works without external database storage.

## Features

- Shorten URLs with 6-character codes
- Cross-browser compatibility using temporary file storage
- 1-hour expiration for shortened URLs
- Session-based management

## How to Run

1. Clone or download the project
2. Navigate to the project directory
3. Start PHP development server:
   ```bash
   php -S localhost:8000
   ```
4. Open http://localhost:8000 in your browser

## Usage

1. Enter a URL in the input field
2. Click "Generate Short URL"
3. Copy and share the shortened URL
4. The shortened URL will redirect to the original URL when accessed

## Requirements

- PHP 7.0 or higher
- Web browser with JavaScript enabled

## Storage

- Uses PHP sessions and temporary files
- No database required
- URLs expire after 1 hour
- Automatic cleanup of expired URLs