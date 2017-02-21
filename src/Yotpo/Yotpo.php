<?php

namespace Yotpo;

use Httpful\Request;
use Httpful\Mime;
use Httpful\Response;

/**
 * Yotpo PHP inetrface for api.yotpo.com
 *
 * @author vlad
 */
class Yotpo
{

    const VERSION = '0.0.3';

    protected static $app_key, $secret, $base_uri = 'https://api.yotpo.com';

    public function __construct($app_key = null, $secret = null, $base_uri = null)
    {
        $this->set_app_key($app_key);

        $this->set_secret($secret);

        if ($base_uri != null) {
            self::$base_uri = $base_uri;
        }

        $template = Request::init()
            ->withoutStrictSsl()// Ease up on some of the SSL checks
            ->expectsJson()// Expect JSON responses
            ->sendsType(Mime::JSON)
            ->addHeader('yotpo_api_connector', 'PHP-' . Yotpo::VERSION);

        // Set it as a template
        Request::ini($template);
    }

    protected function get($uri, array $params = null)
    {
        if (!is_null($params)) {
            $params = self::clean_array($params);
            return self::process_response(Request::get(self::$base_uri . $uri . '?' . http_build_query($params))->send());
        }
        return self::process_response(Request::get(self::$base_uri . $uri)->send());
    }

    protected function post($uri, array $params = null)
    {
        $params = self::clean_array($params);
        return self::process_response(Request::post(self::$base_uri . $uri)->body($params)->send());
    }

    protected function put($uri, array $params = null)
    {
        $params = self::clean_array($params);
        return self::process_response(Request::put(self::$base_uri . $uri)->body($params)->send());
    }

    protected function delete($uri, array $params = null)
    {
        if (!is_null($params)) {
            $params = self::clean_array($params);
            return self::process_response(Request::delete(self::$base_uri . $uri . '?' . http_build_query($params))->send());
        }
        return self::process_response(Request::delete(self::$base_uri . $uri)->send());
    }

    protected static function process_response(Response $response)
    {
        if ($response->hasBody()) {
            return $response->body;
        } else {
            throw new \Exception('Invalid Response');
        }
    }

    public function create_user(array $user_hash)
    {
        $user = array(
            'email' => $user_hash['email'],
            'display_name' => $user_hash['display_name'],
            'password' => $user_hash['password'],
            'url' => $user_hash['url'],
        );
        return $this->post('/users', array('user' => $user));
    }

    public function get_user(array $user_hash)
    {
        $user_id = $user_hash['user_id'];

        if (empty($user_id)) {
            throw new Exception('user_id is mandatory for this request');
        }

        return $this->get("/users/$user_id", array());
    }

    public function get_oauth_token(array $credentials_hash = array())
    {
        $request = array(
            'grant_type' => 'client_credentials'
        );

        $request['client_id'] = $this->get_app_key($credentials_hash);

        if (array_key_exists('secret', $credentials_hash)) {
            $request['client_secret'] = $credentials_hash['secret'];
        } else {
            $request['client_secret'] = self::$secret;
        }

        return $this->post('/oauth/token', $request);
    }

    public function create_account_platform(array $account_platform_hash)
    {
        $account_platform = self::build_request(array(
            'shop_token' => 'shop_token',
            'shop_domain' => 'shop_domain',
            'plan_name' => 'plan_name',
            'platform_type_id' => 'platform_type_id'
        ), $account_platform_hash);
        $account_platform['deleted'] = false;
        $request = array('utoken' => $account_platform_hash['utoken'], 'account_platform' => $account_platform);
        $app_key = $this->get_app_key($account_platform_hash);
        return $this->post("/apps/$app_key/account_platform", $request);
    }

    public function get_login_url(array $credentials_hash = null)
    {
        $request = array();
        $request['app_key'] = $app_key = $this->get_app_key($credentials_hash);
        if (!is_null($credentials_hash) && array_key_exists('secret', $credentials_hash)) {
            $request['secret'] = $credentials_hash['secret'];
        } else {
            $request['secret'] = self::$secret;
        }

        return $this->get('/users/b2blogin.json', $request);
    }

    public function check_subdomain(array $subdomain_hash)
    {
        $app_key = $this->get_app_key($subdomain_hash);
        $subdomain = $subdomain_hash['subdomain'];
        if (is_null($subdomain)) {
            throw new \Exception('subdomain Can not be blank');
        }
        $utoken = $subdomain_hash['utoken'];
        return $this->get("/apps/$app_key/subomain_check/$subdomain?utoken=$utoken");
    }

    public function update_account(array $account_hash)
    {
        $request = array(
            'account' => self::build_request(
                array(
                    'minisite_website_name' => 'minisite_website_name',
                    'minisite_website' => 'minisite_website',
                    'minisite_subdomain' => 'minisite_subdomain',
                    'minisite_cname' => 'minisite_cname',
                    'minisite_subdomain_active' => 'minisite_subdomain_active'
                ), $account_hash),
            'utoken' => $account_hash['utoken']
        );

        $app_key = $this->get_app_key($account_hash);
        return $this->put("/apps/$app_key", $request);
    }

    public function create_purchase(array $purchase_hash)
    {
        $request = self::build_request(
            array(
                'utoken' => 'utoken',
                'platform' => 'platform',
                'email' => 'email',
                'customer_name' => 'customer_name',
                'order_id' => 'order_id',
                'currency_iso' => 'currency_iso',
                'user_reference' => 'user_reference',
                'products' => 'products',
                'validate_data' => 'validate_data',
                'order_date' => 'order_date',
            ), $purchase_hash);
        $app_key = $this->get_app_key($purchase_hash);
        return $this->post("/apps/$app_key/purchases", $request);
    }

    public function create_purchases(array $purchases_hash)
    {
        $request = self::build_request(
            array(
                'utoken' => 'utoken',
                'platform' => 'platform',
                'orders' => 'orders',
                'validate_data' => 'validate_data'),
            $purchases_hash);
        $app_key = $this->get_app_key($purchases_hash);
        return $this->post("/apps/$app_key/purchases/mass_create", $request);
    }

    public function get_purchases(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
            'since_id' => 'since_id',
            'since_date' => 'since_date',
            'page' => 'page',
            'count' => 'count',
        ), $request_hash);
        if (!array_key_exists('page', $request)) {
            $request['page'] = 1;
        }
        if (!array_key_exists('count', $request)) {
            $request['count'] = 10;
        }
        $app_key = $this->get_app_key($request_hash);
        return $this->get("/apps/$app_key/purchases", $request);
    }

    public function delete_purchases(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
            'orders' => 'orders',
        ), $request_hash);

        if (empty($group_name)) {
            throw new Exception('group_name is mandatory for this request');
        }

        $app_key = $this->get_app_key($request_hash);
        return $this->delete("/apps/$app_key/product_groups/$group_name", $request);
    }

    public function send_test_reminder(array $reminder_hash)
    {
        $request = self::build_request(array('utoken' => 'utoken', 'email' => 'email'), $reminder_hash);
        $app_key = $this->get_app_key($reminder_hash);
        return $this->post("/apps/$app_key/reminders/send_test_email", $request);
    }

    public function get_all_bottom_lines(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
            'since_date' => 'since_date',
            'since_id' => 'since_id'
        ), $request_hash);
        $app_key = $this->get_app_key($request_hash);
        return $this->get("/apps/$app_key/bottom_lines", $request);
    }

    public function get_product(array $request_hash)
    {
        $slug = $request_hash['slug'];

        if (empty($slug)) {
            throw new Exception('slug is mandatory for this request');
        }

        $app_key = $this->get_app_key($request_hash);
        return $this->get("/apps/$app_key/products/$slug", array());
    }

    public function get_products(array $request_hash)
    {
        $request = self::build_request(array(
            'page' => isset($request_hash['page']) ? $request_hash['page'] : null,
            'count' => isset($request_hash['count']) ? $request_hash['count'] : null,
            'since_id' => isset($request_hash['since_id']) ? $request_hash['since_id'] : null,
            'since_date' => isset($request_hash['since_date']) ? $request_hash['since_date'] : null,
            'since_updated_at' => isset($request_hash['since_updated_at']) ? $request_hash['since_updated_at'] : null,
            'deleted' => isset($request_hash['deleted']) ? $request_hash['deleted'] : FALSE,
        ), $request_hash);

        $app_key = $this->get_app_key($request_hash);
        return $this->get("/apps/$app_key/products", $request);
    }

    public function create_products(array $products_hash)
    {
        $request = self::build_request(
            array('utoken' => 'utoken', 'products' => 'products'),
            $products_hash);
        $app_key = $this->get_app_key($products_hash);
        return $this->post("/apps/$app_key/products/mass_create", $request);
    }

    public function update_products(array $products_hash)
    {
        $request = self::build_request(
            array('utoken' => 'utoken', 'products' => 'products'),
            $products_hash
        );
        $app_key = $this->get_app_key($products_hash);
        return $this->put("/apps/$app_key/products/mass_update", $request);
    }

    public function create_product_group(array $product_group_hash)
    {
        $request = self::build_request(
            array(
                'utoken' => 'utoken',
                'group_name' => 'group_name',
            ), $product_group_hash);
        $app_key = $this->get_app_key($product_group_hash);
        return $this->post("/v1/apps/$app_key/products_groups", $request);
    }

    public function get_product_group(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
        ), $request_hash);

        $group_name = $request_hash['group_name'];

        if (empty($group_name)) {
            throw new Exception('group_name is mandatory for this request');
        }

        $app_key = $this->get_app_key($request_hash);
        return $this->get("/v1/apps/$app_key/products_groups/$group_name", $request);
    }

    public function get_product_groups(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
        ), $request_hash);
        $app_key = $this->get_app_key($request_hash);
        return $this->get("/v1/apps/$app_key/products_groups", $request);
    }

    public function update_product_group(array $request_hash)
    {
        $params = array(
            'utoken' => 'utoken',
        );

        if (!array_key_exists('product_ids_to_add', $request_hash)) {
            $params['product_ids_to_add'] = 'product_ids_to_add';
        }
        if (!array_key_exists('product_ids_to_remove', $request_hash)) {
            $params['product_ids_to_remove'] = 'product_ids_to_remove';
        }
        if (empty($request_hash['product_ids_to_add']) && empty($request_hash['product_ids_to_remove'])) {
            throw new Exception('product_ids_to_add or product_ids_to_remove is required for this request');
        }

        $request = self::build_request($params, $request_hash);

        $group_name = $request_hash['group_name'];

        if (empty($group_name)) {
            throw new Exception('group_name is mandatory for this request');
        }

        $app_key = $this->get_app_key($request_hash);
        return $this->put("/v1/apps/$app_key/products_groups/$group_name", $request);
    }

    public function delete_product_group(array $request_hash)
    {
        $request = self::build_request(array(
            'utoken' => 'utoken',
        ), $request_hash);

        $group_name = $request_hash['group_name'];

        if (empty($group_name)) {
            throw new Exception('group_name is mandatory for this request');
        }

        $app_key = $this->get_app_key($request_hash);
        return $this->delete("/v1/apps/$app_key/products_groups/$group_name", $request);
    }

    public function create_review(array $review_hash)
    {
        $params = array(
            'app_key' => 'appkey',
            'product_id' => 'sku',
            'domain' => 'shop_domain',
            'product_title' => 'product_title',
            'product_description' => 'product_description',
            'product_url' => 'product_url',
            'product_image_url' => 'product_image_url',
            'display_name' => 'user_display_name',
            'email' => 'user_email',
            'review_content' => 'review_body',
            'review_title' => 'review_title',
            'review_score' => 'review_score',
            'reviewer_type' => 'reviewer_type',
            'utoken' => 'utoken',
        );
        $request = self::build_request($params, $review_hash);

        return $this->get('/reviews/dynamic_create', $request);
    }

    public function create_review_async(array $review_hash)
    {
        return $this->create_review($review_hash);
    }


    public function create_review_sync(array $review_hash)
    {
        $params = array(
            'app_key' => 'appkey',
            'product_id' => 'sku',
            'domain' => 'shop_domain',
            'product_title' => 'product_title',
            'product_description' => 'product_description',
            'product_url' => 'product_url',
            'product_image_url' => 'product_image_url',
            'display_name' => 'user_display_name',
            'email' => 'user_email',
            'review_content' => 'review_body',
            'review_title' => 'review_title',
            'review_score' => 'review_score',
            'reviewer_type' => 'reviewer_type',
            'user_reference' => 'user_reference',
            'utoken' => 'utoken',
        );
        $request = self::build_request($params, $review_hash);

        return $this->get('/reviews/dynamic_create', $request);
    }

    public function get_review($review_id)
    {
        return $this->get("/reviews/$review_id");
    }

    public function get_product_reviews(array $request_hash)
    {
        $app_key = $this->get_app_key($request_hash);

        $product_id = $request_hash['product_id'];

        if (!$product_id) {
            throw new Exception('product_id is mandatory for this request');
        }

        $request_params = array(
            'page' => isset($request_hash['page']) ? $request_hash['page'] : null,
            'count' => isset($request_hash['count']) ? $request_hash['count'] : null,
            'since_id' => isset($request_hash['since_id']) ? $request_hash['since_id'] : null,
            'since_date' => isset($request_hash['since_date']) ? $request_hash['since_date'] : null,
            'since_updated_at' => isset($request_hash['since_updated_at']) ? $request_hash['since_updated_at'] : null,
            'deleted' => isset($request_hash['deleted']) ? $request_hash['deleted'] : FALSE,
            'user_reference' => isset($request_hash['user_reference']) ? $request_hash['user_reference'] : null,
        );

        return $this->get("/apps/$app_key/reviews", $request_params);
    }

    public function get_all_product_reviews(array $request_hash)
    {
        $app_key = $this->get_app_key($request_hash);

        $request_params = array(
            'utoken' => 'utoken',
            'page' => isset($request_hash['page']) ? $request_hash['page'] : null,
            'count' => isset($request_hash['count']) ? $request_hash['count'] : null,
            'since_id' => isset($request_hash['since_id']) ? $request_hash['since_id'] : null,
            'since_date' => isset($request_hash['since_date']) ? $request_hash['since_date'] : null,
            'since_updated_at' => isset($request_hash['since_updated_at']) ? $request_hash['since_updated_at'] : null,
            'deleted' => isset($request_hash['deleted']) ? $request_hash['deleted'] : FALSE,
            'user_reference' => isset($request_hash['user_reference']) ? $request_hash['user_reference'] : null,
        );

        $request = self::build_request($request_params, $request_hash);

        return $this->get("/apps/$app_key/reviews", $request);
    }

    public function get_product_bottom_line(array $request_hash)
    {
        $app_key = $this->get_app_key($request_hash);
        $product_id = $request_hash['product_id'];

        if (!$product_id) {
            throw new \Exception('product_id is mandatory for this request');
        }
        return $this->get("/products/$app_key/$product_id/bottomline");
    }

    public function set_app_key($app_key)
    {
        if ($app_key != null) {
            self::$app_key = $app_key;
        }
    }

    public function set_secret($secret)
    {
        if ($secret != null) {
            self::$secret = $secret;
        }
    }

    protected function get_app_key($hash)
    {
        if (!is_null($hash) && !empty($hash) && array_key_exists('app_key', $hash)) {
            return $hash['app_key'];
        } elseif (self::$app_key != null) {
            return self::$app_key;
        } else {
            throw new \Exception('app_key is mandatory for this request');
        }
    }

    protected static function build_request(array $params, array $request_params)
    {
        $request = array();
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $request_params)) {
                $request[$value] = $request_params[$key];
            }
        }
        return $request;
    }

    protected static function clean_array(array $array)
    {

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    if (empty($value2)) {
                        unset($array[$key][$key2]);
                    }
                }
            }
            if (empty($array[$key])) {
                unset($array[$key]);
            }
        }
        return $array;
    }

}
