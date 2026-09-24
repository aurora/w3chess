<?php

declare(strict_types=1);

// Paths and runtime behaviour. Returns a plain array consumed by
// W3Chess\Config\AppConfig::fromFiles(). Keep in sync with config/defaults.php
// (theme) and config/lang/*.php (translations).

$root = dirname(__DIR__);

return [
    // Absolute path to the directory holding one file per game (see
    // W3Chess\Game\GameRepository for the on-disk format).
    'data_path' => $root.'/games',

    // Absolute path to the admin password file.
    'admin_pass_file' => $root.'/games/adm.pss',

    // URL prefix the browser uses to reach public/figures/.
    'img_url_prefix' => '/figures',

    // Path to the sendmail-compatible binary used to deliver mail.
    'sendmail' => '/usr/sbin/sendmail',

    // Subject prefix for all outgoing mail.
    'subject' => '[W3Chess]',

    // From-address used only when sending the "all games" summary mail, to
    // avoid identical From/To headers. Null keeps the requester's address.
    'no_reply_address' => null,

    // Show the newest move first in move lists.
    'reverse_move_list' => true,

    // Allow admins to delete games from the admin game list.
    'allow_remove' => true,

    // Auto-delete games untouched for this many days.
    'delete_after_days' => 30,

    // Enable the admin area (ACTION=ADMIN).
    'admin_enabled' => true,

    // Enable the public "list all games" action (ACTION=LIST).
    'enable_list' => false,

    // Optional: absolute paths to files whose raw content replaces the
    // built-in page header/footer/default-page HTML, if the files exist.
    'html_header_file' => null,
    'html_footer_file' => null,
    'html_defpage_file' => null,

    // Default language, used when no translation matches the browser's
    // Accept-Language header. Must match a file in config/lang/.
    'default_locale' => 'en',
    'available_locales' => ['en', 'de', 'es', 'it', 'pt_br'],
];
