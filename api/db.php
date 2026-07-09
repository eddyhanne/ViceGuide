<?php
function vg_db(): array {
    $cfgPath = __DIR__ . '/config.php';
    if (!file_exists($cfgPath)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS articles (
            id TEXT PRIMARY KEY,
            cat TEXT NOT NULL,
            title TEXT NOT NULL,
            article_date TEXT,
            summary TEXT,
            meta TEXT,
            lead TEXT,
            content_json TEXT,
            sources_json TEXT,
            img TEXT,
            imgfit_json TEXT,
            credit TEXT,
            author TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS db_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            name TEXT NOT NULL,
            sub TEXT,
            cat TEXT,
            src TEXT,
            description TEXT,
            fields_json TEXT,
            img TEXT,
            imgfit_json TEXT,
            credit TEXT,
            slug TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        try {
            $cols = $pdo->query("PRAGMA table_info(db_entries)")->fetchAll();
            if (!in_array('slug', array_column($cols, 'name'), true)) {
                $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug TEXT');
            }
        } catch (Throwable $e) {
            // Migration darf nie den ganzen Request (auch fremde Endpunkte wie
            // articles.php, die vg_db() nur fuer die Verbindung nutzen) mit
            // hinunterreissen, z.B. bei einer Race Condition zwischen zwei
            // gleichzeitigen Requests, die beide die Spalte fehlend sehen.
        }
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS articles (
            id VARCHAR(180) PRIMARY KEY,
            cat VARCHAR(30) NOT NULL,
            title VARCHAR(300) NOT NULL,
            article_date VARCHAR(30) NULL,
            summary TEXT NULL,
            meta VARCHAR(200) NULL,
            lead TEXT NULL,
            content_json MEDIUMTEXT NULL,
            sources_json TEXT NULL,
            img MEDIUMTEXT NULL,
            imgfit_json VARCHAR(100) NULL,
            credit VARCHAR(200) NULL,
            author VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS db_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section VARCHAR(30) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            name VARCHAR(160) NOT NULL,
            sub VARCHAR(200) NULL,
            cat VARCHAR(100) NULL,
            src VARCHAR(200) NULL,
            description MEDIUMTEXT NULL,
            fields_json TEXT NULL,
            img MEDIUMTEXT NULL,
            imgfit_json VARCHAR(100) NULL,
            credit VARCHAR(200) NULL,
            slug VARCHAR(180) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_section (section),
            INDEX idx_section_slug (section, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'slug'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug VARCHAR(180) NULL, ADD INDEX idx_section_slug (section, slug)');
            }
        } catch (Throwable $e) {
            // Migration darf nie den ganzen Request (auch fremde Endpunkte wie
            // articles.php, die vg_db() nur fuer die Verbindung nutzen) mit
            // hinunterreissen, z.B. bei einer Race Condition zwischen zwei
            // gleichzeitigen Requests, die beide die Spalte fehlend sehen.
        }
    }

    return [$pdo, $cfg];
}

/* Slug-Erzeugung fuer Datenbank-Eintraege, spiegelt slugify() im Frontend
   (index.html) und vg_slugify() in api/articles.php. Eigener Name (nicht
   vg_slugify), weil db.php von articles.php per require eingebunden wird,
   das dort bereits eine eigene vg_slugify()-Funktion fuer Artikel-ids
   definiert, Gleichnamigkeit wuerde zu einem fatalen "Cannot redeclare
   function"-Fehler fuehren (hat genau das live auf viceguide.de ausgeloest). */
function vg_entry_slugify(string $s): string {
    $map = ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss'];
    $s = strtr($s, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return mb_substr($s, 0, 180);
}

/* Vergibt fehlenden Datenbank-Eintraegen einmalig einen festen slug aus dem
   name-Feld (eindeutig je section), analog zur Artikel-id. Laeuft bei jedem
   GET mit, macht aber nichts mehr sobald alle Eintraege einen slug haben. */
function vg_ensure_entry_slugs(PDO $pdo): void {
    try {
        $rows = $pdo->query("SELECT id, section, name FROM db_entries WHERE slug IS NULL OR slug = ''")->fetchAll();
        if (!$rows) return;
        $existing = $pdo->query("SELECT section, slug FROM db_entries WHERE slug IS NOT NULL AND slug <> ''")->fetchAll();
        $taken = [];
        foreach ($existing as $r) { $taken[$r['section'] . '::' . $r['slug']] = true; }

        $upd = $pdo->prepare('UPDATE db_entries SET slug = ? WHERE id = ?');
        foreach ($rows as $r) {
            $base = vg_entry_slugify($r['name']) ?: 'eintrag';
            $slug = $base; $n = 2;
            while (!empty($taken[$r['section'] . '::' . $slug])) { $slug = $base . '-' . $n; $n++; }
            $taken[$r['section'] . '::' . $slug] = true;
            $upd->execute([$slug, $r['id']]);
        }
    } catch (Throwable $e) {
        // Falls die slug-Spalte aus irgendeinem Grund fehlt (z.B. ALTER TABLE
        // in vg_db() ohne noetige Rechte fehlgeschlagen), soll das den
        // aufrufenden Endpunkt nicht mit abschiessen, Eintraege kommen dann
        // eben ohne slug zurueck statt den ganzen Request zu crashen.
    }
}

function vg_require_admin(array $cfg): void {
    vg_session_start();
    if (empty($_SESSION['vg_admin'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode(['error' => 'Nicht angemeldet.']);
        exit;
    }
}

function vg_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 90,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
