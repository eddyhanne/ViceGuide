<?php
/*
 * Creator-Seiten fuer ViceGuide (Partner-Creator).
 *   /creator/         -> Uebersicht aller aktiven Partner-Creator
 *   /creator/<slug>   -> Profil eines Creators (Avatar, Kanaele, Lieblings-
 *                        Eintraege aus der Datenbank, eingebettete Videos)
 *
 * Bewusst eine eigenstaendige, komplett server-gerenderte Seite (nicht die
 * index.html-SPA-Huelle), damit sie ohne Eingriff ins SPA-Routing auskommt,
 * fokussiert bleibt und fuer den Creator-Namen ranken kann. Optik, Farben und
 * Fonts sind dieselben wie auf der Hauptseite (gleiche Tokens, Light+Dark).
 * Stufe 3 (Twitch-Live-Einbettung) ist nicht gebaut, das Feld twitch_login
 * bleibt im Datenmodell reserviert.
 */

require __DIR__ . '/api/db.php';
[$pdo, $cfg] = vg_db();

// Eigene Aufrufe im eingeloggten Redaktionsmodus nicht mittracken (wie in der
// SPA). vg_is_admin() startet nur eine Session, wenn schon ein Session-Cookie
// da ist, setzt fuer anonyme Besucher also kein Cookie (cookiefreie Linie).
$vgTrack = !vg_is_admin();

const VG_CR_SECMAP = [
    'characters' => ['prefix' => 'charaktere',   'label' => 'Charaktere', 'fav' => 'Lieblingscharakter'],
    'vehicles'   => ['prefix' => 'fahrzeuge',    'label' => 'Fahrzeuge',  'fav' => 'Lieblingsauto'],
    'weapons'    => ['prefix' => 'waffen',       'label' => 'Waffen',     'fav' => 'Lieblingswaffe'],
    'wildlife'   => ['prefix' => 'wildtiere',    'label' => 'Wildtiere',  'fav' => 'Lieblingstier'],
    'gangs'      => ['prefix' => 'gangs',        'label' => 'Gangs',      'fav' => 'Lieblingsgang'],
    'radio'      => ['prefix' => 'radio',        'label' => 'Radio',      'fav' => 'Lieblingssender'],
    'activities' => ['prefix' => 'aktivitaeten', 'label' => 'Aktivitäten','fav' => 'Lieblingsaktivität'],
    'locations'  => ['prefix' => 'orte',         'label' => 'Orte',       'fav' => 'Lieblingsort'],
];

function vg_cr_esc($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/* Loest die Favoriten eines Creators gegen db_entries auf (Name, Unterzeile,
   Bild-URL, Slug), damit die Profilseite echte Kacheln zeigt. */
function vg_cr_favorites(PDO $pdo, int $creatorId): array {
    $q = $pdo->prepare('SELECT section, entry_slug, label, quote FROM creator_favorites WHERE creator_id = ? ORDER BY sort_order, id');
    $q->execute([$creatorId]);
    $rows = $q->fetchAll();
    $e = $pdo->prepare('SELECT id, name, sub, slug, img, updated_at FROM db_entries WHERE section = ? AND slug = ? LIMIT 1');
    $out = [];
    foreach ($rows as $r) {
        if (!isset(VG_CR_SECMAP[$r['section']])) continue;
        $e->execute([$r['section'], $r['entry_slug']]);
        $ent = $e->fetch();
        if (!$ent) continue;
        $out[] = [
            'section' => $r['section'],
            'slug'    => $r['entry_slug'],
            'label'   => $r['label'] ?: VG_CR_SECMAP[$r['section']]['fav'],
            'quote'   => $r['quote'],
            'name'    => $ent['name'],
            'sub'     => $ent['sub'],
            'img'     => !empty($ent['img']) ? ('/api/entry_image.php?id=' . (int)$ent['id'] . '&v=' . urlencode((string)($ent['updated_at'] ?? ''))) : null,
        ];
    }
    return $out;
}

/* Bekannte Plattform-Icons als kleines Inline-SVG (currentColor). Fallback ist
   ein Link-Symbol. */
function vg_cr_icon(string $label): string {
    $k = strtolower($label);
    $svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">';
    if (str_contains($k, 'youtube')) return $svg . '<path d="M23 12s0-3.8-.5-5.6a2.9 2.9 0 0 0-2-2C18.7 4 12 4 12 4s-6.7 0-8.5.5a2.9 2.9 0 0 0-2 2C1 8.2 1 12 1 12s0 3.8.5 5.6a2.9 2.9 0 0 0 2 2C5.3 20 12 20 12 20s6.7 0 8.5-.5a2.9 2.9 0 0 0 2-2C23 15.8 23 12 23 12ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>';
    if (str_contains($k, 'twitch')) return $svg . '<path d="M4 2 3 6v13h4v3h3l3-3h4l5-5V2H4Zm16 9-3 3h-4l-3 3v-3H7V4h13v7Zm-3-5h-2v5h2V6Zm-5 0h-2v5h2V6Z"/></svg>';
    if (str_contains($k, 'tiktok')) return $svg . '<path d="M21 8.3a6.7 6.7 0 0 1-4-1.3v7.2a5.9 5.9 0 1 1-5.9-5.9c.3 0 .6 0 .9.1v3a2.9 2.9 0 1 0 2 2.8V2h2.9a4 4 0 0 0 4 4v2.3Z"/></svg>';
    if (str_contains($k, 'instagram')) return $svg . '<path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.3 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.3-2.2-.4-.6-.2-1-.5-1.4-.9a3.8 3.8 0 0 1-.9-1.4c-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2Zm0 3.2A6.6 6.6 0 1 0 18.6 12 6.6 6.6 0 0 0 12 5.4Zm0 10.9A4.3 4.3 0 1 1 16.3 12 4.3 4.3 0 0 1 12 16.3Zm6.9-11.1a1.5 1.5 0 1 1-1.5-1.5 1.5 1.5 0 0 1 1.5 1.5Z"/></svg>';
    if ($k === 'x' || str_contains($k, 'twitter')) return $svg . '<path d="M18.9 2H22l-7.1 8.1L23 22h-6.6l-5.2-6.8L5.3 22H2.2l7.6-8.7L1.7 2h6.8l4.7 6.2L18.9 2Zm-1.2 18h1.8L7.4 3.9H5.5L17.7 20Z"/></svg>';
    if (str_contains($k, 'discord')) return $svg . '<path d="M20 5.3A17 17 0 0 0 15.9 4l-.3.5a12.6 12.6 0 0 1 3.6 1.8 12 12 0 0 0-10.4 0A12.6 12.6 0 0 1 12.4 4.5L12.1 4A17 17 0 0 0 8 5.3 17.6 17.6 0 0 0 5 17.2a17 17 0 0 0 5.2 2.6l.4-.6a11 11 0 0 1-1.8-.9l.4-.3a8.6 8.6 0 0 0 7.6 0l.4.3a11 11 0 0 1-1.8.9l.4.6a17 17 0 0 0 5.2-2.6A17.6 17.6 0 0 0 20 5.3ZM9.7 14.6c-.8 0-1.5-.8-1.5-1.7s.7-1.7 1.5-1.7 1.5.8 1.5 1.7-.7 1.7-1.5 1.7Zm4.6 0c-.8 0-1.5-.8-1.5-1.7s.7-1.7 1.5-1.7 1.5.8 1.5 1.7-.7 1.7-1.5 1.7Z"/></svg>';
    return $svg . '<path d="M10.6 13.4a1 1 0 0 0 1.4 0l4-4a3 3 0 0 0-4.2-4.2l-1 .9 1.4 1.4 1-.9a1 1 0 0 1 1.4 1.4l-4 4a1 1 0 0 0 0 1.4Zm2.8-2.8a1 1 0 0 0-1.4 0l-4 4a3 3 0 0 0 4.2 4.2l1-.9-1.4-1.4-1 .9a1 1 0 0 1-1.4-1.4l4-4a1 1 0 0 0 0-1.4Z"/></svg>';
}

/* ---- gemeinsame Seiten-Huelle (Head, Header, Footer) ---- */
function vg_cr_head(string $title, string $desc, string $canonical, bool $noindex, string $ogImage, array $jsonLd, string $accent = ''): string {
    $robots = $noindex ? 'noindex, follow' : 'index, follow';
    $ld = '';
    foreach ($jsonLd as $obj) {
        $ld .= '<script type="application/ld+json">' . json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    // Markenfarbe des Creators: faerbt die ganze Profilseite (Links, Badges,
    // Buttons) in seiner Farbe, in Hell und Dunkel. Nur gueltige Hex-Werte.
    $accentStyle = '';
    if ($accent !== '' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
        $accentStyle = '<style>:root{--accent:' . $accent . '}:root[data-theme="light"]{--accent:' . $accent . '}</style>';
    }
    $e = fn($s) => vg_cr_esc($s);
    return '<!doctype html><html lang="de" data-theme="light"><head>'
      . '<meta charset="utf-8">'
      . '<script>(function(){try{var t=localStorage.getItem("vg-theme");if(t==="dark"||t==="light")document.documentElement.setAttribute("data-theme",t);}catch(e){}})();</script>'
      . '<meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>' . $e($title) . '</title>'
      . '<meta name="robots" content="' . $robots . '">'
      . '<meta name="description" content="' . $e($desc) . '">'
      . '<link rel="canonical" href="' . $e($canonical) . '">'
      . '<meta property="og:type" content="profile"><meta property="og:title" content="' . $e($title) . '">'
      . '<meta property="og:description" content="' . $e($desc) . '"><meta property="og:url" content="' . $e($canonical) . '">'
      . '<meta property="og:image" content="' . $e($ogImage) . '">'
      . '<link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">'
      . '<link rel="apple-touch-icon" href="/apple-touch-icon.png">'
      . $ld
      . '<style>' . vg_cr_css() . '</style>' . $accentStyle . '</head><body>'
      . vg_cr_header();
}

function vg_cr_header(): string {
    return '<header class="top"><div class="top-inner">'
      . '<a class="brand" href="/"><img src="/logo.png" alt="ViceGuide"><b>ViceGuide</b><span>viceguide.de</span></a>'
      . '<div class="top-right">'
      . '<button class="theme-toggle" onclick="vgToggleTheme()" aria-label="Hell- oder Dunkelmodus"><span id="tico">☀</span><span id="tlbl">Hell</span></button>'
      . '<a class="top-cta" href="/creator/">Alle Partner</a>'
      . '</div></div></header>';
}

function vg_cr_footer(bool $track = false): string {
    return '<footer><div class="foot-in">'
      . '<div class="foot-brand"><b>ViceGuide</b><a href="/">viceguide.de</a></div>'
      . '<p class="disc"><b>ViceGuide ist ein inoffizielles, von Fans erstelltes Portal und steht in keiner Verbindung zu Rockstar Games oder Take-Two Interactive.</b> Alle Marken, Namen und Bezüge gehören ihren jeweiligen Eigentümern. Partner-Creator sind eigenständige Dritte, ihre Inhalte geben ihre eigene Meinung wieder.</p>'
      . '<div class="foot-links"><a href="/">Zur Startseite</a><a href="/partner">Für Partner</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></div>'
      . '</div></footer>'
      . '<script>'
      . 'function vgToggleTheme(){var h=document.documentElement;var n=h.getAttribute("data-theme")==="dark"?"light":"dark";h.setAttribute("data-theme",n);try{localStorage.setItem("vg-theme",n);}catch(e){}vgSyncTheme();}'
      . 'function vgSyncTheme(){var d=document.documentElement.getAttribute("data-theme")==="dark";document.getElementById("tico").textContent=d?"☾":"☀";document.getElementById("tlbl").textContent=d?"Dunkel":"Hell";}vgSyncTheme();'
      . 'function vgLoadYt(el){if(el.getAttribute("data-demo")==="1"){el.style.opacity=".55";el.style.pointerEvents="none";var n=document.createElement("div");n.className="tw-note";n.style.margin="0";n.textContent="Im Livebetrieb erscheint hier dein Video, DSGVO-konform auf Klick.";var card=el.parentNode;if(card){card.appendChild(n);}return;}var id=el.getAttribute("data-yt");var f=document.createElement("iframe");f.width="560";f.height="315";f.style.cssText="width:100%;aspect-ratio:16/9;height:auto;border:0;border-radius:14px";f.allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture";f.allowFullscreen=true;f.src="https://www.youtube-nocookie.com/embed/"+id+"?autoplay=1";el.replaceWith(f);}'
      . 'function vgCopyShare(btn){var t=(document.getElementById("shareTxt")||{}).textContent||"";function ok(){var o=btn.textContent;btn.textContent="Kopiert ✓";setTimeout(function(){btn.textContent=o;},2000);}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(ok,function(){});}else{try{var ta=document.createElement("textarea");ta.value=t;document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);ok();}catch(e){}}}'
      . 'function vgLoadTwitch(el){var login=el.getAttribute("data-twitch");if(el.getAttribute("data-demo")==="1"){el.style.opacity=".55";el.style.pointerEvents="none";var n=document.createElement("div");n.className="tw-note";n.textContent="Im Livebetrieb läuft hier dein Twitch-Stream. Wir betten ihn erst auf Klick ein, vorher wird keine Verbindung zu Twitch aufgebaut (DSGVO-konform).";el.after(n);return;}var f=document.createElement("iframe");f.style.cssText="width:100%;aspect-ratio:16/9;border:0;border-radius:16px";f.allowFullscreen=true;f.src="https://player.twitch.tv/?channel="+encodeURIComponent(login)+"&parent="+location.hostname+"&autoplay=true";el.replaceWith(f);}'
      . '</script>'
      // Cookiefreier Analytics-Treffer fuer Creator-Seiten (siehe api/hits.php),
      // damit im Dashboard sichtbar wird, ob ein Creator-Link geoeffnet wurde.
      // Absolute URL, weil diese Seite unter /creator/<slug> laeuft.
      . ($track ? '<script>(function(){try{var p=new URLSearchParams(location.search);var b=JSON.stringify({path:location.pathname,referrer:document.referrer||"",utm_source:p.get("utm_source")||"",utm_medium:p.get("utm_medium")||"",utm_campaign:p.get("utm_campaign")||""});if(navigator.sendBeacon){navigator.sendBeacon("/api/hits.php",new Blob([b],{type:"application/json"}));}else{fetch("/api/hits.php",{method:"POST",headers:{"Content-Type":"application/json"},body:b,keepalive:true}).catch(function(){});}}catch(e){}})();</script>' : '')
      . '</body></html>';
}

function vg_cr_css(): string {
    return <<<CSS
@font-face{font-family:'Oswald';font-weight:500 700;font-display:swap;src:url('/assets/fonts/oswald-variable.woff2') format('woff2')}
@font-face{font-family:'Inter';font-weight:300 700;font-display:swap;src:url('/assets/fonts/inter-variable.woff2') format('woff2')}
@font-face{font-family:'Space Mono';font-weight:400;font-display:swap;src:url('/assets/fonts/spacemono-400.woff2') format('woff2')}
@font-face{font-family:'Space Mono';font-weight:700;font-display:swap;src:url('/assets/fonts/spacemono-700.woff2') format('woff2')}
:root{--bg:#0D0A1A;--bg-2:#151027;--surface:#1B1436;--surface-2:#241a45;--text:#ECE6F7;--text-soft:#A99CC4;--heading:#F7E7C4;--accent:#88B8C5;--line:rgba(255,255,255,.10);--nav-bg:rgba(13,10,26,.85);--shadow:0 22px 50px -22px rgba(0,0,0,.7);--btn-bg:#88B8C5;--btn-text:#1a0f28;--pill-bg:rgba(136,184,197,.05);--pill-ln:rgba(136,184,197,.24);--logo-shadow:drop-shadow(0 14px 30px rgba(0,0,0,.55))}
:root[data-theme="light"]{--bg:#FBF3E7;--bg-2:#F4E8D6;--surface:#FFFDFB;--surface-2:#FFF6EA;--text:#221041;--text-soft:#6B5E85;--heading:#221041;--accent:#D00059;--line:rgba(34,16,65,.10);--nav-bg:rgba(251,243,231,.9);--shadow:0 20px 44px -22px rgba(34,16,65,.4);--btn-bg:#221041;--btn-text:#FBF3E7;--pill-bg:rgba(208,0,89,.035);--pill-ln:rgba(208,0,89,.22);--logo-shadow:drop-shadow(0 2px 1px rgba(28,10,48,.95)) drop-shadow(0 12px 22px rgba(28,10,48,.45))}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;color:var(--text);background:var(--bg);line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:inherit}img{max-width:100%}
header.top{position:sticky;top:0;z-index:60;background:var(--nav-bg);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
.top-inner{display:flex;align-items:center;gap:14px;height:60px;padding:0 20px;max-width:1000px;margin:0 auto}
.brand{display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--text)}
.brand img{height:38px;width:auto;display:block;filter:drop-shadow(0 2px 5px rgba(0,0,0,.4))}
.brand b{font-family:'Oswald';font-weight:700;font-size:18px;letter-spacing:.3px}
.brand span{font-family:'Space Mono';font-size:11px;color:var(--text-soft);letter-spacing:.5px}
.top-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.top-cta{font-family:'Inter';font-size:13px;font-weight:600;text-decoration:none;background:var(--btn-bg);color:var(--btn-text);padding:9px 15px;border-radius:999px;white-space:nowrap}
.theme-toggle{height:38px;padding:0 13px;border-radius:999px;border:1.5px solid var(--line);display:inline-flex;align-items:center;gap:7px;font-family:'Inter';font-size:13px;font-weight:600;color:var(--text);background:none;cursor:pointer}
.theme-toggle:hover{border-color:var(--accent);color:var(--accent)}
.theme-toggle #tico{font-size:15px;line-height:1}
@media(max-width:560px){.brand span{display:none}.theme-toggle{padding:0 10px}.theme-toggle #tlbl{display:none}}
.wrap{max-width:1000px;margin:0 auto;padding:0 20px 10px}
.eyebrow{font-family:'Space Mono';font-weight:700;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:var(--accent)}
/* Profil-Hero */
.cstate{margin:18px 0 0;padding:11px 16px;border-radius:12px;background:var(--pill-bg);border:1px solid var(--pill-ln);color:var(--text-soft);font-size:13.5px;text-align:center}
.cbadge{align-self:flex-start;display:inline-flex;align-items:center;gap:6px;margin:0 0 2px;font-family:'Space Mono';font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#fff;background:rgba(0,0,0,.34);border:1px solid rgba(255,255,255,.45);border-radius:999px;padding:4px 11px;backdrop-filter:blur(4px)}
@media(max-width:560px){.cbadge{align-self:center}}
.cbadge svg{color:#fff}
.sharebox{display:flex;gap:10px;align-items:stretch;flex-wrap:wrap}
.sharebox code{flex:1;min-width:0;font-family:'Space Mono';font-size:12.5px;color:var(--text);background:var(--surface);border:1.5px solid var(--line);border-radius:12px;padding:13px 15px;line-height:1.5;word-break:break-word}
.sharebox button{flex:0 0 auto;font-family:'Inter';font-weight:700;font-size:14px;color:var(--btn-text);background:var(--btn-bg);border:0;border-radius:12px;padding:0 20px;cursor:pointer}
.sharebox button:hover{opacity:.92}
.chero{position:relative;overflow:hidden;background:#2a1a5e url('/palms-hero.webp') center 32%/cover;border-radius:18px;margin:16px 0 0;padding:22px}
.chero .veil{position:absolute;inset:0;background:linear-gradient(120deg,rgba(12,5,32,.74) 0%,rgba(12,5,32,.5) 60%,rgba(12,5,32,.34) 100%)}
.chero-in{position:relative;z-index:2;display:flex;align-items:center;gap:18px}
.cmeta{min-width:0;display:flex;flex-direction:column;gap:4px}
.cavatar{width:88px;height:88px;border-radius:999px;object-fit:cover;display:block;flex:0 0 auto;border:3px solid rgba(255,255,255,.85);box-shadow:0 8px 22px rgba(0,0,0,.5)}
.cavatar.ph{display:flex;align-items:center;justify-content:center;font-family:'Oswald';font-weight:700;font-size:34px;color:#fff;background:linear-gradient(135deg,#7a3cff,#d0367f)}
.chero h1{font-family:'Oswald';font-weight:700;font-size:clamp(24px,3.6vw,34px);color:#fff;line-height:1.06;letter-spacing:.4px;text-shadow:0 3px 18px rgba(0,0,0,.55)}
.ctag{color:rgba(255,255,255,.92);font-weight:500;font-size:14.5px;text-shadow:0 2px 12px rgba(0,0,0,.55)}
.cplat{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-start;margin-top:6px}
.cplat a{display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-weight:600;font-size:13px;color:#fff;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);border-radius:999px;padding:6px 12px;backdrop-filter:blur(4px);transition:background .15s}
.cplat a:hover{background:rgba(255,255,255,.28)}
@media(max-width:560px){.chero-in{flex-direction:column;text-align:center;gap:12px}.cplat{justify-content:center}.cmeta{align-items:center}}
.sec{margin:26px 0 0}
.sec-h{font-family:'Oswald';font-weight:700;font-size:clamp(18px,2.6vw,22px);color:var(--heading);letter-spacing:.3px;margin:0 0 4px}
.sec-lead{color:var(--text-soft);font-size:14.5px;margin:0 0 16px}
/* Zweispaltiger Hub */
.cgrid{display:grid;grid-template-columns:1fr;gap:20px;margin-top:20px}
@media(min-width:860px){.cgrid{grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);align-items:start}}
.cmain{min-width:0}
.cside{min-width:0;display:flex;flex-direction:column;gap:20px}
.sbox-h{font-family:'Oswald';font-weight:700;font-size:17px;color:var(--heading);letter-spacing:.3px;margin:0 0 10px}
.mini-h{font-family:'Space Mono';font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-soft);margin:18px 0 0}
.cvids{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
@media(max-width:480px){.cvids{grid-template-columns:1fr}}
.favlist{display:flex;flex-direction:column;gap:9px}
.favitem{display:flex;gap:10px;align-items:flex-start;background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:9px;text-decoration:none;color:inherit;transition:border-color .15s,transform .15s}
.favitem:hover{border-color:var(--accent);transform:translateY(-1px)}
.favitem-th{flex:0 0 56px;width:56px;height:42px;border-radius:8px;overflow:hidden;background:var(--surface-2);display:grid;place-items:center;color:var(--accent);font-family:'Oswald';font-size:18px}
.favitem-th img{width:100%;height:100%;object-fit:cover;display:block}
.favitem-b{min-width:0;display:flex;flex-direction:column;gap:1px}
.favitem .lbl{font-family:'Space Mono';font-size:9px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--accent)}
.favitem .nm{font-family:'Oswald';font-weight:600;font-size:14.5px;color:var(--heading);line-height:1.15}
.favitem .q{font-size:11.5px;font-style:italic;color:var(--text-soft);line-height:1.4;margin-top:2px}
.cside .sharebox{flex-direction:column}
.cside .sharebox code{flex:1 1 auto}
.cside .sharebox button{padding:11px}
.cbio{color:var(--text);font-size:16px;line-height:1.7;max-width:760px}
.cbio p{margin:0 0 12px}
/* Favoriten-Grid */
.favgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:11px}
@media(max-width:800px){.favgrid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:520px){.favgrid{grid-template-columns:1fr 1fr}}
.favcard{display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;transition:border-color .15s,transform .15s}
.favcard:hover{border-color:var(--accent);transform:translateY(-2px)}
.fav-th{aspect-ratio:16/10;background:var(--surface-2);position:relative;overflow:hidden}
.fav-th img{width:100%;height:100%;object-fit:cover;display:block}
.fav-th.ph{display:grid;place-items:center;color:var(--accent);font-family:'Oswald';font-size:30px;opacity:.6}
.fav-lbl{position:absolute;top:9px;left:9px;font-family:'Space Mono';font-size:9.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#fff;background:rgba(0,0,0,.55);padding:4px 9px;border-radius:999px}
.fav-b{padding:10px 12px 12px}
.fav-n{font-family:'Oswald';font-weight:600;font-size:15.5px;color:var(--heading);line-height:1.15}
.fav-s{font-family:'Space Mono';font-size:10.5px;color:var(--text-soft);margin-top:2px}
.fav-q{margin-top:8px;font-size:12.5px;font-style:italic;color:var(--text-soft);line-height:1.45;border-left:2px solid var(--accent);padding-left:9px}
.fav-lbl{font-size:9px}
/* Videos */
.vidgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
@media(max-width:820px){.vidgrid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.vidgrid{grid-template-columns:1fr}}
.vid-t{font-size:13.5px;padding:10px 13px}
.vidcard{background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden}
.ytph{position:relative;aspect-ratio:16/9;background:#2a1a5e url('/palms-hero.webp') center/cover;cursor:pointer;display:grid;place-items:center;border:0;width:100%}
.ytph::after{content:"";position:absolute;inset:0;background:rgba(12,5,32,.5)}
.ytplay{position:relative;z-index:2;width:58px;height:58px;border-radius:999px;background:rgba(255,255,255,.92);display:grid;place-items:center}
.ytplay svg{width:24px;height:24px;fill:#221041;margin-left:3px}
.vid-t{padding:12px 15px;font-weight:600;font-size:14.5px;color:var(--text)}
.vid-note{padding:0 15px 13px;font-size:11.5px;color:var(--text-soft)}
/* Live-Stream-Modul */
.twph{position:relative;display:block;width:100%;max-width:620px;aspect-ratio:16/9;border:0;border-radius:14px;overflow:hidden;cursor:pointer;background:#2a1a5e url('/palms-hero.webp') center/cover}
.tw-veil{position:absolute;inset:0;background:linear-gradient(180deg,rgba(12,5,32,.45),rgba(12,5,32,.72))}
.tw-live{position:absolute;top:14px;left:14px;z-index:2;display:inline-flex;align-items:center;gap:6px;font-family:'Space Mono';font-size:12px;font-weight:700;letter-spacing:1px;color:#fff;background:#e23636;padding:5px 11px;border-radius:999px;box-shadow:0 4px 14px rgba(226,54,54,.5)}
.tw-badge{position:absolute;top:14px;left:14px;z-index:2;display:inline-flex;align-items:center;gap:6px;font-family:'Space Mono';font-size:12px;font-weight:700;letter-spacing:1px;color:#fff;background:#9147ff;padding:5px 11px;border-radius:999px}
.tw-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:2;width:74px;height:74px;border-radius:999px;background:rgba(145,71,255,.95);display:grid;place-items:center;box-shadow:0 10px 30px rgba(0,0,0,.5);transition:transform .15s}
.twph:hover .tw-play{transform:translate(-50%,-50%) scale(1.08)}
.tw-play svg{width:32px;height:32px;fill:#fff;margin-left:4px}
.tw-name{position:absolute;bottom:14px;left:16px;z-index:2;font-family:'Space Mono';font-size:13px;color:#fff;text-shadow:0 1px 8px rgba(0,0,0,.7)}
.tw-note{margin-top:12px;padding:14px 16px;background:var(--surface-2);border:1px solid var(--line);border-radius:12px;font-size:13.5px;color:var(--text-soft);line-height:1.55}
/* Zurueck-Link */
.cback{display:inline-flex;align-items:center;gap:6px;margin:20px 0 0;font-size:14px;font-weight:600;color:var(--accent);text-decoration:none}
/* Uebersicht */
.ovhero{text-align:center;padding:34px 20px 8px}
.ovhero h1{font-family:'Oswald';font-weight:700;font-size:clamp(26px,4.5vw,38px);color:var(--heading);letter-spacing:.4px;margin:6px 0 6px}
.ovhero p{color:var(--text-soft);max-width:620px;margin:0 auto;font-size:15.5px}
.ovgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:24px}
@media(max-width:800px){.ovgrid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.ovgrid{grid-template-columns:1fr}}
.ovcard{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:24px 18px 22px;text-decoration:none;color:inherit;transition:border-color .15s,transform .15s}
.ovcard:hover{border-color:var(--accent);transform:translateY(-3px)}
.ovav{width:78px;height:78px;border-radius:999px;object-fit:cover;margin-bottom:8px}
.ovav.ph{display:flex;align-items:center;justify-content:center;font-family:'Oswald';font-weight:700;font-size:30px;color:#fff;background:linear-gradient(135deg,#7a3cff,#d0367f)}
.ovcard b{font-family:'Oswald';font-weight:600;font-size:20px;color:var(--heading)}
.ovcard span{font-size:13px;color:var(--text-soft)}
.ovempty{text-align:center;color:var(--text-soft);padding:40px 20px;font-size:15px}
footer{margin-top:46px;border-top:1px solid var(--line);background:var(--bg-2)}
.foot-in{max-width:1000px;margin:0 auto;padding:26px 20px 34px}
.foot-brand{display:flex;align-items:baseline;gap:10px;margin:0 0 10px}
.foot-brand b{font-family:'Oswald';font-weight:700;font-size:17px;color:var(--heading)}
.foot-brand a{font-family:'Space Mono';font-size:12px;color:var(--accent);text-decoration:none}
.disc{font-size:12.5px;color:var(--text-soft);line-height:1.6;max-width:820px}
.foot-links{margin-top:14px;font-size:12.5px}
.foot-links a{color:var(--text-soft);text-decoration:none;margin-right:16px}
.foot-links a:hover{color:var(--accent)}
CSS;
}

/* ---------- Routing ---------- */
$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');

if ($slug === '') {
    /* ===== Uebersicht /creator/ ===== */
    // Nur oeffentliche Partner (seo=1) werden gelistet. seo=0 ist der Vorschau-/
    // Demo-Zustand: per Link erreichbar, aber nicht in der Uebersicht und noindex.
    $rows = $pdo->query('SELECT * FROM creators WHERE active = 1 AND seo_index = 1 ORDER BY sort_order, id')->fetchAll();
    $indexable = count($rows) > 0;
    $canonical = 'https://viceguide.de/creator/';
    $title = 'Partner-Creator - ViceGuide';
    $desc = 'Die Partner-Creator der deutschen GTA-6-Zentrale ViceGuide: ihre Profile, Kanäle und Lieblings-Einträge aus der Datenbank.';
    $items = [];
    $i = 1;
    foreach ($rows as $r) {
        $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $r['name'], 'item' => 'https://viceguide.de/creator/' . $r['slug']];
    }
    $ld = [[
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'name' => $title, 'description' => $desc, 'url' => $canonical,
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $items],
    ]];
    echo vg_cr_head($title, $desc, $canonical, !$indexable, 'https://viceguide.de/og-image.jpg', $ld);
    echo '<div class="wrap"><div class="ovhero"><p class="eyebrow">Partner</p><h1>Unsere Partner-Creator</h1>'
       . '<p>Creator, die mit ViceGuide zusammenarbeiten. Jede Seite zeigt Profil, Kanäle und die Lieblings-Einträge aus unserer GTA-6-Datenbank.</p></div>';
    if (!$rows) {
        echo '<div class="ovempty">Hier stellen sich bald unsere Partner-Creator vor.</div>';
    } else {
        echo '<div class="ovgrid">';
        foreach ($rows as $r) {
            $av = !empty($r['avatar']) ? '<img class="ovav" src="/api/creator_image.php?id=' . (int)$r['id'] . '&v=' . urlencode((string)($r['updated_at'] ?? '')) . '" alt="' . vg_cr_esc($r['name']) . '">'
                : '<div class="ovav ph">' . vg_cr_esc(mb_substr($r['name'], 0, 1)) . '</div>';
            echo '<a class="ovcard" href="/creator/' . vg_cr_esc($r['slug']) . '">' . $av
               . '<b>' . vg_cr_esc($r['name']) . '</b><span>' . vg_cr_esc($r['tagline'] ?? '') . '</span></a>';
        }
        echo '</div>';
    }
    echo '</div>' . vg_cr_footer($vgTrack);
    exit;
}

/* ===== Einzelseite /creator/<slug> ===== */
$stmt = $pdo->prepare('SELECT * FROM creators WHERE slug = ?');
$stmt->execute([$slug]);
$c = $stmt->fetch();

if (!$c || empty($c['active'])) {
    http_response_code(404);
    $ld = [];
    echo vg_cr_head('Creator nicht gefunden - ViceGuide', 'Diese Creator-Seite gibt es nicht (mehr).', 'https://viceguide.de/creator/', true, 'https://viceguide.de/og-image.jpg', $ld);
    echo '<div class="wrap"><div class="ovhero"><h1>Creator nicht gefunden</h1>'
       . '<p>Diese Seite gibt es nicht oder nicht mehr. Schau dir alle Partner an.</p></div>'
       . '<p style="text-align:center;margin-top:20px"><a class="top-cta" href="/creator/">Alle Partner-Creator</a></p></div>' . vg_cr_footer();
    exit;
}

$cid = (int)$c['id'];
$name = $c['name'];
$tagline = trim($c['tagline'] ?? '');
$bio = trim($c['bio'] ?? '');
$platforms = $c['platforms_json'] ? (json_decode($c['platforms_json'], true) ?: []) : [];
$videos = $c['videos_json'] ? (json_decode($c['videos_json'], true) ?: []) : [];
$favs = vg_cr_favorites($pdo, $cid);
$hasAvatar = !empty($c['avatar']);
$avatarUrl = $hasAvatar ? ('/api/creator_image.php?id=' . $cid . '&v=' . urlencode((string)($c['updated_at'] ?? ''))) : null;
$ogImage = $hasAvatar ? ('https://viceguide.de/api/creator_image.php?id=' . $cid) : 'https://viceguide.de/og-image.jpg';
$canonical = 'https://viceguide.de/creator/' . $c['slug'];
$isDemo = empty($c['seo_index']);   // die Beispiel-/Vorschauseite wird nicht indexiert
$accent = trim((string)($c['accent'] ?? ''));
$twitch = trim((string)($c['twitch_login'] ?? ''));
// seo=0 heisst Demo oder private Vorschau: alles wird illustrativ gezeigt (kein
// echter Stream, keine echten fremden Videos), damit der Creator nur SIEHT, wie
// seine Seite aussehen wird. Erst die oeffentliche Live-Seite (seo=1) bettet
// echte Inhalte ein.
$preview = $isDemo;

$title = $name . ($tagline !== '' ? ' - ' . $tagline : ' - Partner-Creator') ;
if (mb_strlen($title . ' - ViceGuide') <= 60) $title .= ' - ViceGuide';
$desc = $bio !== '' ? mb_substr(preg_replace('/\s+/', ' ', $bio), 0, 155) : ($name . ', Partner-Creator bei ViceGuide.');

// JSON-LD: ProfilePage mit eingebetteter Person, Kanaele als sameAs.
$sameAs = [];
foreach ($platforms as $p) { if (!empty($p['url'])) $sameAs[] = $p['url']; }
$ld = [[
    '@context' => 'https://schema.org', '@type' => 'ProfilePage',
    'url' => $canonical,
    'mainEntity' => array_filter([
        '@type' => 'Person',
        'name' => $name,
        'description' => $tagline !== '' ? $tagline : null,
        'image' => $hasAvatar ? $ogImage : null,
        'url' => $canonical,
        'sameAs' => $sameAs ?: null,
    ]),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => 'https://viceguide.de/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Partner-Creator', 'item' => 'https://viceguide.de/creator/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $canonical],
    ],
]];

echo vg_cr_head($title, $desc, $canonical, $isDemo, $ogImage, $ld, $accent);
echo '<div class="wrap">';

// Zustands-Banner: Demo bzw. private Vorschau (seo=0). Oeffentliche Partner
// (seo=1) bekommen keinen Banner.
if ($isDemo) {
    $stateTxt = $c['slug'] === 'beispiel'
        ? 'Beispiel-Profil. So sieht eine Creator-Seite bei ViceGuide aus.'
        : 'Deine Vorschau. Diese Seite ist noch nicht öffentlich, nur über diesen Link sichtbar.';
    echo '<div class="cstate">' . $stateTxt . '</div>';
}

// Hero (kompaktes horizontales Band). Eigener Cover-Hintergrund, falls gesetzt,
// sonst die Palmen-Optik.
$coverUrl = !empty($c['cover']) ? ('/api/creator_image.php?id=' . $cid . '&field=cover&v=' . urlencode((string)($c['updated_at'] ?? ''))) : '';
$heroStyle = $coverUrl ? (' style="background-image:url(\'' . vg_cr_esc($coverUrl) . '\')"') : '';
echo '<section class="chero"' . $heroStyle . '><div class="veil"></div><div class="chero-in">';
echo $hasAvatar
    ? '<img class="cavatar" src="' . vg_cr_esc($avatarUrl) . '" alt="' . vg_cr_esc($name) . '">'
    : '<div class="cavatar ph">' . vg_cr_esc(mb_substr($name, 0, 1)) . '</div>';
echo '<div class="cmeta">';
echo '<div class="cbadge"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 1.6l2.4 1.7 2.9-.2 1 2.8 2.4 1.7-.9 2.8.9 2.8-2.4 1.7-1 2.8-2.9-.2L12 22.4l-2.4-1.7-2.9.2-1-2.8-2.4-1.7.9-2.8-.9-2.8 2.4-1.7 1-2.8 2.9.2L12 1.6zm-1.2 13.9l5-5-1.4-1.4-3.6 3.6-1.8-1.8-1.4 1.4 3.2 3.2z"/></svg>ViceGuide-Partner</div>';
echo '<h1>' . vg_cr_esc($name) . '</h1>';
if ($tagline !== '') echo '<div class="ctag">' . vg_cr_esc($tagline) . '</div>';
if ($platforms) {
    echo '<div class="cplat">';
    foreach ($platforms as $p) {
        if (empty($p['url'])) continue;
        $lbl = $p['label'] ?? 'Link';
        echo '<a href="' . vg_cr_esc($p['url']) . '" target="_blank" rel="noopener nofollow">' . vg_cr_icon($lbl) . '<span>' . vg_cr_esc($lbl) . '</span></a>';
    }
    echo '</div>';
}
echo '</div>';
echo '</div></section>';

// Zweispaltiger Hub: links Medien (Live & Videos), rechts Bio, Lieblinge, Teilen.
// Mobil stapelt es untereinander, Medien zuerst.
$showStream = $preview || $twitch !== '';
$hasVideos  = $preview || !empty($videos);

echo '<div class="cgrid">';

// ---- MAIN: Live & Videos ----
echo '<div class="cmain">';
if ($showStream || $hasVideos) {
    echo '<section class="sec"><h2 class="sec-h">Live &amp; Videos</h2>';
    if ($showStream) {
        $handle = $twitch !== '' ? $twitch : ($c['slug'] ?: 'deinkanal');
        $liveTag = $preview ? '<span class="tw-live">● LIVE</span>' : '<span class="tw-badge">Twitch</span>';
        echo '<button class="twph" data-twitch="' . vg_cr_esc($handle) . '"' . ($preview ? ' data-demo="1"' : '') . ' onclick="vgLoadTwitch(this)" aria-label="Stream laden">'
           . '<span class="tw-veil"></span>' . $liveTag
           . '<span class="tw-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>'
           . '<span class="tw-name">twitch.tv/' . vg_cr_esc($handle) . '</span></button>';
        echo '<p class="sec-lead" style="margin-top:8px">' . ($preview
            ? 'Auf der echten Seite läuft hier dein Twitch-Stream, DSGVO-konform erst auf Klick.'
            : 'Lädt erst auf Klick, vorher keine Verbindung zu Twitch.') . '</p>';
    }
    if ($hasVideos) {
        echo '<div class="mini-h">Neueste Videos</div><div class="cvids">';
        if ($preview) {
            foreach (['Dein neuestes Video', 'Dein GTA-6-Content'] as $t) {
                echo '<div class="vidcard"><button class="ytph" data-demo="1" onclick="vgLoadYt(this)" aria-label="Beispiel-Video"><span class="ytplay"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></button>'
                   . '<div class="vid-t">' . vg_cr_esc($t) . '</div>'
                   . '<div class="vid-note">Beispiel. Hier steht dein echtes Video.</div></div>';
            }
        } else {
            foreach ($videos as $v) {
                $vid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($v['id'] ?? ''));
                if ($vid === '') continue;
                echo '<div class="vidcard"><button class="ytph" data-yt="' . vg_cr_esc($vid) . '" onclick="vgLoadYt(this)" aria-label="Video abspielen"><span class="ytplay"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></button>'
                   . '<div class="vid-t">' . vg_cr_esc($v['title'] ?? 'Video') . '</div></div>';
            }
        }
        echo '</div>';
    }
    echo '</section>';
}
echo '</div>';

// ---- SIDE: Bio, Lieblinge, Teilen ----
echo '<aside class="cside">';
if ($bio !== '') {
    echo '<section class="sbox"><h2 class="sbox-h">Über ' . vg_cr_esc($name) . '</h2><div class="cbio">';
    foreach (preg_split('/\r?\n+/', $bio) as $para) {
        $para = trim($para);
        if ($para !== '') echo '<p>' . vg_cr_esc($para) . '</p>';
    }
    echo '</div></section>';
}
if ($favs) {
    echo '<section class="sbox"><h2 class="sbox-h">Lieblinge aus der Datenbank</h2><div class="favlist">';
    foreach ($favs as $f) {
        $href = '/' . VG_CR_SECMAP[$f['section']]['prefix'] . '/' . vg_cr_esc($f['slug']);
        echo '<a class="favitem" href="' . $href . '">';
        if ($f['img']) echo '<span class="favitem-th"><img src="' . vg_cr_esc($f['img']) . '" alt="' . vg_cr_esc($f['name']) . '"></span>';
        else echo '<span class="favitem-th">' . vg_cr_esc(mb_substr($f['name'], 0, 1)) . '</span>';
        echo '<span class="favitem-b"><span class="lbl">' . vg_cr_esc($f['label']) . '</span><span class="nm">' . vg_cr_esc($f['name']) . '</span>';
        if ($f['quote']) echo '<span class="q">' . vg_cr_esc($f['quote']) . '</span>';
        echo '</span></a>';
    }
    echo '</div></section>';
}
// Für-deine-Videobeschreibung: kurzer, messbarer Link (utm ueber /c/<slug>).
$shareUrl = 'https://viceguide.de/c/' . rawurlencode($c['slug']);
$shareTxt = '🎮 Deutsche GTA-6-Zentrale: Datenbank, News und Guides auf Deutsch. ' . $shareUrl;
echo '<section class="sbox"><h2 class="sbox-h">Für deine Videobeschreibung</h2>'
   . '<p class="sec-lead" style="margin-bottom:10px">Deine Community bekommt die deutsche GTA-6-Zentrale, und wir sehen, dass die Besucher von dir kommen.</p>'
   . '<div class="sharebox"><code id="shareTxt">' . vg_cr_esc($shareTxt) . '</code>'
   . '<button type="button" onclick="vgCopyShare(this)">Kopieren</button></div></section>';
echo '</aside>';

echo '</div>'; // cgrid
echo '<a class="cback" href="/creator/">← Alle Partner-Creator</a>';
echo '</div>' . vg_cr_footer($vgTrack);
