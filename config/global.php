<?php

return [
    'site_name' => 'Silkroad Online',
    'site_desc' => "Silkroad Online is a World's first blockbuster Free to play MMORPG. Silkroad Online puts players deep into ancient Chinese, Islamic, and European civilization. Enjoy Silkroad's hardcore PvP, personal dungeon system, never ending fortress war and be the top of the highest heroes!",
    'site_url' => 'https://localhost',
    'site_favicon' => 'images/favicon.ico',
    'site_logo' => 'images/logo.png',
    'site_background' => 'images/bg.jpg',
    'dark_mode' => 'switch',
    'default_locale' => 'switch',
    'locale' => 'en',
    'theme' => 'default',
    'timezone' => 'Africa/Cairo',
    'max_level' => 140,
    'max_player' => 3500,
    'fake_player' => 0,
    'account_verify' => 0,
    'disable_register' => 0,
    'register_confirm' => 0,
    'duplicate_email' => 0,
    'agree_terms' => 0,

    'server' => [
        'version' => env('SRO_VERSION', 'iSRO'), // or 'vSRO'
        'guard' => 'isro', // or 'MaxiGuard', 'vPlus', 'Vanguard', 'None'
        'saltKey' => env('SRO_SALT_KEY', ''),
        // 'WebMallPass' => 'ISRO-R Development',
        // 'WebMallAddr' => "http://webmall.luxor-online.com/gateway.asp"
    ],
    'cache' => [
        'account_info' => 60, // 1 minutes
        'event_schedule' => 604800, // 1 week
        'fortress_history' => 604800, // 1 week
        'unique_history' => 600, // 10 minutes
        'globals_history' => 600, // 10 minutes
        'character_info' => 86400, // 1 day
        'guild_info' => 86400, // 1 day
        'ranking_player' => 3600, // 1 hour
        'ranking_guild' => 3600, // 1 hour
        'ranking_unique' => 3600, // 1 hour
        'ranking_unique_monthly' => 3600, // 1 hour
        'ranking_job' => 3600, // 1 hour
        'ranking_honor' => 3600, // 1 hour
        'ranking_fortress_player' => 3600, // 1 hour
        'ranking_fortress_guild' => 3600, // 1 hour
    ],
    'languages' => [
        'en' => [
            'name' => 'English',
            'flag' => 'us',
            'image' => 'images/us.png',
        ],
        'tr' => [
            'name' => 'Türkçe',
            'flag' => 'tr',
            'image' => 'images/tr.png',
        ],
        'ar' => [
            'name' => 'العربية',
            'flag' => 'eg',
            'image' => 'images/eg.png',
        ],
        'es' => [
            'name' => 'Español',
            'flag' => 'es',
            'image' => 'images/es.png',
        ],
        'de' => [
            'name' => 'Deutsch',
            'flag' => 'de',
            'image' => 'images/de.png',
        ],
        'zh_CN' => [
            'name' => '简体中文',
            'flag' => 'cn',
            'image' => 'images/cn.png',
        ],
    ],
    'referral' => [
        'enabled' => false,
        'reward_points' => 5,
        'reward_type' => 0,
        'minimum_redeem' => 25,
    ],
    'tickets' => [
        'enabled' => false,
        'categories' => [
            'sales' => 'Sales',
            'bugs' => 'Bugs',
            'other' => 'Other',
        ],
    ],
    'logs' => [
        'schedule' => true,
        'unique' => true,
        'unique_advanced' => false,
        'fortress' => true,
        'global' => true,
        'pvp_kill' => false,
        'job_kill' => false,
        'item_plus' => false,
        'item_drop' => false,
    ],
    'slider' => [
        0 => [
            'title' => 'Example headline',
            'desc' => 'Some representative placeholder content for the first slide of the carousel.',
            'image' => 'https://wallpapercave.com/wp/wp7441040.jpg',
            'btn_label' => 'Sign Up',
            'btn_url' => '#',
        ],
        1 => [
            'title' => 'Example headline',
            'desc' => 'Some representative placeholder content for the first slide of the carousel.',
            'image' => 'https://wallpapercave.com/wp/wp7441040.jpg',
            'btn_label' => 'Play Now',
            'btn_url' => '#',
        ],
        2 => [
            'title' => 'Example headline',
            'desc' => 'Some representative placeholder content for the first slide of the carousel.',
            'image' => 'https://wallpapercave.com/wp/wp7441040.jpg',
            'btn_label' => 'Download Now',
            'btn_url' => '#',
        ],
    ],
    'footer' => [
        'general' => [
            0 => [
                'name' => 'Home',
                'url' => '#',
                'image' => '',
            ],
            1 => [
                'name' => 'Privacy Policy',
                'url' => '#',
                'image' => '',
            ],
            2 => [
                'name' => 'Terms & Conditions',
                'url' => '#',
                'image' => '',
            ],
        ],
        'social' => [
            0 => [
                'name' => 'Facebook',
                'url' => 'https://www.facebook.com/',
                'image' => '<i class="fab fa-facebook-f"></i>',
            ],
            1 => [
                'name' => 'Discord',
                'url' => 'https://discord.com/',
                'image' => '<i class="fab fa-discord"></i>',
            ],
            2 => [
                'name' => 'Youtube',
                'url' => 'https://www.youtube.com/',
                'image' => '<i class="fab fa-youtube"></i>',
            ],
        ],
        'backlink' => [
            0 => [
                'name' => 'Elitepvpers',
                'url' => 'https://www.elitepvpers.com/forum/sro-pserver-advertising/',
                'image' => 'https://www.elitepvpers.com/images/logo.png',
            ],
            1 => [
                'name' => 'SIlkroad4arab',
                'url' => 'https://www.silkroad4arab.com/vb/forumdisplay.php?f=85',
                'image' => 'https://www.silkroad4arab.com/vb/sawaweb/images/logo.png',
            ],
            2 => [
                'name' => 'SroCave',
                'url' => 'https://srocave.com/forum/sro-private-server-advertising.34/',
                'image' => 'https://srocave.com/data/assets/logo/SCLogo.png',
            ],
        ],
    ],
];
