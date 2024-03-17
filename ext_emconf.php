<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Videoprocessing',
    'description' => '',
    'category' => 'plugin',
    'author' => '',
    'author_email' => '',
    'state' => 'alpha',
    'version' => '1.0.0',
    'description' => 'Automatic conversion of videos within typo3 using local ffmpeg (or cloudconvert). This is a fork of hn/video',
    'category' => 'fe',
    'author' => 'A. Reinhard',
    'author_email' => 'alex@fabbing.com',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'alpha',
    'clearCacheOnLoad' => true,
    'autoload' => [
        'psr-4' => [
            'Faeb\\Videoprocessing\\' => 'Classes',
        ],
    ]
];
