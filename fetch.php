<?php
/**
 * Fetch post views and social shares data.
 *
 * @package knife-analytics
 * @version 1.1.0
 * @author  Anton Lukin
 * @license MIT
 */

namespace Knife\Analytics;

use Dotenv\Dotenv;
use Exception;
use PDO;

if(php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Register composer autoloader.
 */
require_once(__DIR__ . '/vendor/autoload.php');

/**
 * Create database instance and declare common fetching methods.
 */
class Fetch {
    /**
     * Database instance.
     */
    protected static $db = null;

    /**
     * Set current directory path.
     */
    protected static $path = __DIR__;

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

        // Set PDO settings.
        $settings = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => true
        );

        self::$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $settings);
    }

    /**
     * Launch fetching process.
     */
    public function launch() {
        try {
            // Collect Social Shares data.
            new SocialShares();

            // Collect Google Analytics data.
            new GoogleAnalytics();

        } catch(Exception $e) {
            $this->notify_admin($e->getMessage());
        }
    }

    /**
     * Notify admins about parse errors.
     *
     * @param string $error Error message.
     */
    protected function notify_admin($error)
    {
        if (!isset($_ENV['TELEGRAM_CHAT'], $_ENV['TELEGRAM_TOKEN'])) {
            return;
        }

        $message = array(
            'text'       => $error,
            'chat_id'    => $_ENV['TELEGRAM_CHAT'],
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        );

        $url = 'https://api.telegram.org/bot' . $_ENV['TELEGRAM_TOKEN'] . '/sendMessage';

        $this->make_request($url, $message);
    }

    /**
     * Send cURL request.
     *
     * @param string $url Custom URL.
     *
     * @return string|bool
     */
    protected function make_request($url, $data = null, $cookie = null)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'curl/' . curl_version()['version']);

        if ($data) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        if ($cookie) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Cookie: " . $cookie));
        }

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}

(new Fetch)->launch();

