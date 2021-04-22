<?php
/**
 * Fetch post views from google analytics
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
$dotenv->required(
    array('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS')
);

define('ABSPATH', dirname(__FILE__));


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

    /*
    if ($limit > 0) {
        $select = "SELECT post_id, slug, DATE(publish) AS publish FROM posts ORDER BY updated, post_id ASC LIMIT " . $limit;

        // Get availible posts
        return $database->query($select)->fetchAll();
    }
     */

    $select = "SELECT post_id, slug, DATE(publish) AS publish FROM posts WHERE publish > DATE_SUB(NOW(), INTERVAL 1 MONTH)";

    // Get posts for month
    return $database->query($select)->fetchAll();
}

try {
    $posts = get_posts($database);

    // Collect Social Shares data
    SocialShares::collect($posts, $database);

    // Collect Google Analtycs data
    // GoogleAnalytics::collect($posts, $database);

} catch(Exception $e) {
    echo $e->getMessage();
}

