<?php
/**
 * Collect social shares using posts list.
 */
class SocialShares
{
    /**
     * Url to Facebook counter API
     *
     * @var string
     */
    private static $fb_api = 'https://knife.support/facebook/?id=';

    /**
     * Url to vk.com counter API
     *
     * @var string
     */
    private static $vk_api = 'https://vk.com/share.php?act=count&index=0&url=';

    /**
     * Init class with posts list.
     *
     * @param array     $posts    Lists of posts to collect data.
     * @param instnance $database Database instance.
     *
     * @return void
     */
    public static function collect($posts, $database)
    {
        foreach ($posts as $post) {
            $data = array('post_id' => $post['post_id']);

            $select = $database->prepare("SELECT fb, vk FROM shares WHERE post_id = :post_id");
            $select->execute($data);

            $shares = $select->fetch();

            if (false === $shares) {
                $shares = array_fill_keys(array('vk', 'fb'), 0);

                // Add default empty row
                $insert = $database->prepare("INSERT INTO shares (post_id) VALUES (:post_id)");
                $insert->execute($data);
            }

            // Get Facebook shares.
            $data['fb'] = self::get_fb($post['slug'], $shares['fb']);

            // Get VK.com shares.
            $data['vk'] = self::get_vk($post['slug'], $shares['vk']);

            $update = $database->prepare("UPDATE shares SET fb = :fb, vk = :vk WHERE post_id = :post_id");
            $update->execute($data);
        }
    }

    /**
     * Get Facebook share count.
     *
     * @param string  $slug   URL slug.
     * @param integer $before Previous Facebook counter.
     *
     * @return integer
     */
    public static function get_fb($slug, $before = 0) {
        if (empty($_ENV['SITE_URL'])) {
            throw new Exception('Site url undefined');
        }

        // Create post link
        $link = urlencode($_ENV['SITE_URL'] . $slug);

        // Make request to Facebook
        $data = self::make_request(self::$fb_api . $link);

        if (false === $data) {
            return $before;
        }

        preg_match('/^knifeFacebookCount\((.+)\)$/', $data, $match);

        if (!isset($match[1])) {
            return $before;
        }

        $data = json_decode($match[1]);

        if (!isset($data->engagement->share_count)) {
            return $before;
        }

        return $data->engagement->share_count;
    }

    /**
     * Get VK.com share count.
     *
     * @param string  $slug   URL slug.
     * @param integer $before Previous VK counter.
     *
     * @return integer
     */
    public static function get_vk($slug, $before = 0) {
        if (empty($_ENV['SITE_URL'])) {
            throw new Exception('Site url undefined');
        }

        // Create post link
        $link = urlencode($_ENV['SITE_URL'] . $slug);

        // Make request to VK.com
        $data = self::make_request(self::$vk_api . $link);

        if (false === $data) {
            return $before;
        }

        preg_match('/^VK.Share.count\(0, (\d+)\);$/', $data, $match);

        if (!isset($match[1])) {
            return $before;
        }

        return $match[1];
    }


    /**
     * Send cURL request.
     *
     * @param string $url Custom URL.
     *
     * @return string|bool
     */
    public static function make_request($url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}

