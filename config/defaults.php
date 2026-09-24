<?php

declare(strict_types=1);

// Theme: sizes, colors and image filenames (relative to img_url_prefix from
// config/config.php). Returned as a plain array, combined into
// W3Chess\Config\AppConfig by AppConfig::fromFiles().

return [
    'max_string' => 256,
    'mail_length' => 40,
    'nick_length' => 20,
    'admin_pass_length' => 15,

    // Piece images, keyed by the board character used in Board/Move (see
    // W3Chess\Chess\PieceCatalog). "full" = 40px, "small" = 21px,
    // "small_selected" = 21px "stroken" variant shown next to a promotion.
    'piece_images' => [
        'P' => ['full' => 'pld40.gif', 'small' => 'pld21.gif', 'small_selected' => 's_pld21.gif'],
        'B' => ['full' => 'bld40.gif', 'small' => 'bld21.gif', 'small_selected' => 's_bld21.gif'],
        'K' => ['full' => 'kld40.gif', 'small' => 'kld21.gif', 'small_selected' => 's_kld21.gif'],
        'N' => ['full' => 'nld40.gif', 'small' => 'nld21.gif', 'small_selected' => 's_nld21.gif'],
        'Q' => ['full' => 'qld40.gif', 'small' => 'qld21.gif', 'small_selected' => 's_qld21.gif'],
        'R' => ['full' => 'rld40.gif', 'small' => 'rld21.gif', 'small_selected' => 's_rld21.gif'],
        'p' => ['full' => 'pdd40.gif', 'small' => 'pdd21.gif', 'small_selected' => 's_pdd21.gif'],
        'b' => ['full' => 'bdd40.gif', 'small' => 'bdd21.gif', 'small_selected' => 's_bdd21.gif'],
        'k' => ['full' => 'kdd40.gif', 'small' => 'kdd21.gif', 'small_selected' => 's_kdd21.gif'],
        'n' => ['full' => 'ndd40.gif', 'small' => 'ndd21.gif', 'small_selected' => 's_ndd21.gif'],
        'q' => ['full' => 'qdd40.gif', 'small' => 'qdd21.gif', 'small_selected' => 's_qdd21.gif'],
        'r' => ['full' => 'rdd40.gif', 'small' => 'rdd21.gif', 'small_selected' => 's_rdd21.gif'],
    ],
    'empty_image' => 'empty.gif',

    'button_yes' => 'yes.gif',
    'button_no' => 'no.gif',

    'arrow_left' => 'larrow.gif',
    'arrow_left_end' => 'llarrow.gif',
    'arrow_right' => 'rarrow.gif',
    'arrow_right_end' => 'rrarrow.gif',

    'swap_icon_white_top' => 'swap0.gif',
    'swap_icon_black_top' => 'swap1.gif',
    'redframe_icon' => 'redframe.gif',

    'color_board_black' => '#cea86f',
    'color_board_white' => '#ffe2b2',
    'color_selected' => '#24991e',
    'color_warn' => '#FF0000',
    'color_board_margin_bg' => '#9b6411',
    'color_board_margin_fg' => '#f9e5c7',
    'color_board_grid' => '#AAAAAA',
    'color_message' => '#009900',

    'field_size' => 50,
    'piece_size' => 40,
    'message_box_length' => 70,
    'wait_time' => 3,

    'title' => 'W3Chess 0.8.4 - by T.Mueller',
    'url' => 'http://w3chess.sourceforge.net/',
];
