<?php
/**
 * Collect and save analytics data from Google Analytics.
 */
class GoogleAnalytics
{
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
        if (empty($_ENV['GA_ID'])) {
            throw new Exception('Empty GA ID');
        }

        $service = self::get_service();

        foreach ($posts as $post) {
            $metrics = array(
                'metrics'     => 'ga:pageviews,ga:uniquePageviews',
                'filters'     => 'ga:pagePath=@' . $post['slug'],
                'max-results' => '20'
            );

            // Get GA results
            $result = $service->data_ga->get($_ENV['GA_ID'], $post['publish'], 'today', 'ga:visits', $metrics);

            $data = array(
                'post_id'     => $post['post_id'],
                'pageviews'   => $result->totalsForAllResults['ga:pageviews'],
                'uniqueviews' => $result->totalsForAllResults['ga:uniquePageviews']
            );

            $update = $database->prepare("UPDATE posts SET pageviews = :pageviews, uniqueviews = :uniqueviews, updated = NOW() WHERE post_id = :post_id");
            $update->execute($data);
        }
    }

    /**
     * Get GA service.
     *
     * @return instance
     */
    private static function get_service()
    {
        if (empty($_ENV['GA_KEY'])) {
            throw new Exception('Empty GA key');
        }

        $client = new Google_Client();

        $client->setAuthConfig(ABSPATH . $_ENV['GA_KEY']);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);

        return new Google_Service_Analytics($client);
    }
}
