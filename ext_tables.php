<?php

use Faeb\Videoprocessing\Converter;
use Faeb\Videoprocessing\Preset;
use TYPO3\CMS\Core\Http\ApplicationType;


if (!defined('TYPO3')) {
    die('Access denied.');
}


if (TYPO3_MODE === 'BE') {
// if (ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Videoprocessing',
        'system',       // Main area
        'mod1',         // Name of the module
        '',             // Position of the module
        [          // Allowed controller action combinations
            \Faeb\Videoprocessing\Controller\TaskController::class => 'list, delete',
        ],
        [          // Additional configuration
            'access'    => 'user,group',
            'icon'      => 'EXT:recordlist/Resources/Public/Icons/module-list.svg',
            'labels'    => 'LLL:EXT:videoprocessing/Resources/Private/Language/locallang_mod1.xlf',
        ]
    );
}
