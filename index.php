<?php
/**
 * Show post analytics with simple API.
 *
 * @package knife-analytics
 * @version 1.1.0
 * @author  Anton Lukin
 * @license MIT
 */

namespace Knife\Analytics;

use Dotenv\Dotenv;
use PDO;

if(php_sapi_name() === 'cli') {
//    exit;
}

/**
 * Register composer autoloader
 */
require_once(__DIR__ . '/vendor/autoload.php');

/**
 * Create simple API to receive sharing posts data.
 */
final class API {
    /**
     * Database instance
     */
    private static $db = null;

    /**
     * Script base
     */
    private static $base = '';

    /**
     * Class entry point.
     */
    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $dotenv->required(
            array('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD')
        );

        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8";

        // Create PDO connection using credentials and statement
        $settings = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => true
        );

        if (!empty($_ENV['URL_BASE'])) {
            self::$base = $_ENV['URL_BASE'];
        }

        self::$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $settings);
    }

    /**
     * Start with server request uri.
     *
     * @param string $request Request URI.
     */
    public function router($request) {
        $url = strtok(str_replace(self::$base, '', $request), '?');

        // Get first url subdirectory.
        list($action) = explode('/', trim($url, '/'));

        switch ($action) {
            case 'shares':
                $this->get_shares();
                break;

            default:
                $this->send_json_error('Wrong API endpoint');
        }
    }

    /**
     * Get shares handler.
     */
    private function get_shares() {
        if (!isset($_GET['post'])) {
            $this->send_json_error('Required parameter post is not defined');
        }

        $post = (int) $_GET['post'];

        $select = self::$db->prepare('SELECT fb, vk FROM shares WHERE post_id = :post');
        $select->execute(compact('post'));

        $shares = $select->fetch();

        if (false === $shares) {
            $shares = array_fill_keys(array('fb', 'vk'), 0);
        }

        $this->send_json_success($shares);
    }

    /**
     * Send JSON and exit.
     *
     * @param int   $status HTTP status code.
     * @param array $output Data to response.
     */
    private function send_json($output, $status) {
        http_response_code($status);

        header('Content-Type: application/json');
        echo json_encode($output);

        exit;
    }

    /**
     * Send JSON width error message.
     *
     * @param mixed $data   Error data.
     * @param int   $status HTTP status code. By default: 500.
     */
    private function send_json_error($data, $status = 500) {
        $output = array(
            'success' => false,
            'data'    => $data,
        );

        return $this->send_json($output, $status);
    }

    /**
      * Send JSON with success message.
     *
     * @param mixed $data   Success data.
     * @param int   $status HTTP status code. By default: 500.
     */
    private function send_json_success($data) {
        $output = array(
            'success' => true,
            'data'    => $data,
        );

        return $this->send_json($output, 200);
    }
}

(new API())->router($_SERVER['REQUEST_URI']);

