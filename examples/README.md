# Campfire Database Query Examples

This directory contains example scripts demonstrating how to query the Campfire SQLite database from external applications.

## Available Examples

### query_unread_messages.php

A PHP script that demonstrates how to query unread messages from the Campfire database using PDO.

**Prerequisites:**
- PHP 7.4 or higher
- PDO SQLite extension enabled
- Read access to the Campfire SQLite database file

**Usage:**

1. Set the database path environment variable:
   ```bash
   export CAMPFIRE_DATABASE_PATH=/path/to/campfire/db/production.sqlite3
   ```

2. Run the script to see all users with unread messages:
   ```bash
   php examples/query_unread_messages.php
   ```

3. Run the script with a user ID to see detailed unread messages for that user:
   ```bash
   php examples/query_unread_messages.php 1
   ```

**What it demonstrates:**
- Connecting to the SQLite database using PDO
- Querying all users with unread messages
- Checking if a specific user has unread messages
- Getting unread message counts by room
- Retrieving detailed unread message information

## Integration with Laravel

For Laravel-specific integration examples and detailed documentation, see:
- [docs/LARAVEL_UNREAD_MESSAGES_QUERY.md](../docs/LARAVEL_UNREAD_MESSAGES_QUERY.md)

This documentation includes:
- Database structure overview
- SQL query examples
- Laravel Eloquent model examples
- Complete integration guide

## Database Access Notes

1. **Read-Only Access**: These examples are designed for read-only access to the Campfire database. Ensure your application has read permissions on the database file.

2. **Concurrent Access**: SQLite handles concurrent reads well. However, if Campfire is actively writing to the database, there might be brief locks. These examples handle this gracefully.

3. **Database Location**: The default production database is typically located at:
   - Development: `db/development.sqlite3`
   - Production: `db/production.sqlite3`

4. **Security**: Ensure the database file permissions are set appropriately:
   ```bash
   chmod 644 /path/to/campfire/db/production.sqlite3
   ```

## Adding More Examples

When adding new examples to this directory:
1. Follow the naming convention: `{purpose}_{action}.{extension}`
2. Include inline documentation and usage instructions
3. Add an entry to this README
4. Ensure examples are read-only unless explicitly documented otherwise
