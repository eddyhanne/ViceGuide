<?php
function vg_db(): array {
    $cfgPath = __DIR__ . '/config.php';
    if (!file_exists($cfgPath)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'config.php fehlt im api-Ordner. Siehe config.sample.php.']);
        exit;
    }
    $cfg = require $cfgPath;

    $pdo = new PDO($cfg['db_dsn'], $cfg['db_user'] ?? null, $cfg['db_pass'] ?? null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $isSqlite = str_starts_with($cfg['db_dsn'], 'sqlite:');
    if ($isSqlite) {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id TEXT NOT NULL,
            parent_id INTEGER NULL REFERENCES comments(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            text TEXT NOT NULL,
            quote TEXT NULL,
            likes INTEGER NOT NULL DEFAULT 0,
            dislikes INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id VARCHAR(180) NOT NULL,
            parent_id INT NULL,
            name VARCHAR(60) NOT NULL,
            text TEXT NOT NULL,
            quote TEXT NULL,
            likes INT NOT NULL DEFAULT 0,
            dislikes INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_article_id (article_id),
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    return [$pdo, $cfg];
}
