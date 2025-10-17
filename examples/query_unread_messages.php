<?php
/**
 * Example script demonstrating how to query Campfire's SQLite database
 * for unread messages from a Laravel application (or any PHP application).
 * 
 * This example uses PDO for direct database access.
 * 
 * Usage:
 *   php examples/query_unread_messages.php [user_id]
 */

// Configuration
$databasePath = getenv('CAMPFIRE_DATABASE_PATH') ?: '/path/to/campfire/db/production.sqlite3';

// Check if database file exists
if (!file_exists($databasePath)) {
    die("Error: Database file not found at: $databasePath\n");
}

// Connect to the SQLite database
try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to Campfire database\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

/**
 * Get all users with unread messages
 */
function getAllUsersWithUnreadMessages($pdo) {
    $sql = "
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
        ORDER BY u.name
    ";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if a specific user has unread messages
 */
function hasUnreadMessages($pdo, $userId) {
    $sql = "
        SELECT EXISTS(
            SELECT 1
            FROM memberships m
            WHERE m.user_id = :user_id
              AND m.unread_at IS NOT NULL
        ) as has_unread
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (bool)$result['has_unread'];
}

/**
 * Get unread message counts by room for a specific user
 */
function getUnreadMessageCountsByRoom($pdo, $userId) {
    $sql = "
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
        WHERE u.id = :user_id
          AND m.unread_at IS NOT NULL
          AND msg.created_at >= m.unread_at
        GROUP BY u.id, u.name, r.id, r.name, r.type, m.unread_at
        ORDER BY latest_message_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all unread messages for a specific user with details
 */
function getUnreadMessages($pdo, $userId, $limit = 50) {
    $sql = "
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
        WHERE u.id = :user_id
          AND m.unread_at IS NOT NULL
          AND msg.created_at >= m.unread_at
        ORDER BY msg.created_at DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Format and display results
 */
function displayResults($title, $data) {
    echo str_repeat("=", 70) . "\n";
    echo $title . "\n";
    echo str_repeat("=", 70) . "\n";
    
    if (empty($data)) {
        echo "No results found.\n\n";
        return;
    }
    
    foreach ($data as $row) {
        foreach ($row as $key => $value) {
            echo sprintf("%-25s: %s\n", $key, $value ?? 'NULL');
        }
        echo str_repeat("-", 70) . "\n";
    }
    echo "\n";
}

// Main execution
echo "Campfire Unread Messages Query Example\n";
echo str_repeat("=", 70) . "\n\n";

// Get command line argument for user ID
$specificUserId = $argv[1] ?? null;

if ($specificUserId) {
    // Query for specific user
    echo "Querying unread messages for User ID: $specificUserId\n\n";
    
    // Check if user has unread messages
    $hasUnread = hasUnreadMessages($pdo, $specificUserId);
    echo "User has unread messages: " . ($hasUnread ? "YES" : "NO") . "\n\n";
    
    if ($hasUnread) {
        // Get unread counts by room
        $roomCounts = getUnreadMessageCountsByRoom($pdo, $specificUserId);
        displayResults("Unread Message Counts by Room", $roomCounts);
        
        // Get detailed unread messages
        $messages = getUnreadMessages($pdo, $specificUserId, 10);
        displayResults("Recent Unread Messages (Max 10)", $messages);
    }
} else {
    // Query for all users
    echo "Querying all users with unread messages\n\n";
    
    $users = getAllUsersWithUnreadMessages($pdo);
    displayResults("Users with Unread Messages", $users);
    
    if (!empty($users)) {
        echo "\nTip: Run this script with a user ID to see detailed unread messages:\n";
        echo "  php " . $argv[0] . " {user_id}\n\n";
        
        echo "Example:\n";
        echo "  php " . $argv[0] . " " . $users[0]['id'] . "\n\n";
    }
}

echo "Done!\n";
