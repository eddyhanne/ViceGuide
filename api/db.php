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
    } else {
        /* Verbindung fest auf UTC, damit CURRENT_TIMESTAMP (Kommentare etc.)
           deterministisch in UTC geschrieben wird. Der Client haengt beim
           Anzeigen ein "Z" an und rechnet in die Zeitzone des Besuchers um.
           Numerischer Offset braucht keine MySQL-Zeitzonentabellen. */
        try { $pdo->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) {}
    }

    /* Schema-Migration (CREATE TABLE/ALTER TABLE) ist teuer und lief bisher bei
       jedem einzelnen Request mit, article.php, entry.php, sitemap.php und
       jeder api/*.php-Aufruf rufen vg_db() auf. Ein leichter Probe-Query
       reicht, um zu pruefen, ob Tabellen und Spalten schon passen, die volle
       Migration unten laeuft nur noch, wenn der Probe fehlschlaegt (frisches
       Deployment oder ein noch ausstehendes Schema-Upgrade). */
    $schemaReady = false;
    try {
        $pdo->query('SELECT id, author_token, author_email, notify_replies, reply_token FROM comments LIMIT 1');
        $pdo->query('SELECT draft_json, pinned FROM articles LIMIT 1');
        $pdo->query('SELECT slug, draft_json FROM db_entries LIMIT 1');
        $pdo->query('SELECT section FROM section_meta LIMIT 1');
        $pdo->query('SELECT comment_id FROM comment_votes LIMIT 1');
        $pdo->query('SELECT id FROM newsletter_subscribers LIMIT 1');
        $schemaReady = true;
    } catch (Throwable $e) {
        $schemaReady = false;
    }

    if (!$schemaReady) {
    if ($isSqlite) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id TEXT NOT NULL,
            parent_id INTEGER NULL REFERENCES comments(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            text TEXT NOT NULL,
            quote TEXT NULL,
            author_token TEXT NULL,
            author_email TEXT NULL,
            notify_replies INTEGER NOT NULL DEFAULT 0,
            reply_token TEXT NULL,
            likes INTEGER NOT NULL DEFAULT 0,
            dislikes INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS comment_votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
            voter TEXT NOT NULL,
            dir TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(comment_id, voter)
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
            draft_json TEXT,
            pinned INTEGER NOT NULL DEFAULT 0,
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
            draft_json TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS section_meta (
            section TEXT PRIMARY KEY,
            img TEXT,
            imgfit_json TEXT,
            credit TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT \'pending\',
            token TEXT NOT NULL,
            consent_ip TEXT NULL,
            confirmed_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        try {
            $cols = $pdo->query("PRAGMA table_info(db_entries)")->fetchAll();
            $names = array_column($cols, 'name');
            if (!in_array('slug', $names, true)) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug TEXT'); }
            if (!in_array('draft_json', $names, true)) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN draft_json TEXT'); }
        } catch (Throwable $e) {
            // Migration darf nie den ganzen Request (auch fremde Endpunkte wie
            // articles.php, die vg_db() nur fuer die Verbindung nutzen) mit
            // hinunterreissen, z.B. bei einer Race Condition zwischen zwei
            // gleichzeitigen Requests, die beide die Spalte fehlend sehen.
        }
        try {
            $cols = $pdo->query("PRAGMA table_info(articles)")->fetchAll();
            $anames = array_column($cols, 'name');
            if (!in_array('draft_json', $anames, true)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN draft_json TEXT');
            }
            if (!in_array('pinned', $anames, true)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN pinned INTEGER NOT NULL DEFAULT 0');
            }
        } catch (Throwable $e) {
            // s.o.
        }
        try {
            $cols = $pdo->query("PRAGMA table_info(comments)")->fetchAll();
            $cnames = array_column($cols, 'name');
            if (!in_array('author_token', $cnames, true)) {
                $pdo->exec('ALTER TABLE comments ADD COLUMN author_token TEXT');
            }
            if (!in_array('author_email', $cnames, true)) {
                $pdo->exec('ALTER TABLE comments ADD COLUMN author_email TEXT');
            }
            if (!in_array('notify_replies', $cnames, true)) {
                $pdo->exec('ALTER TABLE comments ADD COLUMN notify_replies INTEGER NOT NULL DEFAULT 0');
            }
            if (!in_array('reply_token', $cnames, true)) {
                $pdo->exec('ALTER TABLE comments ADD COLUMN reply_token TEXT');
            }
        } catch (Throwable $e) {
            // s.o.
        }
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id VARCHAR(180) NOT NULL,
            parent_id INT NULL,
            name VARCHAR(60) NOT NULL,
            text TEXT NOT NULL,
            quote TEXT NULL,
            author_token VARCHAR(100) NULL,
            author_email VARCHAR(190) NULL,
            notify_replies TINYINT NOT NULL DEFAULT 0,
            reply_token VARCHAR(64) NULL,
            likes INT NOT NULL DEFAULT 0,
            dislikes INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_article_id (article_id),
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS comment_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            voter VARCHAR(100) NOT NULL,
            dir VARCHAR(4) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_comment_voter (comment_id, voter),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
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
            draft_json MEDIUMTEXT NULL,
            pinned TINYINT NOT NULL DEFAULT 0,
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
            draft_json MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_section (section),
            INDEX idx_section_slug (section, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS section_meta (
            section VARCHAR(30) PRIMARY KEY,
            img MEDIUMTEXT NULL,
            imgfit_json VARCHAR(100) NULL,
            credit VARCHAR(200) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            status VARCHAR(16) NOT NULL DEFAULT \'pending\',
            token VARCHAR(64) NOT NULL,
            consent_ip VARCHAR(64) NULL,
            confirmed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'slug'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug VARCHAR(180) NULL, ADD INDEX idx_section_slug (section, slug)');
            }
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'draft_json'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN draft_json MEDIUMTEXT NULL'); }
        } catch (Throwable $e) {
            // Migration darf nie den ganzen Request (auch fremde Endpunkte wie
            // articles.php, die vg_db() nur fuer die Verbindung nutzen) mit
            // hinunterreissen, z.B. bei einer Race Condition zwischen zwei
            // gleichzeitigen Requests, die beide die Spalte fehlend sehen.
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'draft_json'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE articles ADD COLUMN draft_json MEDIUMTEXT NULL'); }
            $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'pinned'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE articles ADD COLUMN pinned TINYINT NOT NULL DEFAULT 0'); }
        } catch (Throwable $e) {
            // s.o.
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'author_token'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE comments ADD COLUMN author_token VARCHAR(100) NULL'); }
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'author_email'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE comments ADD COLUMN author_email VARCHAR(190) NULL'); }
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'notify_replies'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE comments ADD COLUMN notify_replies TINYINT NOT NULL DEFAULT 0'); }
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'reply_token'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE comments ADD COLUMN reply_token VARCHAR(64) NULL'); }
        } catch (Throwable $e) {
            // s.o.
        }
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

/* Nicht abbrechende Variante von vg_require_admin(): fuer GET-Antworten, die
   fuer alle Besucher funktionieren muessen, aber fuer eingeloggte Admins
   zusaetzlich den eigenen Entwurfsstand einblenden sollen (siehe
   vg_merge_draft() in articles.php/db_entries.php). */
function vg_is_admin(): bool {
    // Anonyme Besucher (ohne Session-Cookie) bekommen gar keine Session: sonst
    // sendet jeder oeffentliche GET ein Set-Cookie, was Shared-/Proxy-Caching
    // blockiert und pro Besucher unnoetig eine Session-Datei anlegt. Nur wer
    // schon eingeloggt war (Cookie vorhanden), startet die Session zur Pruefung.
    if (empty($_COOKIE[session_name()])) return false;
    vg_session_start();
    return !empty($_SESSION['vg_admin']);
}

function vg_require_admin(array $cfg): void {
    // Ohne vorhandenes Session-Cookie kann niemand angemeldet sein: dann gar
    // keine Session starten (kein Set-Cookie bei unautorisierten Zugriffen).
    if (!empty($_COOKIE[session_name()])) vg_session_start();
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
