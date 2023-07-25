<?php

$GLOBALS['TCA']['sys_file_reference']['columns']['autoplay']['config'] = [
    'type' => 'select',
    'renderType' => 'selectSingle',
    'items' => [
        ['No autoplay', 0],
        ['Autoplay muted', 1],
        ['Autoplay muted, looped', 2],
        ['Autoplay muted, looped, without controls', 3],
        ['[Fäb Video] Autoplay muted, looped, without controls, unmute on hover', 4]
    ],
];
