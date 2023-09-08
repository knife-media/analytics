<?php
/**
 * Get Google Analytics post views data.
 *
 * @package knife-analytics
 * @since  1.1.0
 */

namespace Knife\Analytics;

use Exception;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

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

        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . self::$path . $_ENV['GA_KEY']);

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
         $select = self::$db->prepare("SELECT posts.post_id, posts.slug,
            DATE(posts.publish) as publish
            FROM posts ORDER BY publish DESC");

        $select->execute();

        return $select->fetchAll();
    }

    /**
     * Init class with posts list.
     *
     * @param array $posts Lists of posts to collect data.
     */
    private function collect($posts)
    {
        $client = new BetaAnalyticsDataClient();

        $response = $client->runReport(array(
            'property' => 'properties/' . $_ENV['GA_ID'],
            'dateRanges' => array(
                new DateRange(
                    array(
                        'start_date' => '2023-08-11',
                        'end_date' => 'today',
                    )
                ),
            ),
            'dimensions' => array(
                new Dimension(
                    array(
                        'name' => 'pagePath',
                    )
                ),
            ),
            'metrics' => array(
                new Metric(
                    array(
                        'name' => 'screenPageViews',
                    )
                )
            ),
            'limit' => 250000,
        ));

        $fields = array();

        foreach ($response->getRows() as $row) {
            $fields[$row->getDimensionValues()[0]->getValue()] = $row->getMetricValues()[0]->getValue();
        }

        foreach ($posts as $post) {
            $slug = $post['slug'];

            if (!isset($fields[$slug])) {
                continue;
            }

            if (empty($post['oldviews'])) {
                $post['oldviews'] = 0;
            }

            $data = array(
                'post_id'   => $post['post_id'],
                'pageviews' => (int) $fields[$slug] + (int) $post['oldviews'],
            );

            $insert = self::$db->prepare("INSERT INTO views (post_id, pageviews)
                VALUES (:post_id, :pageviews) ON DUPLICATE KEY
                UPDATE pageviews = VALUES(pageviews), updated = NOW()");

            $insert->execute($data);
        }
    }
}

