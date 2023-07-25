<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
// TODO move that into pagets
ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    '[Fäb] video testing utility', 'video'
]);

ExtensionManagementUtility::addPiFlexFormValue('', 'FILE:EXT:videoprocessing/Configuration/FlexForm/TestElement.xml', 'video');

// use default config
// ExtensionManagementUtility::addPiFlexFormValue('', 'FILE:EXT:videoprocessing/Configuration/FlexForm/TestElement.xml', 'textmedia');


$GLOBALS['TCA']['tt_content']['types']['video'] = [
    'showitem' => implode(',', [
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general',
        '--palette--;;general',
        'header, pi_flexform, media',

        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access',
        '--palette--;;hidden',
        '--palette--;LLL:EXT:frontend/Resorces/Private/Language/locallang_ttc.xlf:palette.access;access'
    ])
];

/*
$GLOBALS['TCA']['tt_content']['types']['textmedia'] = [
    'showitem' => implode(',', [
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general',
        '--palette--;;general',
        'header, pi_flexform,media',

        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access',
        '--palette--;;hidden',
        '--palette--;LLL:EXT:frontend/Resorces/Private/Language/locallang_ttc.xlf:palette.access;access'
    ])
];
*/

$GLOBALS['TCA']['sys_file_reference']['columns']['autoplay']['config'] = [
    'type' => 'select',
    'renderType' => 'selectSingle',
    'items' => [
        ['No autoplay', 0],
        ['Autoplay muted', 1],
        ['Autoplay muted, looped', 2],
        ['Autoplay muted, looped, without controls', 3],
        ['[Fäb Video 2] Autoplay muted, looped, without controls, unmute on hover', 4]
    ],
];
