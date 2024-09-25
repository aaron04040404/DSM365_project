<?php
/**
 * Application setting of Slim 3
 *
 * @author Nick Feng
 * @since 1.0
 */
return [
    'settings' => [
        'displayErrorDetails'    => true,   // set to false in production
        'addContentLengthHeader' => false,  // Allow the web server to send the content-length header
        'debug' => true,                    // debug mode
        'mode'  => 'development',           // for developer in debug mode
        
        // Monolog settings
        'logger' => [
            'name'  => 'api',
            'path'  => isset( $_ENV['docker'] ) ? 'php://stdout' : __DIR__ . '/../logs/api.' . date( 'YmdH' ) . '.log',
            'level' => Monolog\Logger::DEBUG,
        ],
        
        'app' => [
            'dns' => [
                'self'  => 'http://127.0.0.1:377',
                'admin' => [
                    'auto_register' => [
                        'url' => 'http://127.0.0.1/api/v1/dsservice/auto-register',    // better to use internal IP in GCP, it will be faster in transfer
                        'ssl' => require __DIR__ . '/../security/ssl/ssl_secur.php',   // default is false,
                        'api_secret_v1' => require __DIR__ . '/../security/jwt/admin_api_secret.php'
                    ],
                    'auto_reset' => [
                        'url' => 'http://127.0.0.1/api/v1/dsservice/auto-reset',      // better to use internal IP in GCP, it will be faster in transfer
                        'ssl' => require __DIR__ . '/../security/ssl/ssl_secur.php',  // default is false,
                        'api_secret_v1' => require __DIR__ . '/../security/jwt/admin_api_secret.php'
                    ]
                ]
            ],
            'url' => [
                'login'    => '/login',
                'reset_pw' => '/password/new?v=',
            ],
            'asset' => [
                'json' => __DIR__ . '/../asset/json'
            ],
            'upload' => [
                'file' => __DIR__ . '/../uploads'   // subdirectory for the behind you have to customize it yourself
            ]
        ],

        // for JWT and cookies
        'oauth' => [
            'header'              => 'X-Token',
            'environment'         => 'HTTP_X_TOKEN',
            'access_token_cookie' => '_l',
            'cookie_secur'        => require __DIR__ . '/../security/ssl/ssl_secur.php',// default is false,
            'access_secret'       => require __DIR__ . '/../security/jwt/secret-access.php',
            'api_secret'          => require __DIR__ . '/../security/jwt/secret-api.php',
            'register_secret'     => require __DIR__ . '/../security/jwt/secret-register.php',
            'pw_reset_secret'     => require __DIR__ . '/../security/jwt/secret-pw-reset.php',
            //'outsource_access'    => require __DIR__ . '/../security/open-api/outsource-access-map.php'
        ],
        
        'firebase' => [
            'header' => 'Oauth',
            'projectId' => 'dsm365-web',
            'service_account_key' => __DIR__ . '/../security/gcp/firebase/serviceAccountKey.json',   // you have to get the json file from Firebase website
            'collections' => [  // root default collections(the first node) on firestore
                'notification_main' => [ // 統一的名稱，盡量跟 database MySQL 的名字不要差太多，最好一樣。而下面的 name 就是依照 正式機與測試機的不同，而有所部一樣的名字
                    'name' => 'notification_main_test'  // 這才是真正在 default node(root collection)中的名字，這樣才可以分開測試機器名子與正式機器的名字
                    // .... 其他新的設定需求
                ],
                'tcc_notification_main' => [
                    'name' => 'tcc_notification_main_test'  // 這才是真正在 default node(root collection)中的名字，這樣才可以分開測試機器名子與正式機器的名字
                ],
                'screenshot' => [   // 統一的名稱，盡量跟 database MySQL 的名字不要差太多，最好一樣。而下面的 name 就是依照 正式機與測試機的不同，而有所部一樣的名字
                    'name' => 'tcc_screenshot_test' // 這才是真正在 default node(root collection)中的名字，這樣才可以分開測試機器名子與正式機器的名字
                ],
                'mcb_raw' => [
                    'name' => 'mcb_raw_test'
                ]
            ]
        ],
        
        'gcp_storage' => [
            'mcb_screenshot' => [
                'bucket_id'           => 'dsm365-img-test',
                'projectId'           => 'dsm365-web',
                'service_account_key' => __DIR__  . '/../security/gcp/cloud-storage/dsm365-web-853f033e3f0d.json',
                'storage_class'       => 'STANDARD',
                'location'            => 'asia-east1',
                'prefix_folder'       => 'mcb_screenshot/'
            ]
        ],
        
        'mailer' => [
            'active'        => true,                     // turn off the flag, system will not send email anyway.
            'os_script_url' => __DIR__ . '/../phpmailer/mailer-for-os.php',         // for Linux execute
            'log_url'       => __DIR__ . '/../logs/mailer.$(date +%Y-%m-%d).log',   // for Linux execute
            'debug'         => 2,                         // Debug level to show client -> server and server -> client messages.
            'is_smtp'       => true,                      // Set mailer to use SMTP
            'host'          => 'smtp.gmail.com',    // Specify main and backup SMTP servers
            'port'          => 587,                       // TCP port to connect to
            'smtp_auth'     => true,                      // Enable SMTP authentication
            'hostname'      => 'dynascan365.com',         // EHLO: setup email domain name
            'user_name'     => 'info@dynascan365.com',    // SMTP username
            'user_pw'       => 'mykyvxidsnwzkbpg',        // SMTP password
            'smtp_secure'   => 'tls',                     // Enable TLS encryption, `ssl` also accepted
            'is_html'       => true,
            'templates'     => __DIR__ . '/../templates/email/'
        ],

        'db' => [
            'client' => [
                'host'     => 'mysql:host=104.199.205.133;port=3306;dbname=dynascan365_client;charset=utf8mb4',
                'user'     => 'dsm365_php',
                'password' => '198411121qaz@wsX',
                'option'   => [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    PDO::MYSQL_ATTR_SSL_KEY  => __DIR__ . '/../security/ssl/client-key.pem',
                    PDO::MYSQL_ATTR_SSL_CERT => __DIR__ . '/../security/ssl/client-cert.pem',
                    PDO::MYSQL_ATTR_SSL_CA   => __DIR__ . '/../security/ssl/server-ca.pem',
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
                ]
            ],
            
            'main' => [
                'host'     => 'mysql:host=104.199.205.133;port=3306;dbname=dynascan365_main;charset=utf8mb4',
                'user'     => 'dsm365_php',
                'password' => '198411121qaz@wsX',
                'option'   => [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    PDO::MYSQL_ATTR_SSL_KEY  => __DIR__ . '/../security/ssl/client-key.pem',
                    PDO::MYSQL_ATTR_SSL_CERT => __DIR__ . '/../security/ssl/client-cert.pem',
                    PDO::MYSQL_ATTR_SSL_CA   => __DIR__ . '/../security/ssl/server-ca.pem',
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
                ]
            ],
            
            'logger' => [
                'host'     => 'mysql:host=34.81.104.44;port=3306;dbname=demo_dynascan365;charset=utf8mb4',
                'user'     => 'dsm365_log_demo',
                'password' => '1qaz@wsX',
                'table'    => 'client_log',
                'option'   => [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    PDO::MYSQL_ATTR_SSL_KEY  => __DIR__ . '/../security/ssl/log/client-key.pem',
                    PDO::MYSQL_ATTR_SSL_CERT => __DIR__ . '/../security/ssl/log/client-cert.pem',
                    PDO::MYSQL_ATTR_SSL_CA   => __DIR__ . '/../security/ssl/log/server-ca.pem',
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
                ]
            ]
        ]
    ]
];
