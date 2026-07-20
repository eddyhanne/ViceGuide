<?php
/* Setzt beim erstmaligen Anlegen der Spalte seo_index eine kuratierte
   Allowlist auf indexierbar (1). Alle uebrigen Eintraege bleiben auf dem
   Default 0 (noindex), bis sie redaktionell freigegeben werden. Bewusst per
   Name statt Wortzahl-Automatik (siehe SEO-Auftrag). Laeuft nur einmal, in der
   Migration direkt nach dem ALTER TABLE. */
function vg_seed_seo_allowlist(PDO $pdo): void {
    $allow = [
        'characters' => ['Jason Duval', 'Lucia Caminos', 'Boobie Ike', "Dre'Quan Priest", 'Raul Bautista', 'Cal Hampton'],
        'locations'  => ['Vice City', 'Leonida Keys', 'Grassrivers', 'Port Gellhorn', 'Ambrosia'],
    ];
    try {
        foreach ($allow as $section => $names) {
            $ph = implode(',', array_fill(0, count($names), '?'));
            $st = $pdo->prepare("UPDATE db_entries SET seo_index = 1 WHERE section = ? AND name IN ($ph)");
            $st->execute(array_merge([$section], $names));
        }
    } catch (Throwable $e) {
        // Seeding darf den Request nie hinunterreissen (analog zur uebrigen Migration).
    }
}
/* Setzt beim erstmaligen Anlegen der status-Spalte einen konservativen
   Startwert: jeder Artikel der Kategorie "leaks" (Geruechte & Leaks) gilt als
   unbestaetigt (rumor). Alle uebrigen bleiben ohne Status (kein Badge), bis sie
   redaktionell gesetzt werden, weil sich "bestaetigt" nicht zuverlaessig aus der
   Kategorie ableiten laesst (auch eine News kann spekulativ sein). Laeuft nur
   einmal, direkt nach dem ALTER TABLE. */
function vg_seed_status_from_cat(PDO $pdo): void {
    try {
        $st = $pdo->prepare("UPDATE articles SET status = 'rumor' WHERE cat = 'leaks' AND (status IS NULL OR status = '')");
        $st->execute();
    } catch (Throwable $e) {
        // Seeding darf den Request nie hinunterreissen (analog zur uebrigen Migration).
    }
}
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
        $pdo->query('SELECT id, author_token, author_email, notify_replies, reply_token, spoiler FROM comments LIMIT 1');
        $pdo->query('SELECT draft_json, pinned, tldr_json, status, status_note FROM articles LIMIT 1');
        $pdo->query('SELECT slug, draft_json, seo_index FROM db_entries LIMIT 1');
        $pdo->query('SELECT section FROM section_meta LIMIT 1');
        $pdo->query('SELECT comment_id FROM comment_votes LIMIT 1');
        $pdo->query('SELECT id FROM newsletter_subscribers LIMIT 1');
        $pdo->query('SELECT id FROM hits LIMIT 1');
        $pdo->query('SELECT id FROM events LIMIT 1');
        $pdo->query('SELECT id FROM gsc_rows LIMIT 1');
        $pdo->query('SELECT id FROM creators LIMIT 1');
        $pdo->query('SELECT id FROM creator_favorites LIMIT 1');
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
            spoiler INTEGER NOT NULL DEFAULT 0,
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
            tldr_json TEXT,
            status TEXT,
            status_note TEXT,
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
            seo_index INTEGER NOT NULL DEFAULT 0,
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS hits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path TEXT NOT NULL,
            referrer TEXT NULL,
            ref_host TEXT NULL,
            utm_source TEXT NULL,
            utm_medium TEXT NULL,
            utm_campaign TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            path TEXT NULL,
            q TEXT NULL,
            num INTEGER NOT NULL DEFAULT 0,
            num2 INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gsc_rows (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kind TEXT NOT NULL,
            label TEXT NOT NULL,
            clicks INTEGER NOT NULL DEFAULT 0,
            impressions INTEGER NOT NULL DEFAULT 0,
            ctr REAL NOT NULL DEFAULT 0,
            position REAL NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gsc_meta (
            kind TEXT PRIMARY KEY,
            range_label TEXT NULL,
            imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS creators (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            tagline TEXT,
            bio TEXT,
            platforms_json TEXT,
            videos_json TEXT,
            avatar TEXT,
            avatarfit_json TEXT,
            twitch_login TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            seo_index INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            draft_json TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS creator_favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            creator_id INTEGER NOT NULL REFERENCES creators(id) ON DELETE CASCADE,
            section TEXT NOT NULL,
            entry_slug TEXT NOT NULL,
            label TEXT,
            quote TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0
        )');
        try {
            $cols = $pdo->query("PRAGMA table_info(db_entries)")->fetchAll();
            $names = array_column($cols, 'name');
            if (!in_array('slug', $names, true)) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug TEXT'); }
            if (!in_array('draft_json', $names, true)) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN draft_json TEXT'); }
            if (!in_array('seo_index', $names, true)) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN seo_index INTEGER NOT NULL DEFAULT 0'); vg_seed_seo_allowlist($pdo); }
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
            if (!in_array('tldr_json', $anames, true)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN tldr_json TEXT');
            }
            if (!in_array('status', $anames, true)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN status TEXT');
                $pdo->exec('ALTER TABLE articles ADD COLUMN status_note TEXT');
                vg_seed_status_from_cat($pdo);
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
            if (!in_array('spoiler', $cnames, true)) {
                $pdo->exec('ALTER TABLE comments ADD COLUMN spoiler INTEGER NOT NULL DEFAULT 0');
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
            spoiler TINYINT NOT NULL DEFAULT 0,
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
            tldr_json TEXT NULL,
            status VARCHAR(20) NULL,
            status_note TEXT NULL,
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
            seo_index TINYINT NOT NULL DEFAULT 0,
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS hits (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            path VARCHAR(300) NOT NULL,
            referrer VARCHAR(500) NULL,
            ref_host VARCHAR(190) NULL,
            utm_source VARCHAR(100) NULL,
            utm_medium VARCHAR(100) NULL,
            utm_campaign VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_ref_host (ref_host),
            INDEX idx_utm_source (utm_source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(16) NOT NULL,
            path VARCHAR(300) NULL,
            q VARCHAR(160) NULL,
            num INT NOT NULL DEFAULT 0,
            num2 INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_created (type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gsc_rows (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(10) NOT NULL,
            label VARCHAR(400) NOT NULL,
            clicks INT NOT NULL DEFAULT 0,
            impressions INT NOT NULL DEFAULT 0,
            ctr DOUBLE NOT NULL DEFAULT 0,
            position DOUBLE NOT NULL DEFAULT 0,
            INDEX idx_kind (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gsc_meta (
            kind VARCHAR(10) PRIMARY KEY,
            range_label VARCHAR(120) NULL,
            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS creators (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(180) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            tagline VARCHAR(300) NULL,
            bio MEDIUMTEXT NULL,
            platforms_json TEXT NULL,
            videos_json TEXT NULL,
            avatar MEDIUMTEXT NULL,
            avatarfit_json VARCHAR(100) NULL,
            twitch_login VARCHAR(100) NULL,
            active TINYINT NOT NULL DEFAULT 1,
            seo_index TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            draft_json MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS creator_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            creator_id INT NOT NULL,
            section VARCHAR(30) NOT NULL,
            entry_slug VARCHAR(180) NOT NULL,
            label VARCHAR(80) NULL,
            quote TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_creator (creator_id),
            INDEX idx_entry (section, entry_slug),
            FOREIGN KEY (creator_id) REFERENCES creators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'slug'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE db_entries ADD COLUMN slug VARCHAR(180) NULL, ADD INDEX idx_section_slug (section, slug)');
            }
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'draft_json'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN draft_json MEDIUMTEXT NULL'); }
            $cols = $pdo->query("SHOW COLUMNS FROM db_entries LIKE 'seo_index'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE db_entries ADD COLUMN seo_index TINYINT NOT NULL DEFAULT 0'); vg_seed_seo_allowlist($pdo); }
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
            $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'tldr_json'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE articles ADD COLUMN tldr_json TEXT NULL'); }
            $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'status'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN status VARCHAR(20) NULL, ADD COLUMN status_note TEXT NULL');
                vg_seed_status_from_cat($pdo);
            }
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
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'spoiler'")->fetchAll();
            if (!$cols) { $pdo->exec('ALTER TABLE comments ADD COLUMN spoiler TINYINT NOT NULL DEFAULT 0'); }
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
