# Querying Unread Messages from Laravel

This document explains how to query unread messages from a Laravel application that has access to the Campfire SQLite database.

> 💡 **Quick Start**: See [QUICK_REFERENCE.md](QUICK_REFERENCE.md) for a condensed cheat sheet of common queries.

## Database Structure Overview

### Key Tables

1. **users** - Contains user information
   - `id` (integer, primary key)
   - `name` (varchar)
   - `email_address` (varchar)
   - `active` (boolean)

2. **rooms** - Contains room/channel information
   - `id` (integer, primary key)
   - `name` (varchar)
   - `type` (varchar) - Types: "Rooms::Open", "Rooms::Closed", "Rooms::Direct"
   - `creator_id` (bigint)

3. **messages** - Contains all messages
   - `id` (integer, primary key)
   - `room_id` (integer, foreign key to rooms)
   - `creator_id` (integer, foreign key to users)
   - `created_at` (datetime)
   - `client_message_id` (varchar)

4. **memberships** - Links users to rooms and tracks read status
   - `id` (integer, primary key)
   - `room_id` (integer, foreign key to rooms)
   - `user_id` (integer, foreign key to users)
   - `unread_at` (datetime, nullable) - **KEY FIELD**: When set, indicates there are unread messages since this timestamp
   - `involvement` (varchar) - "invisible", "nothing", "mentions", "everything"
   - `connections` (integer)
   - `connected_at` (datetime)

5. **action_text_rich_texts** - Contains message body content
   - `id` (integer, primary key)
   - `body` (text)
   - `record_type` (varchar) - Will be "Message" for messages
   - `record_id` (bigint) - References message.id

## How Unread Tracking Works

- When a message is created in a room, all **visible** and **disconnected** members (except the message creator) have their `memberships.unread_at` field set to the message's `created_at` timestamp.
- When a user reads messages in a room, their `memberships.unread_at` is set to `NULL`.
- A membership has unread messages if `unread_at IS NOT NULL`.

## SQL Queries

### 1. Get All Users with Unread Messages

```sql
SELECT DISTINCT 
    u.id,
    u.name,
    u.email_address,
    COUNT(DISTINCT m.room_id) as unread_rooms_count
FROM users u
INNER JOIN memberships m ON u.id = m.user_id
WHERE m.unread_at IS NOT NULL
  AND u.active = 1
GROUP BY u.id, u.name, u.email_address
ORDER BY u.name;
```

### 2. Get All Unread Messages for a Specific User

```sql
SELECT 
    u.id as user_id,
    u.name as user_name,
    r.id as room_id,
    r.name as room_name,
    r.type as room_type,
    msg.id as message_id,
    msg.created_at as message_created_at,
    creator.name as message_creator,
    rt.body as message_body,
    m.unread_at
FROM users u
INNER JOIN memberships m ON u.id = m.user_id
INNER JOIN rooms r ON m.room_id = r.id
INNER JOIN messages msg ON r.id = msg.room_id
INNER JOIN users creator ON msg.creator_id = creator.id
LEFT JOIN action_text_rich_texts rt ON rt.record_type = 'Message' AND rt.record_id = msg.id
WHERE u.id = ? -- Replace with actual user ID
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
ORDER BY msg.created_at DESC;
```

### 3. Get Unread Message Counts by Room for a User

```sql
SELECT 
    u.id as user_id,
    u.name as user_name,
    r.id as room_id,
    r.name as room_name,
    r.type as room_type,
    m.unread_at,
    COUNT(msg.id) as unread_count,
    MAX(msg.created_at) as latest_message_at
FROM users u
INNER JOIN memberships m ON u.id = m.user_id
INNER JOIN rooms r ON m.room_id = r.id
INNER JOIN messages msg ON r.id = msg.room_id
WHERE u.id = ? -- Replace with actual user ID
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
GROUP BY u.id, u.name, r.id, r.name, r.type, m.unread_at
ORDER BY latest_message_at DESC;
```

### 4. Get All Unread Messages Across All Users

```sql
SELECT 
    u.id as user_id,
    u.name as user_name,
    r.id as room_id,
    r.name as room_name,
    COUNT(msg.id) as unread_count
FROM users u
INNER JOIN memberships m ON u.id = m.user_id
INNER JOIN rooms r ON m.room_id = r.id
INNER JOIN messages msg ON r.id = msg.room_id
WHERE m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
  AND u.active = 1
GROUP BY u.id, u.name, r.id, r.name
ORDER BY u.name, r.name;
```

### 5. Check if Specific User Has Any Unread Messages (Boolean)

```sql
SELECT EXISTS(
    SELECT 1
    FROM memberships m
    WHERE m.user_id = ? -- Replace with actual user ID
      AND m.unread_at IS NOT NULL
) as has_unread_messages;
```

## Laravel Eloquent Examples

If you want to set up Laravel models to query this database, here's how you can do it:

### Step 1: Configure SQLite Connection in Laravel

Add to `config/database.php`:

```php
'connections' => [
    // ... other connections
    'campfire' => [
        'driver' => 'sqlite',
        'database' => env('CAMPFIRE_DATABASE_PATH', '/path/to/campfire/db/production.sqlite3'),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    ],
],
```

Add to `.env`:

```
CAMPFIRE_DATABASE_PATH=/path/to/campfire/db/production.sqlite3
```

### Step 2: Create Laravel Models

**User Model:**
```php
<?php

namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $connection = 'campfire';
    protected $table = 'users';
    
    public function memberships()
    {
        return $this->hasMany(Membership::class, 'user_id');
    }
    
    public function unreadMemberships()
    {
        return $this->memberships()->whereNotNull('unread_at');
    }
    
    public function hasUnreadMessages()
    {
        return $this->unreadMemberships()->exists();
    }
}
```

**Membership Model:**
```php
<?php

namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $connection = 'campfire';
    protected $table = 'memberships';
    
    protected $casts = [
        'unread_at' => 'datetime',
        'connected_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
    
    public function unreadMessages()
    {
        return $this->hasMany(Message::class, 'room_id', 'room_id')
            ->where('created_at', '>=', $this->unread_at);
    }
    
    public function scopeUnread($query)
    {
        return $query->whereNotNull('unread_at');
    }
}
```

**Room Model:**
```php
<?php

namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $connection = 'campfire';
    protected $table = 'rooms';
    
    public function messages()
    {
        return $this->hasMany(Message::class, 'room_id');
    }
    
    public function memberships()
    {
        return $this->hasMany(Membership::class, 'room_id');
    }
}
```

**Message Model:**
```php
<?php

namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'campfire';
    protected $table = 'messages';
    
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    public function richText()
    {
        return $this->morphOne(ActionTextRichText::class, 'record');
    }
}
```

**ActionTextRichText Model:**
```php
<?php

namespace App\Models\Campfire;

use Illuminate\Database\Eloquent\Model;

class ActionTextRichText extends Model
{
    protected $connection = 'campfire';
    protected $table = 'action_text_rich_texts';
    
    public function record()
    {
        return $this->morphTo();
    }
}
```

### Step 3: Query Examples

**Get all users with unread messages:**
```php
use App\Models\Campfire\User;

$usersWithUnread = User::whereHas('unreadMemberships')
    ->where('active', true)
    ->with(['unreadMemberships.room'])
    ->get();

foreach ($usersWithUnread as $user) {
    echo "User: {$user->name}\n";
    foreach ($user->unreadMemberships as $membership) {
        echo "  - Unread in room: {$membership->room->name}\n";
    }
}
```

**Get unread message count for a specific user:**
```php
use App\Models\Campfire\User;

$userId = 1;
$user = User::find($userId);

$unreadCount = $user->unreadMemberships()
    ->withCount([
        'unreadMessages' => function ($query) use ($user) {
            // Count messages created after the unread_at timestamp
        }
    ])
    ->get()
    ->sum('unread_messages_count');

echo "User {$user->name} has {$unreadCount} unread messages\n";
```

**Check if a user has unread messages:**
```php
use App\Models\Campfire\User;

$userId = 1;
$user = User::find($userId);

if ($user->hasUnreadMessages()) {
    echo "User has unread messages\n";
} else {
    echo "User has no unread messages\n";
}
```

**Get detailed unread messages for a user:**
```php
use App\Models\Campfire\User;
use App\Models\Campfire\Message;

$userId = 1;
$user = User::find($userId);

// Get all rooms with unread messages
$unreadMemberships = $user->unreadMemberships()->with('room')->get();

foreach ($unreadMemberships as $membership) {
    $unreadMessages = Message::where('room_id', $membership->room_id)
        ->where('created_at', '>=', $membership->unread_at)
        ->with(['creator', 'richText'])
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo "Room: {$membership->room->name} ({$unreadMessages->count()} unread)\n";
    
    foreach ($unreadMessages as $message) {
        echo "  - From {$message->creator->name}: ";
        echo strip_tags($message->richText->body ?? 'No content');
        echo "\n";
    }
}
```

## Important Notes

1. **Read-Only Access**: If your Laravel application only needs to read data, ensure the database file has appropriate read permissions.

2. **Concurrent Access**: SQLite handles concurrent reads well, but be cautious with writes. Since Campfire is the primary application, your Laravel app should ideally only read data.

3. **Involvement Levels**: The `memberships.involvement` field determines notification preferences:
   - `invisible`: User won't appear as a member
   - `nothing`: No notifications
   - `mentions`: Only notify on mentions
   - `everything`: Notify on all messages

4. **Message Body**: The actual message content is stored in the `action_text_rich_texts` table using Rails' ActionText feature. Join with this table to get message bodies.

5. **Room Types**: 
   - `Rooms::Open`: Public rooms all users can join
   - `Rooms::Closed`: Private rooms with explicit membership
   - `Rooms::Direct`: Direct messages between users

## Performance Considerations

For large datasets, consider:
- Adding indexes on frequently queried fields
- Caching query results in Laravel
- Using pagination for large result sets
- Consider using database views for complex repeated queries

## Example Database View

You could create a view in the Campfire database for easier querying:

```sql
CREATE VIEW unread_messages_view AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    u.email_address,
    r.id as room_id,
    r.name as room_name,
    r.type as room_type,
    msg.id as message_id,
    msg.created_at as message_created_at,
    creator.name as message_creator_name,
    rt.body as message_body,
    m.unread_at
FROM users u
INNER JOIN memberships m ON u.id = m.user_id
INNER JOIN rooms r ON m.room_id = r.id
INNER JOIN messages msg ON r.id = msg.room_id
INNER JOIN users creator ON msg.creator_id = creator.id
LEFT JOIN action_text_rich_texts rt ON rt.record_type = 'Message' AND rt.record_id = msg.id
WHERE m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
  AND u.active = 1;
```

Then query it simply:
```sql
SELECT * FROM unread_messages_view WHERE user_id = ?;
```
