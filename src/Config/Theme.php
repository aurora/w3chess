<?php

declare(strict_types=1);

namespace W3Chess\Config;

/** Sizes, colors and image filenames from config/defaults.php. */
final readonly class Theme
{
    public function __construct(
        public int $maxString,
        public int $mailLength,
        public int $nickLength,
        public int $adminPassLength,
        /** @var array<string, array{full:string, small:string, small_selected:string}> keyed by piece char */
        public array $pieceImages,
        public string $emptyImage,
        public string $buttonYes,
        public string $buttonNo,
        public string $arrowLeft,
        public string $arrowLeftEnd,
        public string $arrowRight,
        public string $arrowRightEnd,
        public string $swapIconWhiteTop,
        public string $swapIconBlackTop,
        public string $redframeIcon,
        public string $colorBoardBlack,
        public string $colorBoardWhite,
        public string $colorSelected,
        public string $colorWarn,
        public string $colorBoardMarginBg,
        public string $colorBoardMarginFg,
        public string $colorBoardGrid,
        public string $colorMessage,
        public int $fieldSize,
        public int $pieceSize,
        public int $messageBoxLength,
        public int $waitTime,
        public string $title,
        public string $url,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            maxString: $d['max_string'],
            mailLength: $d['mail_length'],
            nickLength: $d['nick_length'],
            adminPassLength: $d['admin_pass_length'],
            pieceImages: $d['piece_images'],
            emptyImage: $d['empty_image'],
            buttonYes: $d['button_yes'],
            buttonNo: $d['button_no'],
            arrowLeft: $d['arrow_left'],
            arrowLeftEnd: $d['arrow_left_end'],
            arrowRight: $d['arrow_right'],
            arrowRightEnd: $d['arrow_right_end'],
            swapIconWhiteTop: $d['swap_icon_white_top'],
            swapIconBlackTop: $d['swap_icon_black_top'],
            redframeIcon: $d['redframe_icon'],
            colorBoardBlack: $d['color_board_black'],
            colorBoardWhite: $d['color_board_white'],
            colorSelected: $d['color_selected'],
            colorWarn: $d['color_warn'],
            colorBoardMarginBg: $d['color_board_margin_bg'],
            colorBoardMarginFg: $d['color_board_margin_fg'],
            colorBoardGrid: $d['color_board_grid'],
            colorMessage: $d['color_message'],
            fieldSize: $d['field_size'],
            pieceSize: $d['piece_size'],
            messageBoxLength: $d['message_box_length'],
            waitTime: $d['wait_time'],
            title: $d['title'],
            url: $d['url'],
        );
    }
}
