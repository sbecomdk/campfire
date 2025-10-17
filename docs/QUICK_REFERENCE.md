# Quick Reference: Querying Unread Messages

This is a quick reference guide for querying unread messages from the Campfire SQLite database.

**Related Documentation:**
- 📚 Complete Guide: [LARAVEL_UNREAD_MESSAGES_QUERY.md](LARAVEL_UNREAD_MESSAGES_QUERY.md)
- 📊 Database Schema & Diagrams: [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)

## Key Concept

**Unread tracking is based on the `memberships.unread_at` field:**
- `NULL` = No unread messages
- `NOT NULL` = Has unread messages since that timestamp

## Essential Queries

### Check if user has unread messages
```sql
SELECT EXISTS(
    SELECT 1 FROM memberships 
    WHERE user_id = ? AND unread_at IS NOT NULL
) as has_unread;
```

### Get unread count by room for a user
```sql
SELECT 
    r.name as room_name,
    COUNT(msg.id) as unread_count
FROM memberships m
JOIN rooms r ON m.room_id = r.id
JOIN messages msg ON r.id = msg.room_id
WHERE m.user_id = ?
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
GROUP BY r.id, r.name;
```

### Get all users with unread messages
```sql
SELECT DISTINCT 
    u.id, u.name, u.email_address,
    COUNT(DISTINCT m.room_id) as unread_rooms
FROM users u
JOIN memberships m ON u.id = m.user_id
WHERE m.unread_at IS NOT NULL AND u.active = 1
GROUP BY u.id, u.name, u.email_address;
```

## Laravel Eloquent Quick Start

### 1. Configure Connection
```php
// config/database.php
'campfire' => [
    'driver' => 'sqlite',
    'database' => env('CAMPFIRE_DATABASE_PATH'),
],
```

### 2. Basic Model
```php
namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model {
    protected $connection = 'campfire';
    
    public function scopeUnread($query) {
        return $query->whereNotNull('unread_at');
    }
}
```

### 3. Query
```php
use App\Models\Campfire\Membership;

// Check if user has unread messages
$hasUnread = Membership::where('user_id', $userId)
    ->whereNotNull('unread_at')
    ->exists();

// Get count of rooms with unread messages
$unreadRoomsCount = Membership::where('user_id', $userId)
    ->unread()
    ->count();
```

## PHP PDO Example

```php
$pdo = new PDO("sqlite:/path/to/campfire/db/production.sqlite3");

$stmt = $pdo->prepare("
    SELECT EXISTS(
        SELECT 1 FROM memberships 
        WHERE user_id = :user_id AND unread_at IS NOT NULL
    ) as has_unread
");

$stmt->execute(['user_id' => $userId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$hasUnread = (bool)$result['has_unread'];
```

## Database Schema Summary

```
users
├── id (PK)
├── name
├── email_address
└── active

rooms
├── id (PK)
├── name
└── type

messages
├── id (PK)
├── room_id (FK → rooms)
├── creator_id (FK → users)
└── created_at

memberships
├── id (PK)
├── room_id (FK → rooms)
├── user_id (FK → users)
└── unread_at ⭐ (nullable datetime)

action_text_rich_texts
├── id (PK)
├── record_type ('Message')
├── record_id (FK → messages)
└── body (actual message content)
```

## Files & Resources

- 📚 Full Documentation: [LARAVEL_UNREAD_MESSAGES_QUERY.md](LARAVEL_UNREAD_MESSAGES_QUERY.md)
- 🔧 PHP Example Script: [../examples/query_unread_messages.php](../examples/query_unread_messages.php)
- 📝 Test Queries: [../examples/test_queries.sql](../examples/test_queries.sql)
- 📖 Examples README: [../examples/README.md](../examples/README.md)

## Important Notes

1. **Read-only recommended** - Campfire manages writes
2. **SQLite concurrent reads are safe** - Multiple readers OK
3. **Message body** is in `action_text_rich_texts` table
4. **Room types**: `Rooms::Open`, `Rooms::Closed`, `Rooms::Direct`
5. **Involvement levels**: `invisible`, `nothing`, `mentions`, `everything`

## Running the Example Script

```bash
# Set database path
export CAMPFIRE_DATABASE_PATH=/path/to/campfire/db/production.sqlite3

# List all users with unread
php examples/query_unread_messages.php

# Get details for specific user
php examples/query_unread_messages.php 1
```
