# Campfire

A modern team chat application built with Ruby on Rails.

## Features

- Real-time messaging with Action Cable
- Room-based conversations (Open, Closed, Direct)
- Rich text messages with Action Text
- Unread message tracking
- Push notifications via Web Push
- Full-text message search
- User mentions and notifications
- Message boosts (reactions)
- File attachments

## Technical Stack

- **Backend**: Ruby on Rails (edge version)
- **Database**: SQLite 3
- **Real-time**: Action Cable (WebSockets)
- **Frontend**: Hotwire (Turbo + Stimulus)
- **Jobs**: Resque
- **Search**: SQLite FTS5

## Getting Started

### Prerequisites

- Ruby 3.2+
- Node.js (for JavaScript dependencies)
- SQLite 3

### Installation

1. Install dependencies:
   ```bash
   bundle install
   ```

2. Set up the database:
   ```bash
   bin/rails db:setup
   ```

3. Start the server:
   ```bash
   bin/dev
   ```

## External Integration

### Querying Unread Messages from Laravel

If you need to query Campfire's unread messages from a Laravel application or any other system with access to the SQLite database, see the comprehensive guide:

📚 **[Laravel Unread Messages Query Documentation](docs/LARAVEL_UNREAD_MESSAGES_QUERY.md)**

This documentation includes:
- Database structure overview
- SQL query examples
- Laravel Eloquent model examples
- PHP PDO examples
- Complete integration guide

### Example Scripts

The [`examples/`](examples/) directory contains ready-to-use scripts demonstrating how to query the Campfire database:

- **[query_unread_messages.php](examples/query_unread_messages.php)** - PHP script using PDO to query unread messages
- **[test_queries.sql](examples/test_queries.sql)** - SQL queries for testing and validation

See the [examples README](examples/README.md) for usage instructions.

## Database Structure

Campfire uses SQLite for data persistence. The key tables are:

- `users` - User accounts
- `rooms` - Chat rooms/channels
- `messages` - Chat messages
- `memberships` - User-room relationships with unread tracking
- `action_text_rich_texts` - Message body content

See [`db/structure.sql`](db/structure.sql) for the complete schema.

## Unread Message Tracking

Campfire tracks unread messages using the `memberships.unread_at` timestamp field:

- When a message is posted, all disconnected members (except the sender) have their `unread_at` set to the message timestamp
- When a user reads messages in a room, their `unread_at` is set to NULL
- Messages with `created_at >= unread_at` are considered unread for that user

For detailed information on querying unread messages, see the [Laravel integration documentation](docs/LARAVEL_UNREAD_MESSAGES_QUERY.md).

## Testing

Run the test suite:

```bash
bin/rails test
```

Run system tests:

```bash
bin/rails test:system
```

## Deployment

See the included `Dockerfile` and `Procfile` for deployment configuration.

## License

[Add your license information here]

## Contributing

[Add contributing guidelines here]
