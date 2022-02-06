<?php
/**
 * Get social shares from Facebook and VK API.
 *
 * @package knife-analytics
 * @since  1.1.0
 */

namespace Knife\Analytics;

use Exception;

/**
 * Collect social shares.
 */
class SocialShares extends Fetch
{
    /**
     * Init class.
     */
    public function __construct()
    {
        if (!isset($_ENV['SITE_URL'])) {
            return;
        }

        // Get posts list to fetch data.
        $posts = $this->get_posts();

        if (count($posts) > 0) {
            $this->collect($posts);
        }
    }

    /**
     * Get posts smartly.
     *
     * @param array $posts Default posts.
     *
     * @return array
     */
    private function get_posts($posts = array())
    {
        // Add required last 30 posts.
        $posts = array_merge($posts, $this->get_last_posts());

        // Add recurrent posts for last 2 weeks.
        $posts = array_merge($posts, $this->get_recurrent_posts());

        // Calculate up to the total limit.
        $limit = 200 - count($posts);

        if ($limit > 0) {
            $posts = array_merge($posts, $this->get_outdated_posts($limit));
        }

        return $posts;
    }

    /**
     * Get outdated posts.
     *
     * @param int $limit Select posts limit.
     *
     * @return array
     */
    private function get_outdated_posts($limit = 200)
    {
        $select = self::$db->prepare("SELECT posts.post_id, posts.slug
            FROM posts LEFT JOIN shares USING (post_id)
            ORDER BY shares.updated ASC LIMIT :limit");

        $select->execute(compact('limit'));

        return $select->fetchAll();
    }

    /**
     * Get recurrent posts.
     * There are not updated 2 weeks posts for last 6 hours.
     *
     * @return array
     */
    private function get_recurrent_posts()
    {
        $select = self::$db->query("SELECT posts.post_id, posts.slug
            FROM posts LEFT JOIN shares USING (post_id)
            WHERE shares.updated < DATE_SUB(NOW(), INTERVAL 6 HOUR)
            AND posts.publish > DATE_SUB(NOW(), INTERVAL 2 WEEK)
            ORDER BY shares.updated ASC");

        return $select->fetchAll();
    }

    /**
     * Get last 30 posts.
     *
     * @param int $limit Select posts limit.
     *
     * @return array
     */
    private function get_last_posts($limit = 30)
    {
        $select = self::$db->prepare("SELECT post_id, slug FROM posts ORDER BY publish DESC LIMIT :limit");
        $select->execute(compact('limit'));

        return $select->fetchAll();
    }

    /**
     * Collect psots data.
     *
     * @param array $posts List of posts.
     */
    private function collect($posts)
    {
        foreach ($posts as $post) {
            $data = $this->get_post_shares($post);

            // Set new shares data for this post.
            $update = self::$db->prepare("UPDATE shares SET fb = :fb, vk = :vk, updated = NOW() WHERE post_id = :post_id");
            $update->execute($data);
        }
    }

    /**
     * Get post shares.
     *
     * @param array $post Current post data.
     *
     * @return array
     */
    private function get_post_shares($post)
    {
        $data = array(
            'post_id' => $post['post_id'],
        );

        $select = self::$db->prepare("SELECT fb, vk FROM shares WHERE post_id = :post_id");
        $select->execute($data);

        $shares = $select->fetch();

        if (false === $shares) {
            $shares = $this->add_default_shares($data);
        }

        $link = urlencode($_ENV['SITE_URL'] . $post['slug']);

        // Get Facebook shares.
        $data['fb'] = $this->get_fb_data($link, $shares['fb']);

        // Get VK.com shares.
        $data['vk'] = $this->get_vk_data($link, $shares['vk']);

        return $data;
    }

    /**
     * Add default shares data for new posts and return them.
     *
     * @param array Post data.
     *
     * @return array
     */
    private function add_default_shares($data)
    {
        $shares = array_fill_keys(array('fb', 'vk'), 0);

        // Add default empty row
        $insert = self::$db->prepare("INSERT INTO shares (post_id) VALUES (:post_id)");
        $insert->execute($data);

        return $shares;
    }

    /**
     * Get Facebook share count.
     *
     * @param string  $slug   Post link.
     * @param integer $before Previous Facebook shares data.
     *
     * @return integer
     */
    private function get_fb_data($link, $before = 0)
    {
        $shares = 0;

        if (isset($_ENV['FACEBOOK_TOKEN'])) {
            $data = $this->make_request('https://graph.facebook.com/?id=' . $link . '&fields=engagement&access_token=' . $_ENV['FACEBOOK_TOKEN']);

            // Try to parse json answer.
            $data = json_decode($data, true);

            if (isset($data['engagement'])) {
                foreach ($data['engagement'] as $key => $value) {
                    $shares = $shares + intval($value);
                }
            }
        }

        // Try alternate method.
        if ($shares <= $before) {
            $data = $this->make_request('https://www.facebook.com/plugins/like.php?layout=button_count&locale=en_US&href=' . $link, null, 'c_user=100001793856607');

            if (false === $data) {
                return $before;
            }

            preg_match('#>Like</span><span.+?>([\d.]+K?)<#i', $data, $likes);

            if (!isset($likes[1])) {
                throw new Exception("Analytics Facebook parse error:\n" . urldecode($link));
            }

            $shares = $likes[1];

            if ('k' === strtolower(substr($shares, -1))) {
                $shares = intval($shares) * 1000;
            }
        }

        return max($shares, $before);
    }

    /**
     * Get VK.com share count.
     *
     * @param string  $slug   Post link.
     * @param integer $before Previous VK.com shares data.
     *
     * @return integer
     */
    private function get_vk_data($link, $before = 0)
    {
        $data = $this->make_request('https://vk.com/share.php?act=count&index=0&url=' . $link);

        if (false === $data) {
            return $before;
        }

        preg_match('/^VK.Share.count\(0, (\d+)\);$/', $data, $likes);

        if (!isset($likes[1])) {
            throw new Exception("Analytics VK.com parse error:\n" . urldecode($link));
        }

        return max($likes[1], $before);
    }
}

