<?php

return [
	'settings' => [
        'tracy' => [
            'logDir' => __DIR__ . '/../logs',
            'mode' => \Tracy\Debugger::DEBUG,//dasi mod PRODUCTION
            'configs' => [
                'ConsoleAccounts' => ['dev' => '34c6fceca75e456f25e7e99531e2425c6c1de443']// = sha1('dev')
            ]
        ],
        'data_api_url' => 'https://api.e-cyanobacterium.org/',
        'not_psw' => 'loose_all_your_money',
        'python' => 'python3.6'
	],
];
