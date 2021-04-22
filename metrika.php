<?php
/**
 * Fetch post views from yandex metrika
 *
 * @version 1.0.0
 */

if(php_sapi_name() !== 'cli') {
    exit;
}


/**
 * Register composer autoloader
 */
require_once(__DIR__ . '/vendor/autoload.php');


/**
 * Try to load dotenv
 */
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


/**
 * Check required options
 */
$dotenv->required([
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'YANDEX_TOKEN'
]);


/**
 * Create PDO statement
 */
$statement = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8";


/**
 * Connect with database
 */
$database = new PDO($statement, $_ENV['DB_USER'], $_ENV['DB_PASS']);


/**
 * Get query limit for today
 */
function get_limit($database, $limit) {
    // Check limits for today
    $count = (int) $database->query("SELECT COUNT(*) FROM posts WHERE DATE(updated) = DATE(NOW())")->fetchColumn();

    return $limit - $count;
}

/**
 * Get posts using limit
 */
function get_posts($database, $limit = 3000) {
    $limit = get_limit($database, $limit);

    if ($limit > 0) {
        $select = "SELECT post_id, slug, DATE(publish) AS publish FROM posts ORDER BY updated, post_id ASC LIMIT " . $limit;

        // Get availible posts
        return $database->query($select)->fetchAll();
    }

    $select = "SELECT post_id, slug, DATE(publish) AS publish FROM posts WHERE publish > DATE_SUB(NOW(), INTERVAL 1 MONTH)";

    // Get posts for month
    return $database->query($select)->fetchAll();
}


//$posts = get_posts($database, 5);

$args = [
    "ids"               => "45571896",                            // номер счётчика метрики
    'metrics'           => 'ym:s:publisherViewsFullRead', // данные по: страницам и количеству просмотров
    'dimensions'        => 'ym:s:publisherArticle',               // группировка по URLHash
    "date1"             => "2020-01-01",                  // с какой даты получить отчёт
    'accuracy'          => 'full',                        // точная статистика (без округления)
    'limit'             => '1000',                      // максимальный лимит данных
    'proposed_accuracy' => 'false'                        // без округления данных
];


$url = 'https://api-metrika.yandex.ru/stat/v1/data?' . http_build_query($args);


#$url = 'https://api-metrika.yandex.net/stat/v1/data?ids=45571896&date1=2020-01-01&dimensions=ym:s:publisherArticle&metrics=ym:s:publisherviews&filters=(ym:s:publisherArticle!n)&sort=-ym:s:publisherviews';
$options = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'Content-Type: application/x-yametrika+json',
            'Authorization: Bearer' . $_ENV['YANDEX_TOKEN']
        ]
    ]
];

$context = stream_context_create($options);
$request = file_get_contents($url, false, $context);

$json = json_decode($request);

print_r($json); exit;
