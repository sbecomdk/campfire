# Database Schema Diagram

## Unread Messages Entity Relationship

```
┌─────────────────────┐
│       users         │
├─────────────────────┤
│ id (PK)            │◄───┐
│ name               │    │
│ email_address      │    │
│ active             │    │
└─────────────────────┘    │
                           │
                           │ user_id (FK)
                           │
┌─────────────────────┐    │      ┌─────────────────────┐
│       rooms         │    │      │    memberships      │
├─────────────────────┤    │      ├─────────────────────┤
│ id (PK)            │◄───┼──────┤ id (PK)            │
│ name               │    │      │ room_id (FK)       │
│ type               │    └──────┤ user_id (FK)       │
│ creator_id         │           │ unread_at ⭐       │
└─────────────────────┘           │ involvement        │
         │                        │ connections        │
         │                        │ connected_at       │
         │                        └─────────────────────┘
         │ room_id (FK)
         │
         ▼
┌─────────────────────┐
│      messages       │
├─────────────────────┤
│ id (PK)            │
│ room_id (FK)       │
│ creator_id (FK)    │◄───────┐
│ created_at         │        │
│ client_message_id  │        │
└─────────────────────┘        │ record_id (FK)
                               │ + record_type = 'Message'
                               │
                    ┌──────────┴──────────────────┐
                    │ action_text_rich_texts      │
                    ├─────────────────────────────┤
                    │ id (PK)                     │
                    │ record_type                 │
                    │ record_id                   │
                    │ body                        │
                    └─────────────────────────────┘
```

## Unread Message Logic Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     Message Posted in Room                      │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Find all memberships where: │
        │  - room_id = message.room_id │
        │  - user_id ≠ message.creator │
        │  - involvement ≠ 'invisible' │
        │  - connections = 0           │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Update memberships:         │
        │  SET unread_at =             │
        │      message.created_at      │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Users now have unread       │
        │  messages in this room       │
        └──────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                User Reads Messages in Room                      │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Find membership where:      │
        │  - user_id = current_user    │
        │  - room_id = current_room    │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Update membership:          │
        │  SET unread_at = NULL        │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Room is now marked as read  │
        └──────────────────────────────┘
```

## Query Pattern for Unread Messages

```
┌─────────────────────────────────────────────────────────────────┐
│              Find Unread Messages for a User                    │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  1. Get memberships where:   │
        │     - user_id = ?            │
        │     - unread_at IS NOT NULL  │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  2. For each membership:     │
        │     Join with messages where:│
        │     - room_id = membership.  │
        │       room_id                │
        │     - created_at >=          │
        │       membership.unread_at   │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  3. Optionally join with:    │
        │     - action_text_rich_texts │
        │       for message body       │
        │     - users for creator info │
        └──────────────┬───────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Result: All unread messages │
        │  for the user                │
        └──────────────────────────────┘
```

## Key Fields Explained

### memberships.unread_at
- **Type**: `datetime(6)`, nullable
- **Purpose**: Tracks the timestamp of the oldest unread message in the room
- **NULL**: No unread messages
- **NOT NULL**: Has unread messages since this timestamp

### memberships.involvement
- **Type**: `varchar`, default 'mentions'
- **Values**:
  - `invisible`: User hidden from room
  - `nothing`: No notifications
  - `mentions`: Notify only when mentioned
  - `everything`: Notify on all messages

### memberships.connections
- **Type**: `integer`, default 0
- **Purpose**: Number of active WebSocket connections
- **0**: User is disconnected (eligible for unread marking)
- **>0**: User is connected (not marked as unread)

## Data Flow Example

### Scenario: User1 sends message to Room1

```
Initial State:
┌──────────────────────────────────────────────────┐
│ memberships                                      │
├────┬─────────┬─────────┬────────────┬───────────┤
│ id │ user_id │ room_id │ unread_at  │ connected │
├────┼─────────┼─────────┼────────────┼───────────┤
│  1 │    1    │    1    │    NULL    │     1     │ ← User1 (connected)
│  2 │    2    │    1    │    NULL    │     0     │ ← User2 (disconnected)
│  3 │    3    │    1    │    NULL    │     0     │ ← User3 (disconnected)
└────┴─────────┴─────────┴────────────┴───────────┘

After User1 sends message at 2024-01-15 10:30:00:
┌──────────────────────────────────────────────────┐
│ memberships                                      │
├────┬─────────┬─────────┬────────────┬───────────┤
│ id │ user_id │ room_id │ unread_at  │ connected │
├────┼─────────┼─────────┼────────────┼───────────┤
│  1 │    1    │    1    │    NULL    │     1     │ ← No change (sender)
│  2 │    2    │    1    │ 10:30:00   │     0     │ ← Updated!
│  3 │    3    │    1    │ 10:30:00   │     0     │ ← Updated!
└────┴─────────┴─────────┴────────────┴───────────┘

User2 reads messages:
┌──────────────────────────────────────────────────┐
│ memberships                                      │
├────┬─────────┬─────────┬────────────┬───────────┤
│ id │ user_id │ room_id │ unread_at  │ connected │
├────┼─────────┼─────────┼────────────┼───────────┤
│  1 │    1    │    1    │    NULL    │     1     │
│  2 │    2    │    1    │    NULL    │     0     │ ← Cleared!
│  3 │    3    │    1    │ 10:30:00   │     0     │ ← Still unread
└────┴─────────┴─────────┴────────────┴───────────┘
```

## Quick Query Reference

### Check if user has ANY unread messages
```sql
SELECT EXISTS(
    SELECT 1 FROM memberships 
    WHERE user_id = ? AND unread_at IS NOT NULL
)
```

### Count unread messages for user in specific room
```sql
SELECT COUNT(*) 
FROM messages 
WHERE room_id = ?
  AND created_at >= (
    SELECT unread_at FROM memberships 
    WHERE user_id = ? AND room_id = ?
  )
```

### Get all rooms with unread counts for user
```sql
SELECT 
    r.id, r.name,
    COUNT(msg.id) as unread_count
FROM memberships m
JOIN rooms r ON m.room_id = r.id
JOIN messages msg ON r.id = msg.room_id
WHERE m.user_id = ?
  AND m.unread_at IS NOT NULL
  AND msg.created_at >= m.unread_at
GROUP BY r.id, r.name
```
