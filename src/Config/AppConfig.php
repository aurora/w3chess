<?php

declare(strict_types=1);

namespace W3Chess\Config;

/**
 * Combines config/config.php (paths & behaviour) and config/defaults.php
 * (theme) into one typed, immutable object.
 */
final readonly class AppConfig
{
    public function __construct(
        public string $dataPath,
        public string $adminPassFile,
        public string $imgUrlPrefix,
        public string $sendmail,
        public string $subject,
        public ?string $noReplyAddress,
        public bool $reverseMoveList,
        public bool $allowRemove,
        public int $deleteAfterDays,
        public bool $adminEnabled,
        public bool $enableList,
        public string $defaultLocale,
        /** @var list<string> */
        public array $availableLocales,
        public ?string $htmlHeaderFile,
        public ?string $htmlFooterFile,
        public ?string $htmlDefpageFile,
        public Theme $theme,
    ) {
    }

    public static function fromFiles(string $configFile, string $defaultsFile): self
    {
        /** @var array<string, mixed> $config */
        $config = require $configFile;
        /** @var array<string, mixed> $defaults */
        $defaults = require $defaultsFile;

        return new self(
            dataPath: $config['data_path'],
            adminPassFile: $config['admin_pass_file'],
            imgUrlPrefix: rtrim((string) $config['img_url_prefix'], '/'),
            sendmail: $config['sendmail'],
            subject: $config['subject'],
            noReplyAddress: $config['no_reply_address'],
            reverseMoveList: $config['reverse_move_list'],
            allowRemove: $config['allow_remove'],
            deleteAfterDays: $config['delete_after_days'],
            adminEnabled: $config['admin_enabled'],
            enableList: $config['enable_list'],
            defaultLocale: $config['default_locale'],
            availableLocales: $config['available_locales'],
            htmlHeaderFile: $config['html_header_file'],
            htmlFooterFile: $config['html_footer_file'],
            htmlDefpageFile: $config['html_defpage_file'],
            theme: Theme::fromArray($defaults),
        );
    }

    /** URL for a theme image, e.g. "/figures/yes.gif". */
    public function imageUrl(string $filename): string
    {
        return $this->imgUrlPrefix.'/'.$filename;
    }
}
