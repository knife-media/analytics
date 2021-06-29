<?php
/**
 * Get Google Analytics post views data.
 *
 * @package knife-analytics
 * @since  1.1.0
 */

namespace Knife\Analytics;

use Exception;
use Google_Client;
use Google_Service_Analytics;

/**
 * Collect and save analytics data from Google Analytics.
 */
class GoogleAnalytics extends Fetch
{
    /**
     * Init class.
     */
    public function __construct()
    {
        if (!isset($_ENV['GA_ID'], $_ENV['GA_KEY'])) {
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
        // Add required last 60 posts.
        $posts = array_merge($posts, $this->get_last_posts());

        // Calculate up to the total limit.
        $limit = 300 - count($posts);

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
        $select = self::$db->prepare("SELECT posts.post_id, posts.slug,
            DATE(posts.publish) as publish
            FROM posts LEFT JOIN views USING (post_id)
            ORDER BY views.updated ASC LIMIT :limit");


        $select->execute(compact('limit'));

        return $select->fetchAll();
    }

    /**
     * Get last 30 posts.
     *
     * @param int $limit Select posts limit.
     *
     * @return array
     */
    private function get_last_posts($limit = 60)
    {
        $select = self::$db->prepare("SELECT post_id, slug,
            DATE(publish) AS publish FROM posts ORDER BY publish DESC LIMIT :limit");

        $select->execute(compact('limit'));

        return $select->fetchAll();
    }


    /**
     * Init class with posts list.
     *
     * @param array $posts Lists of posts to collect data.
     */
    private function collect($posts)
    {
        $client = new Google_Client();

        $client->setAuthConfig(self::$path . $_ENV['GA_KEY']);
        $client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));

        // Create new Analytics service.
        $service = new Google_Service_Analytics($client);

        foreach ($posts as $post) {
            $metrics = array(
                'metrics'     => 'ga:pageviews,ga:uniquePageviews',
                'filters'     => 'ga:pagePath=@' . $post['slug'],
                'max-results' => '50',
            );

            // Get GA results.
            $result = $service->data_ga->get($_ENV['GA_ID'], $post['publish'], 'today', 'ga:visits', $metrics);

            $data = array(
                'post_id'     => $post['post_id'],
                'pageviews'   => $result->totalsForAllResults['ga:pageviews'],
                'uniqueviews' => $result->totalsForAllResults['ga:uniquePageviews'],
            );

            $insert = self::$db->prepare("INSERT INTO views (post_id, pageviews, uniqueviews)
                VALUES (:post_id, :pageviews, :uniqueviews) ON DUPLICATE KEY
                UPDATE pageviews = VALUES(pageviews), uniqueviews = VALUES(uniqueviews), updated = NOW()");

            $insert->execute($data);
        }
    }
}

