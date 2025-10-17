# SQL Query Validation Tests

This file contains the SQL queries from the documentation for easy testing.

## Test Setup

To test these queries, you can:

1. Create a test database with the structure:
   ```bash
   sqlite3 test.db < db/structure.sql
   ```

2. Run queries using sqlite3 CLI:
   ```bash
   sqlite3 db/production.sqlite3 < examples/test_queries.sql
   ```

## Query 1: Get All Users with Unread Messages

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

## Query 2: Get All Unread Messages for a Specific User

Replace `?` with actual user ID (e.g., 1):

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
WHERE u.id = 1
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
ORDER BY msg.created_at DESC;
```

## Query 3: Get Unread Message Counts by Room for a User

Replace `?` with actual user ID (e.g., 1):

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
WHERE u.id = 1
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
GROUP BY u.id, u.name, r.id, r.name, r.type, m.unread_at
ORDER BY latest_message_at DESC;
```

## Query 4: Get All Unread Messages Across All Users

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

## Query 5: Check if Specific User Has Any Unread Messages

Replace `?` with actual user ID (e.g., 1):

```sql
SELECT EXISTS(
    SELECT 1
    FROM memberships m
    WHERE m.user_id = 1
      AND m.unread_at IS NOT NULL
) as has_unread_messages;
```

## Quick Verification Queries

### Check table structure
```sql
.schema memberships
.schema messages
.schema users
.schema rooms
```

### List all tables
```sql
.tables
```

### Sample data queries
```sql
-- List all users
SELECT id, name, email_address, active FROM users LIMIT 10;

-- List all rooms
SELECT id, name, type FROM rooms LIMIT 10;

-- List memberships with unread status
SELECT m.id, u.name, r.name, m.unread_at 
FROM memberships m
JOIN users u ON m.user_id = u.id
JOIN rooms r ON m.room_id = r.id
LIMIT 10;
```
