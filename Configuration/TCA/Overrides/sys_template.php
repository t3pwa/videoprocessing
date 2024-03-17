<?php
defined('TYPO3') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('videoprocessing', 'Configuration/TypoScript', 'Videoprocessing');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'videoprocessing',
    'Configuration/TypoScript/ContentElement',
    'Videoprocessing: Content Elements overwrites'
);
