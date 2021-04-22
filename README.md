# Post analytics

The service for collecting analytics of posts on the knife.media from various sources.
Data is used to build different custom reports.

## Installation
1. Create `.env` config file from `.env.example`.
2. Add required credentials to `.env` file.
3. Create database using `database.sql` dump file.
4. Set cron job for `fetch.php` script.
