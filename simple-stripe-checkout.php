<?php

/*
  Plugin Name: Simple Stripe Checkout
  Plugin URI: https://s-page.biz/ssc/
  Description: 決済プラットフォーム「Stripe」の連携プラグイン
  Version: 1.1.28
  Author: growniche
  Author URI: https://www.growniche.co.jp/
  Text Domain: simple-stripe-checkout
  Domain Path: /languages/
*/

const GSSC = 'simple-stripe-checkout';

load_plugin_textdomain(GSSC, false, plugin_basename( dirname( __FILE__ ) ) . '/languages');

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

register_activation_hook(__FILE__, function($network_wide) {
    $initialize_pages = function() {
        // 固定ページ「決済完了」が未登録の場合
        if (!get_page_by_path(SimpleStripeCheckout::SLUG__CHECKEDOUT)) {
            // 決済完了の固定ページを作成
            $page_id = wp_insert_post(array(
                'post_name' => SimpleStripeCheckout::SLUG__CHECKEDOUT,
                'post_author' => 1,
                'post_title' => __('Payment completed', GSSC),
                'post_content' => __('Payment is complete.', GSSC) . '

' . __('Thank you very much.', GSSC) . '
' . __('Thank you for your continued support.', GSSC),
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            get_pages('include=' . $page_id);
        }
        // 固定ページ「キャンセル完了」が未登録の場合
        if (!get_page_by_path(SimpleStripeCheckout::SLUG__CANCEL)) {
            // キャンセル完了の固定ページを作成
            $page_id = wp_insert_post(array(
                'post_name' => SimpleStripeCheckout::SLUG__CANCEL,
                'post_author' => 1,
                'post_title' => __('Cancellation completed', GSSC),
                'post_content' => __('We have accepted the cancellation of payment.', GSSC) . '

' . __('Thank you very much.', GSSC) . '
' . __('If you have another chance, thank you.', GSSC),
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            get_pages('include=' . $page_id);
        }
        // 固定ページ「決済確定完了」が未登録の場合
        if (!get_page_by_path(SimpleStripeCheckout::SLUG__CAPTURE_COMPLETE)) {
            // 決済確定完了の固定ページを作成
            $page_id = wp_insert_post(array(
                'post_name' => SimpleStripeCheckout::SLUG__CAPTURE_COMPLETE,
                'post_author' => 1,
                'post_title' => __('Settlement confirmed', GSSC),
                'post_content' => __('The payment is confirmed and the payment is completed.', GSSC) . '

' . __('Thank you very much.', GSSC) . '
' . __('Thank you for your continued support.', GSSC),
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            get_pages('include=' . $page_id);
        }
        // 固定ページ「定期支払キャンセル完了」が未登録の場合
        if (!get_page_by_path(SimpleStripeCheckout::SLUG__CANCELED_SUBSCRIPTION)) {
            // 定期支払キャンセル完了の固定ページを作成
            $page_id = wp_insert_post(array(
                'post_name' => SimpleStripeCheckout::SLUG__CANCELED_SUBSCRIPTION,
                'post_author' => 1,
                'post_title' => __('Recurring payment cancellation completed', GSSC),
                'post_content' => __('I canceled the registered regular payment.', GSSC) . '
' . __('Thank you for using.', GSSC) . '

[gssc-product-name]',
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            get_pages('include=' . $page_id);
        }
        // 固定ページ「Stripeカスタマーポータル」が未登録の場合
        if (!get_page_by_path(SimpleStripeCheckout::SLUG__CUSTOMER_PORTAL)) {
            // Stripeカスタマーポータルの固定ページを作成
            $page_id = wp_insert_post(array(
                'post_name' => SimpleStripeCheckout::SLUG__CUSTOMER_PORTAL,
                'post_author' => 1,
                'post_title' => __('Stripe Customer Portal', GSSC),
                'post_content' =>
                    '<form method="POST" action="' . home_url() . '/?' . SimpleStripeCheckout::SLUG__CUSTOMER_PORTAL_ACCESS . '=true">' .
                    __('Subscription ID', GSSC) . "（" . __('Included in the payment email', GSSC) . "）" .
                    ':<br><input type="text" name="subscription_id" value=""><br><br>' .
                    __('Last 4 digits of registered credit card', GSSC) .
                    ':<br><input type="password" name="last4" value=""><br><br><button type="submit">' .
                    __('Go to Customer Portal', GSSC) .
                    '</button></form>',
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            get_pages('include=' . $page_id);
        }
        //
    };
    if (is_multisite() && $network_wide) {
        foreach (get_sites(['fields'=>'ids']) as $blog_id) {
            switch_to_blog($blog_id);
            $initialize_pages();
        }
        restore_current_blog();
    }
    else {
        $initialize_pages();
    }
});

// WordPressの読み込みが完了してヘッダーが送信される前に実行するアクションに、
// SimpleStripeCheckoutクラスのインスタンスを生成するStatic関数をフック
add_action('init', 'SimpleStripeCheckout::instance');

/**
 * SimpleStripeCheckoutプラグインの商品情報モデルクラス
 */
class SimpleStripeCheckout_Product {

    /**
     * プロパティ：商品コード
     */
    public $code;

    /**
     * プロパティ：商品価格
     */
    public $price;

    /**
     * プロパティ：商品提供者名
     */
    public $provider_name;

    /**
     * プロパティ：商品名
     */
    public $name;

    /**
     * プロパティ：商品通貨
     */
    public $currency;

    /**
     * プロパティ：商品ボタン名
     */
    public $button_name;

    /**
     * プロパティ：商品請求タイミング
     */
    public $billing_timing;

    /**
     * プロパティ：商品請求間隔
     */
    public $billing_frequency;

    /**
     * プロパティ：支払サイクル
     */
    public $billing_cycle;

    /**
     * プロパティ：商品無料トライアル
     */
    public $free_trial_days;
}

/**
 * SimpleStripeCheckoutプラグインの商品情報一覧クラス
 */
class SimpleStripeCheckout_ProductList {

    private $id;
    private $items;
    private $type;

    public function getItems() {
        return $this->items;
    }

    public function getType() {
        return $this->type;
    }

    public function __construct($type, $items) {
        $this->type = $type;
        $this->items = $items;
    }

}

class SimpleStripeCheckout_ProductListTable extends WP_List_Table {

    private $product_list;

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 必ず親のコンストラクタを呼ぶ
        parent::__construct();
    }

    /**
     * 商品情報リストをセット
     */
    public function set_product_list($product_list) {
        $this->product_list = $product_list;
    }

    /**
     *
     */
    public function prepare_items() {
        $info = new SimpleStripeCheckout_ProductList(__('Product list', GSSC), $this->product_list);
        $this->items = $info->getItems();

        // 検索
        $s = isset($_REQUEST['s']) ? (string)$_REQUEST['s'] : '';
        if (!empty($s)) {
            $this->items = array_filter($this->items, function($item) use($s) {
                return
                    strpos($item->code, $s) ||
                    strpos($item->price, $s) ||
                    strpos($item->provider_name, $s) ||
                    strpos($item->name, $s) ||
                    strpos($item->currency, $s) ||
                    strpos($item->button_name, $s) ||
                    strpos($item->billing_timing, $s) ||
                    strpos($item->billing_frequency, $s) ||
                    strpos($item->billing_cycle, $s) ||
                    strpos($item->free_trial_days, $s);
            });
        }

        // ソート関数
        $sort = function($a, $b, $bigA){
            if($a === $b) return 0;
            // $bigAが1なら昇順、-1なら降順
            return $a > $b ? $bigA : -$bigA;
        };

        $orderby  = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'code';
        $order    = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
        $orderDir = $order === 'asc' ? 1 : -1;

        $fnames = [
            'code'          => function($item){ return $item->code;              },
            'name'          => function($item){ return $item->name;              },
            'provider_name' => function($item){ return $item->provider_name;    },
            'price'         => function($item){ return $item->price;             },
            'currency'      => function($item){ return $item->currency;          },
            'button_name'   => function($item){ return $item->button_name;       },
            'billing'       => function($item){
              $s = $item->billing_timing;

              return $s;
            },
            'billing_frequency' => function($item){ return $item->billing_frequency; },
            'billing_cycle' => function($item){ return $item->billing_cycle; },
            'free_trial_days'   => function($item){ return $item->free_trial_days;   }
        ];

        $getter = isset($fnames[$orderby]) ? $fnames[$orderby] : null;

        if ($getter) {
            usort(
                $this->items,
                function($a, $b) use($getter, $sort, $orderDir) {
                    return $sort($getter($a), $getter($b), $orderDir);
                }
            );
        }
        // ページネーションを使う場合は設定（「'total_pages' => 5」を設定しない場合は「ceil(total_items / per_page)」となる）
        $this->set_pagination_args(['total_items' => count($this->items), 'per_page' => 10]);
        // ページ数を取得
        $pageLen = $this->get_pagination_arg('total_pages');
        // 現在のページ($_REQUEST['paged'])を取得、範囲を外れると修正される
        $paged = $this->get_pagenum();
        // 1ページあたりの件数
        $per_page = $this->get_pagination_arg('per_page');
        // ページネーションを独自に計算
        $this->items = array_slice($this->items, $per_page * ($paged - 1), $per_page);
    }

    /**
     * 商品情報リストテーブルの左上のアクションリスト表示
     */
    protected function get_bulk_actions() {
        return ['delete' => __('Delete', GSSC)];
    }

    /**
     * 商品情報リストテーブルの上部中央に配置するその他のHTML
     */
    protected function extra_tablenav($witch) {
        echo "<div class=\"alignleft actions bulkactions\"></div>";
    }

    public function get_columns() {
        return [
            'cb' => __('Checkbox', GSSC),
            'name' => __('Product name', GSSC),
            'code' => __('Product code', GSSC),
            'short_code' => __('Shortcode', GSSC),
            'provider_name' => __('Provider', GSSC),
            'price' => __('Price', GSSC),
            'currency' => __('Currency', GSSC),
            'button_name' => __('Button label', GSSC),
            'subscription' => __('Continuation', GSSC)
        ];
    }

    protected function column_cb($item) {
        $code = $item->code;
        return "<input type=\"checkbox\" name=\"checked[]\" value=\"{$code}\" />";
    }

    protected function column_default($item, $name) {
        switch($name) {
            // ここでcbが呼び出されることはない。
            // cbは特別にcolumn_cb($item)が呼び出される。
            case 'code':          return (string)$item->code;
            case 'short_code':    return '[' . SimpleStripeCheckout::PLUGIN_ID . ' code=' . (string)$item->code . ']';
            case 'name':          return esc_html($item->name);
            case 'provider_name': return esc_html($item->provider_name);
            case 'price':         return (string)$item->price;
            case 'currency':      return esc_html($item->currency);
            case 'button_name':   return esc_html($item->button_name);
            case 'subscription':  return esc_html($item->subscription);
        }
    }

    protected function column_name($item) {
        $name = esc_html($item->name);
        return "<strong>{$name}</strong>";
    }

    protected function column_short_code($item) {
        return
            '<p style="cursor:pointer;" alt="' . __('Click to copy.', GSSC) . '" title="' . __('Click to copy.', GSSC) . '" onclick="' .  "\n" .
            // seletionオブジェクトを取得
            'var selection = window.getSelection();' . "\n" .
            // rangeオブジェクトを生成
            'var range = document.createRange();' . "\n" .
            // rangeオブジェクトにp要素を与える
            'range.selectNodeContents(this);' . "\n" .
            // 一旦selectionオブジェクトの持つrangeオブジェクトを削除
            'selection.removeAllRanges();' . "\n" .
            // 上記で生成したrangeオブジェクトをselectionオブジェクトに改めて追加
            'selection.addRange(range);' . "\n" .
            // クリップボードにコピーします。
            'var succeeded = document.execCommand(' . "'copy'" . ');' . "\n" .
            // コピーに成功した場合の処理です。
            'if (succeeded) alert(' . "'" . __('The shortcode was copied.', GSSC) . "'" . ');' . "\n" .
            // selectionオブジェクトの持つrangeオブジェクトを全て削除
            'selection.removeAllRanges();' . "\n" .
            '">[' . SimpleStripeCheckout::PLUGIN_ID . ' code=' . $item->code . ']</p>';
    }

    protected function column_subscription($item) {
        $billing_timing = esc_html($item->billing_timing);
        $subscription = __('Lump sum', GSSC);
        if ($billing_timing === 'subscription') {
            $subscription = __('Subscription', GSSC);
            $billing_frequency = esc_html($item->billing_frequency);
            if ($billing_frequency === 'monthly') {
                $subscription .= '(' . __('Monthly', GSSC) . ')';
            } else if ($billing_frequency === 'yearly') {
                $subscription .= '(' . __('Yearly', GSSC) . ')';
            }
            $billing_cycle = esc_html($item->billing_cycle);
            if (strlen($billing_cycle) > 0 && $billing_cycle > 0) {
                $subscription .= '(' . $billing_cycle . __('Cycle', GSSC) . ')';
            } else {
                $subscription .= '(' . __('Indefinite', GSSC) . ')';
            }
        }
        return "{$subscription}";
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
        if( $column_name === $primary ) {
            $actions = [
                'edit'   => '<a href="?page=' . SimpleStripeCheckout::SLUG__PRODUCT_EDIT_FORM . '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_CODE . '=' . $item->code . '">' . __('Edit', GSSC) . '</a>',
                'copy'   => '<a href="?page=' . SimpleStripeCheckout::SLUG__PRODUCT_EDIT_FORM .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_PRICE             . '=' . $item->price .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_PROVIDER_NAME     . '=' . $item->provider_name .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_NAME              . '=' . $item->name .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_CURRENCY          . '=' . $item->currency .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_BUTTON_NAME       . '=' . $item->button_name .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_BILLING_TIMING    . '=' . $item->billing_timing .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_BILLING_FREQUENCY . '=' . $item->billing_frequency .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_BILLING_CYCLE . '=' . $item->billing_cycle .
                            '&' . SimpleStripeCheckout::PARAMETER__PRODUCT_FREE_TRIAL_DAYS   . '=' . $item->free_trial_days .
                            '">' . __('Duplicate', GSSC) . '</a>',
                'delete' => '<a href="?page=' . SimpleStripeCheckout::SLUG__PRODUCT_LIST . '&action=delete&checked[]=' . $item->code . '" onclick="return confirm(\'「' . $item->name . '」' . __(': Do you want to delete it?', GSSC) . '\')">' . __('Delete', GSSC) . '</a>'
            ];
            // div class = raw-actions がキモ
            return $this->row_actions($actions);
        }
    }

    /**
     * 並び替えのできるカラム
     */
    protected function get_sortable_columns() {
        return [
            'code'          => 'code',
            'short_code'    => 'short_code',
            'name'          => 'name',
            'provider_name' => 'provider_name',
            'price'         => 'price',
            'currency'      => 'currency',
            'button_name'   => 'button_name',
            'subscription'  => 'subscription'
        ];
    }
}

class SimpleStripeCheckout {

    /**
     * このプラグインのバージョン
     */
    const VERSION = '1.1.0';

    /**
     * このプラグインのID：Growniche Simple Stripe Checkout
     */
    const PLUGIN_ID = 'gssc';

    /**
     * ショートコード（商品名）
     */
    const GSSC_PRODUCT_NAME = self::PLUGIN_ID . '-product-name';

    /**
     * このプラグインのスクリプトのハンドル名
     */
    const SCRIPT_HANDLE = self::PLUGIN_ID . '-js';

    /**
     * CredentialAction（プレフィックス）
     */
    const CREDENTIAL_ACTION = self::PLUGIN_ID . '-nonce-action_';

    /**
     * CredentialAction：初期設定
     */
    const CREDENTIAL_ACTION__INITIAL_CONFIG = self::CREDENTIAL_ACTION . 'initial-config';

    /**
     * CredentialAction：商品情報編集
     */
    const CREDENTIAL_ACTION__PRODUCT_EDIT = self::CREDENTIAL_ACTION . 'product-edit';

    /**
     * CredentialAction：メール設定
     */
    const CREDENTIAL_ACTION__MAIL_CONFIG = self::CREDENTIAL_ACTION . 'mail-config';

    /**
     * CredentialAction：Webhook設定
     */
    const CREDENTIAL_ACTION__HOOK_CONFIG = self::CREDENTIAL_ACTION . 'hook-config';

    /**
     * CredentialName（プレフィックス）
     */
    const CREDENTIAL_NAME = self::PLUGIN_ID . '-nonce-key_';

    /**
     * CredentialName：初期設定
     */
    const CREDENTIAL_NAME__INITIAL_CONFIG = self::CREDENTIAL_NAME . 'initial-config';

    /**
     * CredentialName：商品情報編集
     */
    const CREDENTIAL_NAME__PRODUCT_EDIT = self::CREDENTIAL_NAME . 'product-edit';

    /**
     * CredentialName：メール設定
     */
    const CREDENTIAL_NAME__MAIL_CONFIG = self::CREDENTIAL_NAME . 'mail-config';

    /**
     * CredentialName：Webhook設定
     */
    const CREDENTIAL_NAME__HOOK_CONFIG = self::CREDENTIAL_NAME . 'hook-config';

    /**
     * (23文字)
     */
    const PLUGIN_PREFIX = self::PLUGIN_ID . '_';

    /**
     * OPTIONSキー：決済完了ページのSLUG
     * ※OPTIONSテーブルにセットする際のキー
     */
    const OPTION_KEY__CHECKEDOUT_PAGE_SLUG = self::PLUGIN_PREFIX . 'checkedout-page-slug';

    /**
     * OPTIONSキー：キャンセル完了ページのSLUG
     * ※OPTIONSテーブルにセットする際のキー
     */
    const OPTION_KEY__CANCEL_PAGE_SLUG = self::PLUGIN_PREFIX . 'cancel-page-slug';

    /**
     * OPTIONSキー：決済確定完了ページのSLUG
     * ※OPTIONSテーブルにセットする際のキー
     */
    const OPTION_KEY__CAPTURE_COMPLETE_PAGE_SLUG = self::PLUGIN_PREFIX . 'capture-complete-page-slug';

    /**
     * OPTIONSキー：定期支払キャンセル完了ページのSLUG
     * ※OPTIONSテーブルにセットする際のキー
     */
    const OPTION_KEY__CANCELED_SUBSCRIPTION_PAGE_SLUG = self::PLUGIN_PREFIX . 'canceled-subscription-page-slug';

    /**
     * OPTIONSキー：[初期設定] STRIPEの公開キー
     * ※OPTIONSテーブルにセットする際のキー
     */
    const OPTION_KEY__STRIPE_PUBLIC_KEY = self::PLUGIN_PREFIX . 'stripe-public-key';

    /**
     * OPTIONSキー：[初期設定] STRIPEのシークレットキー
     */
    const OPTION_KEY__STRIPE_SECRET_KEY = self::PLUGIN_PREFIX . 'stripe-secret-key';

    /**
     * OPTIONSキー：[商品情報編集] 商品情報リスト
     */
    const OPTION_KEY__PRODUCT_LIST = self::PLUGIN_PREFIX . 'product-list';

    /**
     * OPTIONSキー：[メール設定] 販売者向け受信メルアド
     */
    const OPTION_KEY__SELLER_RECEIVE_ADDRESS = self::PLUGIN_PREFIX . 'seller_receive_address';

    /**
     * OPTIONSキー：[メール設定] 販売者向け送信元メルアド
     */
    const OPTION_KEY__SELLER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'seller_from_address';

    /**
     * OPTIONSキー：[メール設定] 購入者向け送信元メルアド
     */
    const OPTION_KEY__BUYER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'buyer_from_address';

    /**
     * OPTIONSキー：[メール設定] 即時決済フラグ
     */
    const OPTION_KEY__IMMEDIATE_SETTLEMENT = self::PLUGIN_PREFIX . 'immediate_settlement';

    /**
     * OPTIONSキー：[メール設定] StripeカスタマーポータルログインURL
     */
    const OPTION_KEY__STRIPE_CUSTOMER_PORTAL_LOGIN_URL = self::PLUGIN_PREFIX . 'stripe_customer_portal_login_url';

    /**
     * 画面のslug：トップ
     */
    const SLUG__TOP = self::PLUGIN_ID;

    /**
     * 画面のslug：初期設定
     */
    const SLUG__INITIAL_CONFIG_FORM = self::PLUGIN_PREFIX . 'initial-config-form';

    /**
     * 画面のslug：商品情報リスト
     */
    const SLUG__PRODUCT_LIST = self::PLUGIN_PREFIX . 'product-list';

    /**
     * 画面のslug：商品情報編集
     */
    const SLUG__PRODUCT_EDIT_FORM = self::PLUGIN_PREFIX . 'product-edit-form';

    /**
     * 画面のslug：メール設定
     */
    const SLUG__MAIL_CONFIG_FORM = self::PLUGIN_PREFIX . 'mail-config-form';

    /**
     * 画面のslug：Webhook設定
     */
    const SLUG__HOOK_CONFIG_FORM = self::PLUGIN_PREFIX . 'hook-config-form';

    /**
     * 画面のslug：STRIPE与信枠の確保
     */
    const SLUG__CHECKOUT = self::PLUGIN_PREFIX . 'checkout';

    /**
     * 画面のslug：STRIPE与信枠の確保キャンセル
     */
    const SLUG__REFUND = self::PLUGIN_PREFIX . 'refund';

    /**
     * 画面のslug：STRIPEサブスクリプションのキャンセル
     */
    const SLUG__CANCEL_SUBSCRIPTION = self::PLUGIN_PREFIX . 'cancel-subscription';

    /**
     * 画面のslug：STRIPE定期支払キャンセル完了（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CANCELED_SUBSCRIPTION = self::PLUGIN_ID . '-' . 'canceled-subscription';

    /**
     * 画面のslug：STRIPEカスタマーポータル（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CUSTOMER_PORTAL = self::PLUGIN_ID . '-' . 'cp';

    /**
     * 画面のslug：STRIPEカスタマーポータル遷移処理（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CUSTOMER_PORTAL_ACCESS = self::PLUGIN_ID . '-' . 'cp-access';

    /**
     * 画面のslug：STRIPEサブスクリプションの毎月/毎年の支払完了
     */
    const SLUG__PAY_SUBSCRIPTION = self::PLUGIN_PREFIX . 'pay-subscription';

    /**
     * 画面のslug：STRIPE決済完了（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CHECKEDOUT = self::PLUGIN_ID . '-' . 'checkedout';

    /**
     * 画面のslug：STRIPEキャンセル完了（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CANCEL = self::PLUGIN_ID . '-' . 'cancel';

    /**
     * 画面のslug：STRIPE決済の確定
     */
    const SLUG__CAPTURE = self::PLUGIN_PREFIX . 'capture';

    /**
     * 画面のslug：STRIPE決済の確定完了（パーマリンクにアンスコを使用できなかったのでハイフンを使用）
     */
    const SLUG__CAPTURE_COMPLETE = self::PLUGIN_ID . '-' . 'capture-complete';

    /**
     * パラメータ名：[初期設定] Stripeの公開キー
     */
    const PARAMETER__STRIPE_PUBLIC_KEY = self::PLUGIN_PREFIX . 'stripe-public-key';

    /**
     * パラメータ名：[初期設定] Stripeのシークレットキー
     */
    const PARAMETER__STRIPE_SECRET_KEY = self::PLUGIN_PREFIX . 'stripe-secret-key';

    /**
     * パラメータ名：[商品情報編集] 商品コード
     */
    const PARAMETER__PRODUCT_CODE = self::PLUGIN_PREFIX . 'product-code';

    /**
     * パラメータ名：[商品情報編集] 商品価格
     */
    const PARAMETER__PRODUCT_PRICE = self::PLUGIN_PREFIX . 'product-price';

    /**
     * パラメータ名：[商品情報編集] 商品提供者
     */
    const PARAMETER__PRODUCT_PROVIDER_NAME = self::PLUGIN_PREFIX . 'product-provider-name';

    /**
     * パラメータ名：[商品情報編集] 商品名
     */
    const PARAMETER__PRODUCT_NAME = self::PLUGIN_PREFIX . 'product-name';

    /**
     * パラメータ名：[商品情報編集] 商品通貨
     */
    const PARAMETER__PRODUCT_CURRENCY = self::PLUGIN_PREFIX . 'product-currency';

    /**
     * パラメータ名：[商品情報編集] 商品ボタン名
     */
    const PARAMETER__PRODUCT_BUTTON_NAME = self::PLUGIN_PREFIX . 'product-button-name';

    /**
     * パラメータ名：[商品情報編集] 商品請求タイミング
     */
    const PARAMETER__PRODUCT_BILLING_TIMING = self::PLUGIN_PREFIX . 'product-billing-timing';

    /**
     * パラメータ名：[商品情報編集] 商品請求間隔
     */
    const PARAMETER__PRODUCT_BILLING_FREQUENCY = self::PLUGIN_PREFIX . 'product-billing-frequency';

    /**
     * パラメータ名：[商品情報編集] 支払回数
     */
    const PARAMETER__PRODUCT_BILLING_CYCLE = self::PLUGIN_PREFIX . 'product-billing-cycle';

    /**
     * パラメータ名：[商品情報編集] 商品無料トライアル
     */
    const PARAMETER__PRODUCT_FREE_TRIAL_DAYS = self::PLUGIN_PREFIX . 'product-free-trial-days';

    /**
     * パラメータ名：[メール設定] 販売者向け受信メルアド
     */
    const PARAMETER__SELLER_RECEIVE_ADDRESS = self::PLUGIN_PREFIX . 'seller-receive-address';

    /**
     * パラメータ名：[メール設定] 販売者向け送信元メルアド
     */
    const PARAMETER__SELLER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'seller-from-address';

    /**
     * パラメータ名：[メール設定] 購入者向け送信元メルアド
     */
    const PARAMETER__BUYER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'buyer-from-address';

    /**
     * パラメータ名：[メール設定] 即時決済フラグ
     */
    const PARAMETER__IMMEDIATE_SETTLEMENT = self::PLUGIN_PREFIX . 'immediate_settlement';

    /**
     * パラメータ名：[メール設定] StripeカスタマーポータルログインURL
     */
    const PARAMETER__STRIPE_CUSTOMER_PORTAL_LOGIN_URL = self::PLUGIN_PREFIX . 'stripe_customer_portal_login_url';

    /**
     * TRANSIENTキー(一時入力値)：[初期設定] Stripeの公開キー
     * ※4文字+41文字以下
     */
    const TRANSIENT_KEY__TEMP_PUBLIC_KEY = self::PLUGIN_PREFIX . 'temp-public-key';

    /**
     * TRANSIENTキー(一時入力値)：[初期設定] Stripeのシークレットキー
     */
    const TRANSIENT_KEY__TEMP_SECRET_KEY = self::PLUGIN_PREFIX . 'temp-secret-key';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品コード
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_CODE = self::PLUGIN_PREFIX . 'temp-product-code';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品価格
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_PRICE = self::PLUGIN_PREFIX . 'temp-product-price';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品提供者名
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_PROVIDER_NAME = self::PLUGIN_PREFIX . 'temp-product-provider-name';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品名
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_NAME = self::PLUGIN_PREFIX . 'temp-product-name';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品通貨
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_CURRENCY = self::PLUGIN_PREFIX . 'temp-product-currency';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品ボタン名
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_BUTTON_NAME = self::PLUGIN_PREFIX . 'temp-product-button-name';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品請求タイミング
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_BILLING_TIMING = self::PLUGIN_PREFIX . 'temp-product-billing-timing';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品請求間隔
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_BILLING_FREQUENCY = self::PLUGIN_PREFIX . 'temp-product-billing-frequency';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 支払回数
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_BILLING_CYCLE = self::PLUGIN_PREFIX . 'temp-product-billing-cycle';

    /**
     * TRANSIENTキー(一時入力値)：[商品情報編集] 商品無料トライアル
     */
    const TRANSIENT_KEY__TEMP_PRODUCT_FREE_TRIAL_DAYS = self::PLUGIN_PREFIX . 'temp-product-free-trial-days';

    /**
     * TRANSIENTキー(一時入力値)：[メール設定] 販売者向け受信メルアド
     */
    const TRANSIENT_KEY__TEMP_SELLER_RECEIVE_ADDRESS = self::PLUGIN_PREFIX . 'temp-seller-receive-address';

    /**
     * TRANSIENTキー(一時入力値)：[メール設定] 販売者向け送信元メルアド
     */
    const TRANSIENT_KEY__TEMP_SELLER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'temp-seller-from-address';

    /**
     * TRANSIENTキー(一時入力値)：[メール設定] 購入者向け送信元メルアド
     */
    const TRANSIENT_KEY__TEMP_BUYER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'temp-buyer-from-address';

    /**
     * TRANSIENTキー(一時入力値)：[メール設定] 即時決済フラグ
     */
    const TRANSIENT_KEY__TEMP_IMMEDIATE_SETTLEMENT = self::PLUGIN_PREFIX . 'temp-immediate_settlement';

    /**
     * TRANSIENTキー(一時入力値)：[メール設定] StripeカスタマーポータルログインURL
     */
    const TRANSIENT_KEY__TEMP_STRIPE_CUSTOMER_PORTAL_LOGIN_URL = self::PLUGIN_PREFIX . 'temp-stripe-customer-portal-login-url';

    /**
     * TRANSIENTキー(不正メッセージ)：[初期設定] Stripeの公開キー
     */
    const TRANSIENT_KEY__INVALID_PUBLIC_KEY = self::PLUGIN_PREFIX . 'invalid-public-key';

    /**
     * TRANSIENTキー(不正メッセージ)：[初期設定] Stripeのシークレットキー
     */
    const TRANSIENT_KEY__INVALID_SECRET_KEY = self::PLUGIN_PREFIX . 'invalid-secret-key';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品価格
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_PRICE = self::PLUGIN_PREFIX . 'invalid-product-price';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品提供者名
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_PROVIDER_NAME = self::PLUGIN_PREFIX . 'invalid-product-provider-name';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品名
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_NAME = self::PLUGIN_PREFIX . 'invalid-product-name';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品通貨
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_CURRENCY = self::PLUGIN_PREFIX . 'invalid-product-currency';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品ボタン名
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_BUTTON_NAME = self::PLUGIN_PREFIX . 'invalid-product-button-name';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品請求タイミング
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_BILLING_TIMING = self::PLUGIN_PREFIX . 'invalid-product-billing-timing';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品請求間隔
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_BILLING_FREQUENCY = self::PLUGIN_PREFIX . 'invalid-product-billing-frequency';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 支払回数
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_BILLING_CYCLE = self::PLUGIN_PREFIX . 'invalid-product-billing-cycle';

    /**
     * TRANSIENTキー(不正メッセージ)：[商品情報編集] 商品無料トライアル日数
     */
    const TRANSIENT_KEY__INVALID_PRODUCT_FREE_TRIAL_DAYS = self::PLUGIN_PREFIX . 'invalid-product-free-trial-days';

    /**
     * TRANSIENTキー(不正メッセージ)：[メール設定] 販売者向け受信メルアド
     */
    const TRANSIENT_KEY__INVALID_SELLER_RECEIVE_ADDRESS = self::PLUGIN_PREFIX . 'invalid-seller-receive-address';

    /**
     * TRANSIENTキー(不正メッセージ)：[メール設定] 販売者向け送信元メルアド
     */
    const TRANSIENT_KEY__INVALID_SELLER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'invalid-seller-from-address';

    /**
     * TRANSIENTキー(不正メッセージ)：[メール設定] 購入者向け送信元メルアド
     */
    const TRANSIENT_KEY__INVALID_BUYER_FROM_ADDRESS = self::PLUGIN_PREFIX . 'invalid-buyer-from-address';

    /**
     * TRANSIENTキー(不正メッセージ)：[メール設定] 即時決済フラグ
     */
    const TRANSIENT_KEY__INVALID_IMMEDIATE_SETTLEMENT = self::PLUGIN_PREFIX . 'invalid-immediate-settlement';

    /**
     * TRANSIENTキー(不正メッセージ)：[メール設定] StripeカスタマーポータルログインURL
     */
    const TRANSIENT_KEY__INVALID_STRIPE_CUSTOMER_PORTAL_LOGIN_URL = self::PLUGIN_PREFIX . 'invalid-stripe-customer-portal-login-url';

    /**
     * TRANSIENTキー(エラーメッセージ)：[STRIPE] 商品登録失敗
     */
    const TRANSIENT_KEY__ERROR_STRIPE_PRODUCT_REGISTER = self::PLUGIN_PREFIX . 'error-stripe-product-register';

    /**
     * TRANSIENTキー(エラーメッセージ)：[STRIPE] 価格登録失敗
     */
    const TRANSIENT_KEY__ERROR_STRIPE_PRICE_REGISTER = self::PLUGIN_PREFIX . 'error-stripe-price-register';

    /**
     * TRANSIENTキー(エラーメッセージ)：[STRIPE] Webhook取得失敗
     */
    const TRANSIENT_KEY__ERROR_STRIPE_WEBHOOK_RETRIEVE = self::PLUGIN_PREFIX . 'error-stripe-webhook-retrieve';

    /**
     * TRANSIENTキー(保存完了メッセージ)：初期設定
     */
    const TRANSIENT_KEY__SAVE_INITIAL_CONFIG = self::PLUGIN_PREFIX . 'save-initial-config';

    /**
     * TRANSIENTキー(保存完了メッセージ)：商品情報編集
     */
    const TRANSIENT_KEY__SAVE_PRODUCT_INFO = self::PLUGIN_PREFIX . 'save-product-info';

    /**
     * TRANSIENTキー(保存完了メッセージ)：メール設定
     */
    const TRANSIENT_KEY__SAVE_MAIL_CONFIG = self::PLUGIN_PREFIX . 'save-mail-config';

    /**
     * TRANSIENTキー(保存完了メッセージ)：Webhook設定
     */
    const TRANSIENT_KEY__SAVE_HOOK_CONFIG = self::PLUGIN_PREFIX . 'save-hook-config';

    /**
     * TRANSIENTのタイムリミット：5秒
     */
    const TRANSIENT_TIME_LIMIT = 5;

    /**
     * 通知タイプ：エラー
     */
    const NOTICE_TYPE__ERROR = 'error';

    /**
     * 通知タイプ：警告
     */
    const NOTICE_TYPE__WARNING = 'warning';

    /**
     * 通知タイプ：成功
     */
    const NOTICE_TYPE__SUCCESS = 'success';

    /**
     * 通知タイプ：情報
     */
    const NOTICE_TYPE__INFO = 'info';

    /**
     * 暗号化する時のパスワード：STRIPEの公開キーとシークレットキーの複合化で使用
     */
    const ENCRYPT_PASSWORD = 's9YQReXd';

    /**
     * 正規表現(部分)：メルアド
     */
    const REGEXP_ADDRESS = "[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*";

    /**
     * 正規表現：単一メルアド
     */
    const REGEXP_SINGLE_ADDRESS = "/^" . self::REGEXP_ADDRESS . "$/";

    /**
     * 正規表現：複数メルアド(カンマ区切り)
     */
    const REGEXP_MULTIPLE_ADDRESS = "/^" . self::REGEXP_ADDRESS . "([ ]*,[ ]*" . self::REGEXP_ADDRESS . ")*$/";

    /**
     * プロパティ名: ラベル
     */
    const LABEL = 'label';

    /**
     * 商品請求タイミング: 一括
     */
    const STRIPE_BILLING_TIMING__LUMP_SUM = 'lump-sum-payment';

    /**
     * 商品請求タイミング: 定期
     */
    const STRIPE_BILLING_TIMING__SUBSCRIPTION = 'subscription';

    /**
     * 商品請求頻度リスト: 月次
     */
    const STRIPE_BILLING_FREQUENCY__MONTHLY = 'monthly';

    /**
     * 商品請求頻度リスト: 年次
     */
    const STRIPE_BILLING_FREQUENCY__YEARLY = 'yearly';

    /**
     * WordPressの読み込みが完了してヘッダーが送信される前に実行するアクションにフックする、
     * SimpleStripeCheckoutクラスのインスタンスを生成するStatic関数
     */
    static function instance() {
        return new self();
    }

    /**
     * 固定ページのスラッグの重複回避処理
     * @param slug_prefix 重複を回避したいスラッグのプレフィックス
     * @param slug_suffix [参照渡し] 重複を回避した結果のスラッグのサフィックス
     * @param target 比較対象のスラッグ
     */
    static function avoidDuplication($slug_prefix, &$slug_suffix, $target) {
        preg_match('/^' . $slug_prefix . '(-([0-9]+))?$/', $target, $date_match);
        if (count($date_match) == 1) {
            if ($slug_suffix <= 2) {
                $slug_suffix = 2;
            }
        } else if (count($date_match) == 3) {
            if (intval($date_match[2]) > $slug_suffix) {
                $new_slug_suffix = intval($date_match[2]) + 1;
                if ($slug_suffix < $new_slug_suffix) {
                    $slug_suffix = $new_slug_suffix;
                }
            }
        }
    }

    /**
     * 通知タグを生成・取得
     * @param message 通知するメッセージ
     * @param type 通知タイプ(error/warning/success/info)
     * @retern 通知タグ(HTML)
     */
    static function getNotice($message, $type) {
        return
            '<div class="notice notice-' . $type . ' is-dismissible">' .
            '<p><strong>' . $message . '</strong></p>' .
            '<button type="button" class="notice-dismiss">' .
            '<span class="screen-reader-text">Dismiss this notice.</span>' .
            '</button>' .
            '</div>';
    }

    /**
     * 複合化：AES 256
     * @param edata 暗号化してBASE64にした文字列
     * @param string 複合化のパスワード
     * @return 複合化された文字列
     */
    static function decrypt($edata, $password) {
        $data = base64_decode($edata);
        $salt = substr($data, 0, 16);
        $ct = substr($data, 16);
        $rounds = 3; // depends on key length
        $data00 = $password.$salt;
        $hash = array();
        $hash[0] = hash('sha256', $data00, true);
        $result = $hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $hash[$i] = hash('sha256', $hash[$i - 1].$data00, true);
            $result .= $hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32,16);
        return openssl_decrypt($ct, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * crypt AES 256
     *
     * @param data $data
     * @param string $password
     * @return base64 encrypted data
     */
    static function encrypt($data, $password) {
        // Set a random salt
        $salt = openssl_random_pseudo_bytes(16);
        $salted = '';
        $dx = '';
        // Salt the key(32) and iv(16) = 48
        while (strlen($salted) < 48) {
          $dx = hash('sha256', $dx.$password.$salt, true);
          $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv  = substr($salted, 32,16);
        $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($salt . $encrypted_data);
    }

    /**
     * HTMLのOPTIONタグを生成・取得
     */
    static function makeHtmlSelectOptions($list, $selected, $label = null) {
        $html = '';
        foreach ($list as $key => $value) {
            $html .= '<option class="level-0" value="' . $key . '"';
            if ($key == $selected) {
                $html .= ' selected="selected';
            }
            $html .= '">' . (is_null($label) ? $value : $value[$label]) . '</option>';
        }
        return $html;
    }

    /**
     * HTMLのRADIOタグを生成・取得
     */
    static function makeHtmlRadios($list, $name, $selected, $label = null, $separate = '') {
        $html = '';
        foreach ($list as $key => $value) {
            $html .= '<input type="radio" name="' . $name . '"value="' . $key . '"';
            if ($key == $selected) {
                $html .= ' checked';
            }
            $html .= '">' . (is_null($label) ? $value : $value[$label]) . '</option>';
            $html .= $separate;
        }
        return $html;
    }

    /**
     * 購入者向け与信枠確保メール本文テンプレート
     */
    static function buyer_lump_sum_payment_mail_template($email, $service_name, $amount, $last4, $cancel_url, $site_name, $home_url, $buyer_from_address, $immediate_settlement, $stripe_customer_portal_login_url) {
        return "
${email} " . __('Mr/Ms', GSSC) . "

" . __('Thank you for this time.', GSSC) . "
" . __('Payment was made with the following contents.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Stripe Customer Portal', GSSC) . "
${stripe_customer_portal_login_url}
※" . __('You can change your card, registered e-mail address, etc.', GSSC) . "

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

" . ($immediate_settlement ? "" : ("<<" . __('Cancellation of payment', GSSC) . ">>
" . __('You can cancel within 24 hours after confirming payment by clicking below.', GSSC) . "
${cancel_url}
※" . __('After that, a cancellation fee will be charged.', GSSC) . "
")) . "
------
${site_name}
${home_url}

" . __('Contact for payment', GSSC) . "
${buyer_from_address}
";
    }

    /**
     * 購入者向けサブスクリプション登録完了メール本文テンプレート
     */
    static function buyer_subscription_mail_template($email, $service_name, $amount, $last4, $cancel_url, $site_name, $home_url, $buyer_from_address, $billing_frequency, $billing_cycle, $trial_end_date, $subscription_id, $stripe_customer_portal_login_url) {
        return "
${email} " . __('Mr/Ms', GSSC) . "

" . __('Thank you for this time.', GSSC) . "
" . __('From now on, payment will be made with the following contents.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Billing frequency', GSSC) . "
${billing_frequency}

▼" . __('Billing cycle', GSSC) . "
${billing_cycle}

▼" . __('First scheduled withdrawal date', GSSC) . "
${trial_end_date}

▼" . __('Cancellation of subscription', GSSC) . "
" . __('If you want to cancel, click below to cancel.', GSSC) . "
${cancel_url}

▼" . __('Stripe Customer Portal', GSSC) . "
${stripe_customer_portal_login_url}
※" . __('You can change your card, registered e-mail address, etc.', GSSC) . "

▼" . __('Subscription ID', GSSC) . "
${subscription_id}

▼" . __('Last 4 digits of the card to be used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}

" . __('Contact for payment', GSSC) . "
${buyer_from_address}
";
    }

    /**
     * 販売者向け与信枠確保メール本文テンプレート
     */
    static function seller_lump_sum_payment_mail_template($email, $service_name, $amount, $last4, $capture_url, $site_name, $home_url, $immediate_settlement) {
        return "
" . __('Application details', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}
" . ($immediate_settlement ? "" : "
<<" . __('You will not be able to collect it unless you confirm your payment!', GSSC) . ">>
" . __('"Cancellation is possible within 24 hours" is sent by automatic email.', GSSC) . "
" . __('Click below to confirm, so please wait 24 hours.', GSSC) . "
${capture_url}

※" . __('If you cancel after confirmation, you will be charged a Stripe fee.', GSSC) . "
") . "
------
${site_name}
${home_url}
";
    }

    /**
     * 販売者向けサブスクリプション登録完了メール本文テンプレート
     */
    static function seller_subscription_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $billing_frequency, $billing_cycle, $trial_end_date, $subscription_id) {
        return "
" . __('Application details', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Billing frequency', GSSC) . "
${billing_frequency}

▼" . __('Billing cycle', GSSC) . "
${billing_cycle}

▼" . __('First scheduled withdrawal date', GSSC) . "
${trial_end_date}

▼" . __('Subscription ID', GSSC) . "
${subscription_id}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}

";
    }

    /**
     * 購入者向けキャンセルメール本文テンプレート
     */
    static function buyer_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $buyer_from_address, $subscription_id, $stripe_customer_portal_login_url) {
        $subscription = "";
        if ($subscription_id) {
            $subscription = "
▼" . __('Subscription ID', GSSC) . "
${subscription_id}";
        }
        return "
${email} " . __('Mr/Ms', GSSC) . "

" . __('The following contents have been cancelled.', GSSC) . "
" . __('If you apply again, you will need to pay again.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Stripe Customer Portal', GSSC) . "
${stripe_customer_portal_login_url}
※" . __('You can change your card, registered e-mail address, etc.', GSSC) . "
${subscription}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}

" . __('Contact for payment', GSSC) . "
${buyer_from_address}
";
    }

    /**
     * 販売者向けキャンセルメール本文テンプレート
     */
    static function seller_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $subscription_id) {
        $subscription = "";
        if ($subscription_id) {
            $subscription = "
▼" . __('Subscription ID', GSSC) . "
${subscription_id}";
        }
        return "
" . __('The following payment registration has been cancelled.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}
${subscription}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}
";
    }

    /**
     * 購入者向け決済確定メール本文テンプレート
     */
    static function buyer_capture_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $stripe_customer_portal_login_url) {
        return "
${email} " . __('Mr/Ms', GSSC) . "

" . __('Thank you very much for this time.', GSSC) . "

" . __('Since there was no cancellation, payment was confirmed with the following contents.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Stripe Customer Portal', GSSC) . "
${stripe_customer_portal_login_url}
※" . __('You can change your card, registered e-mail address, etc.', GSSC) . "

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}
";
    }

    /**
     * 販売者向け決済確定メール本文テンプレート
     */
    static function seller_capture_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url) {
        return "
" . __('Since the customer has confirmed the payment, the following payment has been made.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}
";
    }

    /**
     * 購入者向けサブスクリプション支払完了メール本文テンプレート
     */
    static function buyer_pay_subscription_mail_template($email, $service_name, $amount, $last4, $next_date, $site_name, $home_url, $buyer_from_address, $subscription_id, $stripe_customer_portal_login_url, $cancel_url) {
        return "
${email} " . __('Mr/Ms', GSSC) . "

${service_name} " . __(': The payment has been made, so you will receive an email to contact you.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Next scheduled withdrawal date', GSSC) . "
${next_date}

▼" . __('Stripe Customer Portal', GSSC) . "
${stripe_customer_portal_login_url}
※" . __('You can change your card, registered e-mail address, etc.', GSSC) . "

▼" . __('Subscription ID', GSSC) . "
${subscription_id}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

▼" . __('Cancellation of subscription', GSSC) . "
" . __('If you want to cancel, click below to cancel.', GSSC) . "
${cancel_url}

------
${site_name}
${home_url}

" . __('Contact for payment', GSSC) . "
${buyer_from_address}
";
    }

    /**
     * 販売者向けサブスクリプション支払完了メール本文テンプレート
     */
    static function seller_pay_subscription_mail_template($email, $service_name, $amount, $last4, $next_date, $site_name, $home_url, $subscription_id) {
        return "
" . __('Since the customer has confirmed the payment, the following payment has been made.', GSSC) . "

▼" . __('Buyer email', GSSC) . "
${email}

▼" . __('Contents', GSSC) . "
${service_name}

▼" . __('Price', GSSC) . "
${amount}

▼" . __('Next scheduled withdrawal date', GSSC) . "
${next_date}

▼" . __('Subscription ID', GSSC) . "
${subscription_id}

▼" . __('Last 4 digits of the card used for payment', GSSC) . "
${last4}

------
${site_name}
${home_url}
";
    }

    /**
     * メール送信処理
     */
    static function send_mail($title, $body, $to, $from) {
        // メールヘッダー
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");
        $header  = "From: " . $from . "\n";
        $header = $header . "Reply-To: " . $from;
        // メール送信
        return mb_send_mail($to, $title, $body, $header, "-f" .$from);
    }

    /**
     * 商品情報リストテーブル
     */
    private $product_list_table;

    /**
     * STRIPE対応通貨リスト
     */
    private $stripe_currencies;

    /**
     * 商品請求タイミング: リスト
     */
    private $stripe_billing_timing_list;

    /**
     * 商品請求頻度: リスト
     */
    private $stripe_billing_frequency_list;

    /**
     * コンストラクタ
     */
    function __construct() {
        // STRIPE対応通貨リスト
        $this->stripe_currencies = array(
            'USD'=>array(self::LABEL=>('USD ($0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'$___'),
            'AUD'=>array(self::LABEL=>('AUD ($0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'$___'),
            'BRL'=>array(self::LABEL=>('BRL (R$0.50'   . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'R$___'),
            'CAD'=>array(self::LABEL=>('CAD ($0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'$___'),
            'CHF'=>array(self::LABEL=>('CHF (0.50 Fr'  . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'___ Fr'),
            'DKK'=>array(self::LABEL=>('DKK (2.50-kr.' . __('or more', GSSC) . ')'), 'min'=>2.50, 'format'=>'___-kr'),
            'EUR'=>array(self::LABEL=>('EUR (€0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'€___'),
            'GBP'=>array(self::LABEL=>('GBP (£0.30'    . __('or more', GSSC) . ')'), 'min'=>0.30, 'format'=>'£___'),
            'HKD'=>array(self::LABEL=>('HKD ($4.00'    . __('or more', GSSC) . ')'), 'min'=>4.00, 'format'=>'$___'),
            'INR'=>array(self::LABEL=>('INR (₹0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'₹___'),
            'JPY'=>array(self::LABEL=>('JPY (￥50'     . __('or more', GSSC) . ')'), 'min'=>50.0, 'format'=>'￥___'),
            'MXN'=>array(self::LABEL=>('MXN ($10'      . __('or more', GSSC) . ')'), 'min'=>10.0, 'format'=>'$___'),
            'MYR'=>array(self::LABEL=>('MYR (RM 2'     . __('or more', GSSC) . ')'), 'min'=>2.00, 'format'=>'RM ___'),
            'NOK'=>array(self::LABEL=>('NOK (3.00-kr.' . __('or more', GSSC) . ')'), 'min'=>3.00, 'format'=>'___-kr.'),
            'NZD'=>array(self::LABEL=>('NZD ($0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'$___'),
            'PLN'=>array(self::LABEL=>('PLN (2.00 zł'  . __('or more', GSSC) . ')'), 'min'=>2.00, 'format'=>'___ zł'),
            'SEK'=>array(self::LABEL=>('SEK (3.00-kr.' . __('or more', GSSC) . ')'), 'min'=>3.00, 'format'=>'___-kr.'),
            'SGD'=>array(self::LABEL=>('SGD ($0.50'    . __('or more', GSSC) . ')'), 'min'=>0.50, 'format'=>'$___')
        );

        // 商品請求タイミング: リスト
        $this->stripe_billing_timing_list = array(
            self::STRIPE_BILLING_TIMING__LUMP_SUM   => array(self::LABEL=>(__('Lump sum', GSSC))),
            self::STRIPE_BILLING_TIMING__SUBSCRIPTION => array(self::LABEL=>(__('Subscription', GSSC)))
        );

        // 商品請求頻度: リスト
        $this->stripe_billing_frequency_list = array(
            'monthly'=>array(self::LABEL=>__('Monthly', GSSC)),
            'yearly'=>array(self::LABEL=>__('Yearly', GSSC))
        );

        // ショートコード処理の前準備
        add_action('wp_enqueue_scripts', [$this, 'pre_short_code']);
        wp_enqueue_script('jquery' );
        // ショートコード処理を登録
        add_shortcode(self::PLUGIN_ID, [$this, 'short_code']);
        add_shortcode(self::GSSC_PRODUCT_NAME, [$this, 'short_code_product_name']);

        // STRIPEの与信枠の確保処理の準備：
        add_rewrite_endpoint(self::SLUG__CHECKOUT, EP_ALL);
        // STRIPEの与信枠の確保キャンセル処理の準備：
        add_rewrite_endpoint(self::SLUG__REFUND, EP_ALL);
        // STRIPEの決済の確定処理の準備：
        add_rewrite_endpoint(self::SLUG__CAPTURE, EP_ALL);
        // STRIPEのサブスクリプションのキャンセル処理の準備
        add_rewrite_endpoint(self::SLUG__CANCEL_SUBSCRIPTION, EP_ALL);
        // STRIPEのサブスクリプションの毎月/毎年の支払完了受信処理の準備
        add_rewrite_endpoint(self::SLUG__PAY_SUBSCRIPTION, EP_ALL);
        // STRIPEカスタマーポータル遷移処理の準備：
        add_rewrite_endpoint(self::SLUG__CUSTOMER_PORTAL_ACCESS, EP_ALL);

        // ページを表示する直前の前処理をフック(STRIPEのチェックアウト処理を追加)
        add_action('template_redirect', [$this, 'on_template_redirect']);

        // 管理画面を表示中、且つ、ログイン済の場合
        if (is_admin() && is_user_logged_in()) {

            // 管理画面メニューの基本構造が配置された後に実行するアクションに、
            // 管理画面のトップメニューページを追加する関数をフック
            add_action('admin_menu', [$this, 'set_plugin_menu']);
            // 管理画面メニューの基本構造が配置された後に実行するアクションに、
            // 管理画面のサブメニューページを追加する関数をフック
            add_action('admin_menu', [$this, 'set_plugin_sub_menu']);

            // 管理画面各ページの最初、ページがレンダリングされる前に実行するアクションに、
            // 初期設定を保存する関数をフック
            add_action('admin_init', [$this, 'save_initial_config']);
            // 管理画面各ページの最初、ページがレンダリングされる前に実行するアクションに、
            // 商品情報を保存する関数をフック
            add_action('admin_init', [$this, 'save_product']);
            // 管理画面各ページの最初、ページがレンダリングされる前に実行するアクションに、
            // メール設定を保存する関数をフック
            add_action('admin_init', [$this, 'save_mail_config']);
            // 管理画面各ページの最初、ページがレンダリングされる前に実行するアクションに、
            // Webhook設定を保存する関数をフック
            add_action('admin_init', [$this, 'save_hook_config']);

        }
    }

    /**
     * ショートコード処理の前準備
     */
    function pre_short_code() {
        wp_enqueue_script(self::SCRIPT_HANDLE, plugin_dir_url(__FILE__) . self::PLUGIN_ID . '.js');
    }

    /**
     * ショートコード処理（購入ボタン）
     */
    function short_code($atts, $content = null) {
        $val = '';
        // ショートコードの引数に商品コードがある場合
        if (is_array($atts) && isset($atts['code']) && intval($atts['code']) > 0) {
            // 即時決済フラグをOPTIONSテーブルから取得
            $immediate_settlement = get_option(self::OPTION_KEY__IMMEDIATE_SETTLEMENT);
            // 即時決済フラグが設定されている場合
            if ($immediate_settlement === 'ON' || $immediate_settlement === 'OFF') {
                // 商品コード
                $product_code = intval($atts['code']);
                // STRIPEの公開キーをOPTIONSテーブルから取得
                $stripe_public_key = self::decrypt(get_option(self::OPTION_KEY__STRIPE_PUBLIC_KEY), self::ENCRYPT_PASSWORD);
                // OPTIONSテーブルから商品情報を取得
                $product = $this->getProduct($product_code);
                // 商品情報がある場合
                if (isset($product)) {
                    // 商品価格
                    $product_price = $product->price;
                    // 商品通貨
                    $product_currency = strtolower($product->currency);
                    // 商品提供者名
                    $product_provider_name = $product->provider_name;
                    // 商品名
                    $product_name = $product->name;
                    // 商品ボタン名
                    $product_button_name = $product->button_name;
                    // 商品定期支払種別
                    // $product_subscription = $product->subscription;
                    // 決済URL
                    $url = '?' . self::SLUG__CHECKOUT . "=" . $product->code;
                    // STRIPEの購入ボタンのHTMLを作成
                    // <script
                    //  src="https://checkout.stripe.com/checkout.js"
                    //  class="stripe-button"
                    //  data-key="{$stripe_public_key}"
                    //  data-amount="{$product_price}"
                    //  data-name="{$product_provider_name}"
                    //  data-description="{$product_name}"
                    //  data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
                    //  data-locale="auto"
                    //  data-currency="{$product_currency}"
                    //  data-zip-code="false"
                    //  data-allow-remember-me="false"
                    //  data-label="{$product_button_name}"></script>
                    $val =<<<EOS
                    <form action="{$url}" method="POST" class="gssc-form"
                      data-key="{$stripe_public_key}"
                      data-amount="{$product_price}"
                      data-name="{$product_provider_name}"
                      data-description="{$product_name}"
                      data-currency="{$product_currency}"
                      data-label="{$product_button_name}">
                    </form>
EOS;
                }
            }
        }
        return $val;
    }

    /**
     * ショートコード処理（商品名）
     */
    function short_code_product_name($atts, $content = null) {
        $val = '';
        // 商品コード
        $product_code = isset($_GET['product_id']) ? intval(trim(sanitize_text_field($_GET['product_id']))) : 0;
        // STRIPEの公開キーをOPTIONSテーブルから取得
        $stripe_public_key = self::decrypt(get_option(self::OPTION_KEY__STRIPE_PUBLIC_KEY), self::ENCRYPT_PASSWORD);
        // OPTIONSテーブルから商品情報を取得
        $product = $this->getProduct($product_code);
        // 商品情報がある場合
        if (isset($product)) {
            // 商品価格
            $product_price = $product->price;
            // 商品通貨
            $product_currency = strtolower($product->currency);
            // 商品提供者名
            $product_provider_name = $product->provider_name;
            // 商品名
            $product_name = $product->name;
            // 商品ボタン名
            $product_button_name = $product->button_name;
            // STRIPEの購入ボタンのHTMLを作成
            $val = __('Canceled payment', GSSC) . '：' . $product_name;
        }
        return $val;
    }

    /**
     * OPTION情報から任意の商品情報を取得
     */
    function getProduct($product_code) {
        // 商品情報リストをOPTIONSテーブルから取得
        $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
        // 商品情報リストがある場合
        if (!is_null($product_list)) {
            // 商品情報リストをアンシリアライズ
            $product_list = unserialize($product_list);
            // アンシリアライズした商品情報リストが正しく配列の場合
            if (is_array($product_list)) {
                for ($i = 0; $i < count($product_list); $i++) {
                    if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                        // 商品コードが一致する場合
                        if ($product_list[$i]->code == $product_code) {
                            return $product_list[$i];
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * ページを表示する直前の前処理をフック(STRIPEのチェックアウト処理を追加)
     */
    function on_template_redirect() {
        // STRIPEの与信枠の確保をするために対象の商品コードをURLクエリーから取得
        $checkout = get_query_var(self::SLUG__CHECKOUT);
        // 商品コードを取得
        $product_code = intval($checkout);
        // 商品コードがある場合
        if ($product_code > 0) {
            // STRIPEの一括払いと定期払いの共通前処理
            list($billing_timing, $token, $email, $product, $immediate_settlement) = $this->precheck($product_code);
            // 一括払いの場合
            if ($billing_timing === self::STRIPE_BILLING_TIMING__LUMP_SUM) {
                // STRIPEの与信枠の確保処理
                $this->checkout($token, $email, $product, $immediate_settlement);
            }
            // 分割払いの場合
            else if ($billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                // STRIPEのサブスクリプション処理
                $this->subscribe($token, $email, $product, $immediate_settlement);
            }
        }
        // STRIPEの与信枠の確保キャンセル処理をするために対象の料金IDをURLクエリーから取得
        // ex) ch_zd400000000000000rsn
        $refund = get_query_var(self::SLUG__REFUND);
        // 与信枠の確保キャンセルの場合
        // ex) ch_zd4gENPfuT06SdQwWrsn
        if (strlen($refund) > 0) {
            // STRIPEの与信枠の確保キャンセル処理
            $this->refund($refund);
        }
        // STRIPEの決済の確定処理をするために対象の料金IDをURLクエリーから取得
        // ex) ch_zd400000000000000rsn
        $capture = get_query_var(self::SLUG__CAPTURE);
        // 決済確定の場合
        if (strlen($capture) > 0) {
            // STRIPEの決済を確定処理
            $this->capture($capture);
        }
        // STRIPEのサブスクリプションのキャンセル処理をするために対象のサブスクリプションIDをURLクエリーから取得
        // ex) sub_zd40000000000000rsn
        $subscription = get_query_var(self::SLUG__CANCEL_SUBSCRIPTION);
        if (strlen($subscription) > 0) {
            // STRIPEのサブスクリプションのキャンセル処理
            $this->cancelSubscription($subscription);
        }
        // STRIPEのサブスクリプションの毎月/毎年の支払完了受信処理をするために値をURLクエリーから取得
        // ex) true
        $pay_subscription = get_query_var(self::SLUG__PAY_SUBSCRIPTION);
        if (strlen($pay_subscription) > 0) {
            // STRIPEのサブスクリプションの毎月/毎年の支払完了受信処理
            $this->paySubscription();
        }
        // STRIPEカスタマーポータル遷移処理をするためにURLクエリーから取得
        $stripe_customer_portal_access = get_query_var(self::SLUG__CUSTOMER_PORTAL_ACCESS);
        if (strlen($stripe_customer_portal_access) > 0) {
            // STRIPEカスタマーポータル遷移処理
            $this->accessStripeCustomerPortal();
        }
    }

    private $stripe;

    /**
     * STRIPEのAPIを初期化
     */
    function initStripeApi() {
        // STRIPEのライブラリを読み込む
        // require_once( dirname(__FILE__).'/lib/stripe-php-7.125.0/init.php');
        require_once( dirname(__FILE__).'/lib/stripe-php-10.12.1/init.php');
        // STRIPEのシークレットキーをOPTIONSテーブルから取得
        $stripe_secret_key = self::decrypt(get_option(self::OPTION_KEY__STRIPE_SECRET_KEY), self::ENCRYPT_PASSWORD);
        // STRIPEのシークレットキーをセット
        // \Stripe\Stripe::setApiKey($stripe_secret_key); // 旧APIの仕様
        if (!\is_string($stripe_secret_key) && !\is_array($stripe_secret_key)) {
            $stripe_secret_key = array();
        }
        $this->stripe = new \Stripe\StripeClient($stripe_secret_key);
    }

    /**
     * STRIPEの一括払いと定期払いの共通前処理
     */
    function precheck($product_code) {
        // STRIPEのAPIを初期化
        $this->initStripeApi();
        // STRIPEのトークンと決済者のメルアドを取得
        $token = trim(sanitize_text_field($_POST['stripeToken']));
        $email = trim(sanitize_text_field($_POST['stripeEmail']));
        // OPTIONSテーブルから商品情報を取得
        $product = $this->getProduct($product_code);
        // 商品情報がない場合
        if (!isset($product)) {
            echo __('There is no product information.', GSSC);
            exit;
        }
        // 即時決済フラグをOPTIONSテーブルから取得
        $immediate_settlement = get_option(self::OPTION_KEY__IMMEDIATE_SETTLEMENT);
        // 即時決済フラグが設定されていない場合
        if ($immediate_settlement !== 'ON' && $immediate_settlement !== 'OFF') {
            echo __('Incomplete settings.', GSSC);
            exit;
        }
        $immediate_settlement = ($immediate_settlement === 'ON');

        $billing_timing = self::STRIPE_BILLING_TIMING__LUMP_SUM;
        if (isset($product->billing_timing) && $product->billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
            $billing_timing = self::STRIPE_BILLING_TIMING__SUBSCRIPTION;
        }

        return array($billing_timing, $token, $email, $product, $immediate_settlement);
    }

    /**
     * StripeカスタマーポータルログインURLを取得
     */
    function getStripeCustomerPortalLoginURL() {
        $stripe_customer_portal_login_url = get_option(self::OPTION_KEY__STRIPE_CUSTOMER_PORTAL_LOGIN_URL);
        // それでも無ければ初期値
        if (strlen($stripe_customer_portal_login_url) === 0) {
            $stripe_customer_portal_login_url = home_url() . '/' . SimpleStripeCheckout::SLUG__CUSTOMER_PORTAL;
        }
        return $stripe_customer_portal_login_url;
    }

    /**
     * STRIPEの与信枠の確保処理
     */
    function checkout($token, $email, $product, $immediate_settlement) {
        // 決済結果
        $charge = null;
        // 料金ID
        $charge_id = null;
        // フォームから情報を取得:
        try {
            // オーソリ(与信枠の確保)
            $charge = $this->stripe->charges->create(array(
                "amount" => $product->price,
                "currency" => strtolower($product->currency),
                "source" => $token,
                "description" => $product->name,
                // 即時決済フラグがONの場合はtrue(即座に決済が完了)
                // 即時決済フラグがOFFの場合はfalse(与信枠を確保した後、決済を確定させるかキャンセル)
                'capture' => $immediate_settlement,
            ));
            // 料金IDを取得
            $charge_id = $charge['id'];
        } catch (\Stripe\Error\Card $e) {
            if ($charge_id !== null) {
                // 例外が発生すればオーソリを取り消す
                $this->stripe->refunds->create(array(
                    'charge' => $charge_id,
                ));
            }
            // 決済できなかったときの処理
            die(__('Payment was not completed.', GSSC));
        }
        // カード番号下4桁
        $last4 = "----";
        // 金額
        $amount = 0;
        // サービス名
        $service_name = '----';
        // メルアド
        $email = '----';
        // 決済が完了した場合
        if ($charge) {
            // 金額を取得
            if (isset($charge->amount)) {
                $amount = $charge->amount;
            }
            // サービス名を取得
            if (isset($charge->description)) {
                $service_name = $charge->description;
            }
            if (isset($charge->source)) {
                // カード番号下4桁を取得
                if (isset($charge->source->last4)) {
                    $last4 = $charge->source->last4;
                }
                // メルアドを取得
                if (isset($charge->source->name)) {
                    $email = $charge->source->name;
                }
            }
        }
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($product->currency)]['format']);
        // キャンセルURL
        $cancel_url = home_url() . '/?' . self::SLUG__REFUND . '=' . $charge_id;
        // 確定URL
        $capture_url = home_url() . '/?' . self::SLUG__CAPTURE . '=' . $charge_id;
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name}" . __(': Thank you for your payment.', GSSC),
            // 本文
            self::buyer_lump_sum_payment_mail_template($email, $service_name, $amount, $last4, $cancel_url, $site_name, $home_url, $buyer_from_address, $immediate_settlement, $stripe_customer_portal_login_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name}" . __(': There was payment.', GSSC),
            // 本文
            self::seller_lump_sum_payment_mail_template($email, $service_name, $amount, $last4, $capture_url, $site_name, $home_url, $immediate_settlement),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        };
        // サンキューページへリダイレクト
        $redirect_page = get_page_by_path(SimpleStripeCheckout::SLUG__CHECKEDOUT);
        if (isset($redirect_page)) {
            $redirect_page_id = $redirect_page->ID;
        }
        wp_safe_redirect(home_url('/?p=' . $redirect_page_id), 303);
        exit;
    }

    /**
     * STRIPEのサブスクリプション処理
     */
    function subscribe($token, $email, $product, $immediate_settlement) {
        // 決済結果
        $charge = null;
        // 料金ID
        $charge_id = null;
        // フォームから情報を取得:
        try {
            $plan = $this->stripe->plans->retrieve(
              $product->stripe_plan_id,
              []
            );
            $customer = $this->stripe->customers->create(array(
              'email' => $email,
              'source'  => $token,
            ));
            $isBillingCycle = strlen($product->billing_cycle) > 0 && $product->billing_cycle > 0;
            if ($isBillingCycle) {
              $now = time();
              $startdate = $now;
              $isTrial = strlen($product->free_trial_days) > 0 && intval($product->free_trial_days) > 0;
              if ($isTrial) {
                $startdate = $startdate + (intval($product->free_trial_days) * 60 * 60 * 24);
              }
              $phases = [];
              if ($isTrial) {
                $phases[] = [
                  'items' => [
                    [
                      'price' => $product->stripe_plan_id,
                      'quantity' => 1,
                    ],
                  ],
                  'trial' => true,
                  'end_date' => $startdate,
                ];
              }
              $phases[] = [
                'items' => [
                  [
                    'price' => $product->stripe_plan_id,
                    'quantity' => 1,
                  ],
                ],
                'iterations' => $product->billing_cycle,
              ];
              $subscriptionSchedule = $this->stripe->subscriptionSchedules->create([
                'customer' => $customer->id,
                'start_date' => $now,
                'end_behavior' => 'cancel',
                'phases' => $phases,
              ]);
              $subscription = $this->stripe->subscriptions->retrieve(
                $subscriptionSchedule->subscription,
                []
              );
            } else {
              $subscription = $this->stripe->subscriptions->create(array(
                'customer' => $customer->id,
                'trial_from_plan' => true,
                'items' => array(
                    array('price' => $product->stripe_plan_id)
                )
              ));
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = $e->getError();
            echo __('Subscription registration failed.', GSSC) . ' [' . $error->type . ':' . $error->message . ']';
            if (isset($customer) && $customer->id !== null) {
              // Customerを削除すればSubscriptionも削除される
              $this->stripe->customers->delete($customer->id,array());
            }
            die();
        }
        // カード番号下4桁
        $last4 = "----";
        // 金額
        $amount = 0;
        // サービス名
        $service_name = '----';
        // メルアド
        $email = '----';
        // 金額を取得
        if (isset($plan) && isset($plan->amount)) {
            $amount = $plan->amount;
        }
        // サービス名を取得
        if (isset($product->name)) {
            $service_name = $product->name;
        }
        if (isset($customer->sources)) {
            // カード番号下4桁を取得
            if (isset($customer->sources->data[0]->last4)) {
                $last4 = $customer->sources->data[0]->last4;
            }
            // メルアドを取得
            if (isset($customer->sources->data[0]->name)) {
                $email = $customer->sources->data[0]->name;
            }
        }
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($product->currency)]['format']);
        // サブスクリプションID
        $subscription_id = $subscription->id;
        // キャンセルURL
        $cancel_url = home_url() . '/?' . self::SLUG__CANCEL_SUBSCRIPTION . '=' . $subscription_id;
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 請求間隔（月次 or 年次）
        $billing_frequency = '';
        if (isset($this->stripe_billing_frequency_list[$product->billing_frequency])) {
            $billing_frequency = $this->stripe_billing_frequency_list[$product->billing_frequency][self::LABEL];
        }
        // 支払回数
        $billing_cycle = '';
        if (isset($product->billing_frequency)) {
            $billing_cycle = $product->billing_cycle;
        }
        // 初回引落予定日
        $trial_end_date =
          isset($subscription->trial_end)
            ? date('Y/m/d', $subscription->trial_end)
            : (
              $isBillingCycle && $isTrial
                ? __('About 1 hour later', GSSC)
                : '---'
            );
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name}" . __(': Thank you for registering for regular payment.', GSSC),
            // 本文
            self::buyer_subscription_mail_template($email, $service_name, $amount, $last4, $cancel_url, $site_name, $home_url, $buyer_from_address, $billing_frequency, $billing_cycle, $trial_end_date, $subscription_id, $stripe_customer_portal_login_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name}" . __(': There was a regular payment registration.', GSSC),
            // 本文
            self::seller_subscription_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $billing_frequency, $billing_cycle, $trial_end_date, $subscription_id),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        };
        // サンキューページへリダイレクト
        $redirect_page = get_page_by_path(SimpleStripeCheckout::SLUG__CHECKEDOUT);
        if (isset($redirect_page)) {
            $redirect_page_id = $redirect_page->ID;
        }
        wp_safe_redirect(home_url('/?p=' . $redirect_page_id), 303);
        exit;
    }

    /**
     * STRIPEの与信枠の確保キャンセル処理
     */
    function refund($charge_id) {
        // STRIPEのAPIを初期化
        $this->initStripeApi();
        // 決済結果
        $charge = null;
        try {
            // 与信枠を確保していた料金データを取得
            $charge = $this->stripe->charges->retrieve($charge_id);
            // キャンセル済の場合
            if ($charge['refunded'] === true) {
                die(__('The payment has already been cancelled.', GSSC));
            }
            // 決済確定済の場合
            if ($charge['captured'] === true) {
                die(__('The settlement has already been confirmed.', GSSC));
            }
            // 24時間未経過の場合
            if (($charge['created'] + (60 * 60 * 24)) < time()) {
                die(__('It has been 24 hours and cannot be canceled.', GSSC));
            }
            // 与信枠の確保をキャンセル
            $this->stripe->refunds->create(array(
                'charge' => $charge['id'],
            ));
        } catch (Exception $e) {
            die(__('There is no data that has secured the credit line, or the cancellation of the credit line has failed.', GSSC));
        }
        echo __('Canceled to secure credit line.', GSSC);

        // カード番号下4桁
        $last4 = "----";
        // 金額
        $amount = 0;
        // サービス名
        $service_name = '----';
        // メルアド
        $email = '----';
        // 通貨
        $currency = '----';
        // 決済が完了した場合
        if ($charge) {
            // 金額を取得
            if (isset($charge->amount)) {
                $amount = $charge->amount;
            }
            // サービス名を取得
            if (isset($charge->description)) {
                $service_name = $charge->description;
            }
            // 通貨を取得
            if (isset($charge->currency)) {
                $currency = $charge->currency;
            }
            if (isset($charge->source)) {
                // カード番号下4桁を取得
                if (isset($charge->source->last4)) {
                    $last4 = $charge->source->last4;
                }
                // メルアドを取得
                if (isset($charge->source->name)) {
                    $email = $charge->source->name;
                }
            }
        }
        // 価格に単位を付ける
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($currency)]['format']);
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __('It was cancelled.', GSSC),
            // 本文
            self::buyer_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $buyer_from_address, null, $stripe_customer_portal_login_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __('It was cancelled.', GSSC),
            // 本文
            self::seller_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, null),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        };
        // キャンセル完了ページへリダイレクト
        $redirect_page = get_page_by_path(SimpleStripeCheckout::SLUG__CANCEL);
        if (isset($redirect_page)) {
            $redirect_page_id = $redirect_page->ID;
        }
        wp_safe_redirect(home_url('/?p=' . $redirect_page_id), 303);
        exit;
    }

    /**
     * STRIPEの決済の確定処理
     */
    function capture($charge_id) {
        // STRIPEのAPIを初期化
        $this->initStripeApi();
        // 決済結果
        $charge = null;
        // 与信枠を確保していた料金データを取得
        try {
            $charge = $this->stripe->charges->retrieve($charge_id);
            // キャンセル済の場合
            if ($charge['refunded'] === true) {
                die(__('The payment has already been cancelled.', GSSC));
            }
            // 決済確定済の場合
            if ($charge['captured'] === true) {
                die(__('The settlement has already been confirmed.', GSSC));
            }
            // 24時間未経過の場合
            if (($charge['created'] + (60 * 60 * 24)) > time()) {
                die(__('24 hours have not passed yet.', GSSC));
            }
            // 決済を確定
            $charge->capture();
        } catch (\Stripe\Error\Card $e) {
            die(__('There is no data to confirm the payment, or the payment has failed to be confirmed.', GSSC));
        }
        echo __('The settlement has been confirmed.', GSSC);
        // カード番号下4桁
        $last4 = "----";
        // 金額
        $amount = 0;
        // サービス名
        $service_name = '----';
        // メルアド
        $email = '----';
        // 通貨
        $currency = '----';
        // 決済が完了した場合
        if ($charge) {
            // 金額を取得
            if (isset($charge->amount)) {
                $amount = $charge->amount;
            }
            // サービス名を取得
            if (isset($charge->description)) {
                $service_name = $charge->description;
            }
            // 通貨を取得
            if (isset($charge->currency)) {
                $currency = $charge->currency;
            }
            if (isset($charge->source)) {
                // カード番号下4桁を取得
                if (isset($charge->source->last4)) {
                    $last4 = $charge->source->last4;
                }
                // メルアドを取得
                if (isset($charge->source->name)) {
                    $email = $charge->source->name;
                }
            }
        }
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($currency)]['format']);
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __(': Payment was made.', GSSC),
            // 本文
            self::buyer_capture_mail_template($email, $service_name, $amount, $last4, $cancel_url, $site_name, $home_url, $buyer_from_address, $stripe_customer_portal_login_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __(': Payment was made.', GSSC),
            // 本文
            self::seller_capture_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        };
        // 決済確定完了ページへリダイレクト
        $redirect_page = get_page_by_path(SimpleStripeCheckout::SLUG__CAPTURE_COMPLETE);
        if (isset($redirect_page)) {
            $redirect_page_id = $redirect_page->ID;
        }
        wp_safe_redirect(home_url('/?p=' . $redirect_page_id), 303);
        exit;
    }

    /**
     * STRIPEのサブスクリプションのキャンセル処理
     */
    function cancelSubscription($subscription_id) {
        // STRIPEのAPIを初期化
        $this->initStripeApi();
        $cancel = false;
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscription_id);
            // サブスクリプションが存在しない場合
            if (!isset($subscription)) {
                die(__('The subscription does not exist.', GSSC));
            }
            // キャンセル済の場合
            if ($subscription['status'] === 'canceled') {
                die(__('Your subscription has already been cancelled.', GSSC));
            }
            // 商品情報取得
            $product = $this->stripe->products->retrieve($subscription->plan->product);
            // 商品情報が存在しない場合
            if (!isset($product)) {
                die(__('Product information does not exist.', GSSC) . ' [code: stripe]');
            }
            $plan = $this->stripe->plans->retrieve(
              $subscription->items->data[0]->price->id,
              []
            );
            // プラン情報が存在しない場合
            if (!isset($plan)) {
                die(__('Plan information does not exist.', GSSC) . ' [code: stripe]');
            }
            // OPTIONSテーブルから商品情報を取得
            $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
            if (!is_null($product_list)) {
                $product_list = unserialize($product_list);
                if (is_array($product_list)) {
                    for ($i = 0; $i < count($product_list); $i++) {
                        if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                            // 商品コードが一致する場合
                            if ($product_list[$i]->stripe_plan_id == $plan->id) {
                                $optionsProduct = $product_list[$i];
                            }
                        }
                    }
                }
            }
            if (!isset($optionsProduct)) {
                die(__('Product information does not exist.', GSSC) . ' [code: options]');
            }
            // 顧客情報取得
            $customer = $this->stripe->customers->retrieve($subscription->customer);
            //顧客情報が存在しない場合
            if (!isset($customer)) {
                die(__('Customer information does not exist.', GSSC));
            }
            $subscription->cancel();
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = $e->getError();
            die(__('Subscription cancellation failed.', GSSC) . ' [' . $error->type . ':' . $error->message . ']');
        }
        echo __('The subscription has been cancelled.', GSSC);
        // サービス名
        $service_name = '----';
        if (isset($product->name)) {
            $service_name = $product->name;
        }
        // カード番号下4桁
        $last4 = "----";
        if (isset($customer->sources)) {
            if (isset($customer->sources->data)) {
                if (count($customer->sources->data) > 0) {
                    if (isset($customer->sources->data[0]->last4)) {
                        $last4 = $customer->sources->data[0]->last4;
                    }
                }
            }
        }
        // 金額
        $amount = 0;
        if (isset($plan)) {
            if (isset($plan->amount)) {
                $amount = $plan->amount;
            }
        }
        // 通貨
        $currency = '----';
        if (isset($plan)) {
            if (isset($plan->currency)) {
                $currency = $plan->currency;
            }
        }

        // メルアド
        $email = '----';
        if (isset($customer->email)) {
            $email = $customer->email;
        }
        // 価格に単位を付ける
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($currency)]['format']);
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __('It was cancelled.', GSSC),
            // 本文
            self::buyer_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $buyer_from_address, $subscription_id, $stripe_customer_portal_login_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __('It was cancelled.', GSSC),
            // 本文
            self::seller_cancel_mail_template($email, $service_name, $amount, $last4, $site_name, $home_url, $subscription_id),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Mail has send.', GSSC);
        } else {
            echo __('Failed to send the email.', GSSC);
        };
        // キャンセル完了ページへリダイレクト
        $redirect_page = get_page_by_path(SimpleStripeCheckout::SLUG__CANCELED_SUBSCRIPTION);
        if (isset($redirect_page)) {
            $redirect_page_id = $redirect_page->ID;
        }
        wp_safe_redirect(home_url('/?p=' . $redirect_page_id), 303);
        exit;
    }

    /**
     * STRIPEのサブスクリプションの支払完了イベント受信処理
     */
    function paySubscription() {
        $json = json_decode(file_get_contents('php://input'), true);
        // サブスクリプションID
        $subscription_id = '';
        if (
          isset($json['data']['object']['billing_reason']) &&
          isset($json['data']['object']['lines']['data'][0]['plan']) &&
          array_key_exists('trial_period_days', $json['data']['object']['lines']['data'][0]['plan'])
        ) {
          if (!(
            ($json['data']['object']['billing_reason'] === 'subscription_cycle') ||
            (($json['data']['object']['billing_reason'] === 'subscription_create') && (is_null($json['data']['object']['lines']['data'][0]['plan']['trial_period_days'])))
          )) {
            die(__('It is not a regular payment recycling.', GSSC));
          }
        }
        if (isset($json['data']['object']['subscription'])) {
            $subscription_id = $json['data']['object']['subscription'];
        }
        // STRIPEのAPIを初期化
        $this->initStripeApi();
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscription_id);
            // サブスクリプションが存在しない場合
            if (!isset($subscription)) {
                die(__('The subscription does not exist.', GSSC));
            }
            // キャンセル済の場合
            if ($subscription['status'] === 'canceled') {
                die(__('Your subscription has already been cancelled.', GSSC));
            }
            // 商品情報取得
            $product = $this->stripe->products->retrieve($subscription->plan->product);
            // 商品情報が存在しない場合
            if (!isset($product)) {
                die(__('Product information does not exist.', GSSC) . ' [code: stripe]');
            }
            // OPTIONSテーブルから商品情報を取得
            $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
            if (!is_null($product_list)) {
                $product_list = unserialize($product_list);
                if (is_array($product_list)) {
                    for ($i = 0; $i < count($product_list); $i++) {
                        if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                            // 商品コードが一致する場合
                            if ($product_list[$i]->stripe_plan_id == $subscription->plan->id) {
                                $optionsProduct = $product_list[$i];
                            }
                        }
                    }
                }
            }
            if (!isset($optionsProduct)) {
                die(__('Product information does not exist.', GSSC) . ' [code: options]');
            }
            // 顧客情報取得
            $customer = $this->stripe->customers->retrieve($subscription->customer);
            //顧客情報が存在しない場合
            if (!isset($customer)) {
                die(__('Customer information does not exist.', GSSC));
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = $e->getError();
            die(__('Failed to send subscription payment completion email', GSSC) . ' [' . $error->type . ':' . $error->message . ']');
        }
        // サービス名
        $service_name = '----';
        if (isset($product->name)) {
            $service_name = $product->name;
        }
        // カード番号下4桁
        $last4 = "----";
        if (isset($customer->sources)) {
            if (isset($customer->sources->data)) {
                if (count($customer->sources->data) > 0) {
                    if (isset($customer->sources->data[0]->last4)) {
                        $last4 = $customer->sources->data[0]->last4;
                    }
                }
            }
        }
        // 金額
        $amount = 0;
        if (isset($subscription->plan)) {
            if (isset($subscription->plan->amount)) {
                $amount = $subscription->plan->amount;
            }
        }
        // 通貨
        $currency = '----';
        if (isset($subscription->plan)) {
            if (isset($subscription->plan->currency)) {
                $currency = $subscription->plan->currency;
            }
        }
        // 次回支払予定日
        $next_date = '----';
        if (isset($json)) {
            if (isset($json['data'])) {
                if (isset($json['data']['object'])) {
                    if (isset($json['data']['object']['lines'])) {
                        if (isset($json['data']['object']['lines']['data'])) {
                            if (isset($json['data']['object']['lines']['data'][0])) {
                                if (isset($json['data']['object']['lines']['data'][0]['period'])) {
                                    if (isset($json['data']['object']['lines']['data'][0]['period']['end'])) {
                                        $next_date = date("Y/m/d", $json['data']['object']['lines']['data'][0]['period']['end']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // メルアド
        $email = '----';
        if (isset($customer->email)) {
            $email = $customer->email;
        }
        // 価格に単位を付ける
        $amount = str_replace('___', $amount, $this->stripe_currencies[strtoupper($currency)]['format']);
        // キャンセルURL
        $cancel_url = home_url() . '/?' . self::SLUG__CANCEL_SUBSCRIPTION . '=' . $subscription_id;
        // サイト名
        $site_name = get_bloginfo('name');
        // サイトURL
        $home_url = home_url();
        // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
        $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        // StripeカスタマーポータルログインURLをOPTIONSテーブルから取得
        $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        // 購入者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __(': Payment was made.', GSSC),
            // 本文
            self::buyer_pay_subscription_mail_template($email, $service_name, $amount, $last4, $next_date, $site_name, $home_url, $buyer_from_address, $subscription_id, $stripe_customer_portal_login_url, $cancel_url),
            // 宛先
            $email,
            // 送信元(購入者向け送信元メルアドをOPTIONSテーブルから取得)
            $buyer_from_address
        )) {
            echo __('Completed sending email to purchaser.', GSSC);
        } else {
            echo __('Failed to send the email to the purchaser.', GSSC);
        }
        // 販売者向けメール送信
        if (self::send_mail(
            // タイトル
            "${service_name} " . __(': Payment was made.', GSSC),
            // 本文
            self::seller_pay_subscription_mail_template($email, $service_name, $amount, $last4, $next_date, $site_name, $home_url, $subscription_id),
            // 宛先(販売者向け受信メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS),
            // 送信元(販売者向け送信元メルアドをOPTIONSテーブルから取得)
            get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS)
        )) {
            echo __('Completed sending email to seller.', GSSC);
        } else {
            echo __('Failed to send the email to the seller.', GSSC);
        };
        exit;
    }

    /**
     * STRIPEのカスタマーポータルへのアクセス処理
     */
    function accessStripeCustomerPortal() {
        $unauthorized_access = false;
        $pre_access_count = 0;
        $pre_access_time = '';
        $now = date("Y/m/d H:i");
        $ip = "";
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $tmp_dir = realpath(get_temp_dir());
        if ($tmp_dir) {
            $ip_file_path = $tmp_dir . "/" . "gssc" . "/" . md5($ip);
            if (file_exists($ip_file_path)) {
                $ip_file_data = file_get_contents($ip_file_path);
            }
            if ($ip_file_data) {
                $pre_access_info = explode(",", $ip_file_data);
                if (is_array($pre_access_info) && count($pre_access_info) === 2) {
                    $pre_access_time = $pre_access_info[0];
                    $pre_access_count = $pre_access_info[1];
                    $pre_15minute = date("Y/m/d H:i", strtotime('-15 minute'));
                    if ($pre_access_time && strcmp($pre_15minute, $pre_access_time) < 0) {
                        // 15分未満
                        if (intval($pre_access_count) >= 10) {
                            $unauthorized_access = true;
                        }
                    } else {
                        // 15分経過したので失敗回数をリセット
                        $pre_access_count = 0;
                    }
                }
            }
        }
        if ($unauthorized_access) {
            echo __('Unauthorized access.', GSSC);
            exit;
        }

        $subscription_id = trim( htmlentities( $_POST['subscription_id'] ) );
        $last4 = trim( htmlentities( $_POST['last4'] ) );
        if ( !empty( $subscription_id ) && !empty( $last4 ) ) {
            // 取引IDとカード下4桁で照合
            try {
                // STRIPEのAPIを初期化
                $this->initStripeApi();
                // サブスクリプション情報を取得
                $subscription = $this->stripe->subscriptions->retrieve($subscription_id);
                // 顧客ID
                $customer_id  = $subscription->customer;
                // 顧客情報取得
                $customer = $this->stripe->customers->retrieve($customer_id);
                // カード下4桁の照合
                $matchLast4 = false;
                foreach ($customer->sources->data as $card) {
                    if ( $card->last4 === $last4 ) {
                        $matchLast4 = true;
                    }
                }
                if ( $matchLast4 ) {
                    // Stripeカスタマーポータル操作完了ページ
                    $customerPortalEntrance = get_page_by_path(SimpleStripeCheckout::SLUG__CUSTOMER_PORTAL);
                    if (isset($customerPortalEntrance)) {
                        $customerPortalEntranceId = $customerPortalEntrance->ID;
                    }
                    // Authenticate your user.
                    $session = $this->stripe->billingPortal->sessions->create( [
                        'customer'   => $customer_id,
                        'return_url' => home_url('/?p=' . $customerPortalEntranceId),
                    ] );
                    // Redirect to the customer portal.
                    header( "Location: " . $session->url );
                    exit();
                }
            } catch ( Exception $e ) {
                // print_r($e);
                // N/A
            }
        }

        $pre_access_count++;
        $ip_file_dir = $tmp_dir . "/" . "gssc";
        if (!file_exists($ip_file_dir)) {
            mkdir($ip_file_dir);
        }
        $ip_file_data = file_put_contents($ip_file_path, $now . ',' . $pre_access_count);

        echo __('Incorrect input.', GSSC);
        exit;
    }

    /**
     * 管理画面メニューの基本構造が配置された後に実行するアクションにフックする、
     * 管理画面のトップメニューページを追加する関数
     */
    function set_plugin_menu() {
        // トップメニュー「SimpleStripeCheckout」を追加
        add_menu_page(
            // ページタイトル：
            'SimpleStripeCheckout',
            // メニュータイトル：
            'Simple Stripe Checkout',
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__TOP,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_product_list'],
            // アイコン：
            // WordPressが用意しているカートのアイコン
            // ・参考（https://developer.wordpress.org/resource/dashicons/#awards）
            'dashicons-cart',
            // メニューが表示される位置：
            // 省略時はメニュー構造の最下部に表示される。
            // 大きい数値ほど下に表示される。
            // 2つのメニューが同じ位置を指定している場合は片方のみ表示され上書きされる可能性がある。
            // 衝突のリスクは整数値でなく小数値を使用することで回避することができる。
            // 例： 63の代わりに63.3（コード内ではクォートを使用。例えば '63.3'）
            // 初期値はメニュー構造の最下部。
            // ・2 - ダッシュボード
            // ・4 - （セパレータ）
            // ・5 - 投稿
            // ・10 - メディア
            // ・15 - リンク
            // ・20 - 固定ページ
            // ・25 - コメント
            // ・59 - （セパレータ）
            // ・60 - 外観（テーマ）
            // ・65 - プラグイン
            // ・70 - ユーザー
            // ・75 - ツール
            // ・80 - 設定
            // ・99 - （セパレータ）
            // 但しネットワーク管理者メニューでは値が以下の様に異なる。
            // ・2 - ダッシュボード
            // ・4 - （セパレータ）
            // ・5 - 参加サイト
            // ・10 - ユーザー
            // ・15 - テーマ
            // ・20 - プラグイン
            // ・25 - 設定
            // ・30 - 更新
            // ・99 - （セパレータ）
            99
        );
    }

    /**
     * 管理画面メニューの基本構造が配置された後に実行するアクションにフックする、
     * 管理画面のサブメニューページを追加する関数
     */
    function set_plugin_sub_menu() {

        // サブメニュー「初期設定」を追加
        add_submenu_page(
            // 親メニューのslug：
            self::SLUG__TOP,
            //ページタイトル：
            __('Initial setting', GSSC), // 初期設定
            //メニュータイトル：
            __('Initial setting', GSSC), // 初期設定
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__INITIAL_CONFIG_FORM,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_initial_config_form']
        );

        // 商品情報リストクラスを生成
        $this->product_list_table = new SimpleStripeCheckout_ProductListTable();
        // 商品情報リストのアクション処理
        $this->act_to_products();
        // サブメニュー「商品一覧」を追加
        add_submenu_page(
            // 親メニューのslug：
            self::SLUG__TOP,
            //ページタイトル：
            __('Product list', GSSC), // 商品一覧
            //メニュータイトル：
            __('Product list', GSSC), // 商品一覧
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__PRODUCT_LIST,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_product_list']
        );

        // サブメニュー「新規登録」を追加
        add_submenu_page(
            // 親メニューのslug：
            self::SLUG__TOP,
            //ページタイトル：
            __('Product registration', GSSC), // 新規登録
            //メニュータイトル：
            __('Product registration', GSSC), // 新規登録
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__PRODUCT_EDIT_FORM,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_product_edit_form']
        );

        // サブメニュー「メール設定」を追加
        add_submenu_page(
            // 親メニューのslug：
            self::SLUG__TOP,
            //ページタイトル：
            __('Mail setting', GSSC), // メール設定
            //メニュータイトル：
            __('Mail setting', GSSC), // メール設定
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__MAIL_CONFIG_FORM,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_mail_config_form']
        );

        // サブメニュー「Webhook」を追加
        add_submenu_page(
            // 親メニューのslug：
            self::SLUG__TOP,
            //ページタイトル：
            'Webhook',
            //メニュータイトル：
            'Webhook',
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            self::SLUG__HOOK_CONFIG_FORM,
            // メニューに紐づく画面を描画するcallback関数：
            [$this, 'show_hook_config_form']
        );
    }

    /**
     * 商品情報リストをOPTIONSから取得
     */
    function get_product_list() {
        // 商品コードがあればoptionsテーブルから商品情報を取得
        $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
        // 商品情報リストがある場合
        if (!is_null($product_list)) {
            // 商品情報リストをアンシリアライズ
            $product_list = unserialize($product_list);
        }
        // 商品情報リストが正しく配列ではない場合
        if (!is_array($product_list)) {
            $product_list = array();
        }
        return $product_list;
    }

    /**
     * サブメニュー「初期設定」押下時の画面を表示するcallback関数
     */
    function show_initial_config_form() {
        // 初期設定の保存完了メッセージ
        if (false !== ($complete_message = get_transient(self::TRANSIENT_KEY__SAVE_INITIAL_CONFIG))) {
            $complete_message = self::getNotice($complete_message, self::NOTICE_TYPE__SUCCESS);
        }
        // STRIPEの公開キーの不正メッセージ
        if (false !== ($invalid_public_key = get_transient(self::TRANSIENT_KEY__INVALID_PUBLIC_KEY))) {
            $invalid_public_key = self::getNotice($invalid_public_key, self::NOTICE_TYPE__ERROR);
        }
        // STRIPEのシークレットキーの不正メッセージ
        if (false !== ($invalid_secret_key = get_transient(self::TRANSIENT_KEY__INVALID_SECRET_KEY))) {
            $invalid_secret_key = self::getNotice($invalid_secret_key, self::NOTICE_TYPE__ERROR);
        }
        // STRIPEの公開キーのパラメータ名
        $param_stripe_public_key = self::PARAMETER__STRIPE_PUBLIC_KEY;
        // STRIPEのシークレットキーのパラメータ名
        $param_stripe_secret_key = self::PARAMETER__STRIPE_SECRET_KEY;
        // STRIPEの公開キーをTRANSIENTから取得
        if (false === ($stripe_public_key = get_transient(self::TRANSIENT_KEY__TEMP_PUBLIC_KEY))) {
            // 無ければoptionsテーブルから取得
            $stripe_public_key = self::decrypt(get_option(self::OPTION_KEY__STRIPE_PUBLIC_KEY), self::ENCRYPT_PASSWORD);
        }
        // STRIPEのシークレットキーをoptionsテーブルから取得
        if (false === ($stripe_secret_key = get_transient(self::TRANSIENT_KEY__TEMP_SECRET_KEY))) {
            // 無ければoptionsテーブルから取得
            $stripe_secret_key = self::decrypt(get_option(self::OPTION_KEY__STRIPE_SECRET_KEY), self::ENCRYPT_PASSWORD);
        }
        // nonceフィールドを生成・取得
        $nonce_field = wp_nonce_field(self::CREDENTIAL_ACTION__INITIAL_CONFIG, self::CREDENTIAL_NAME__INITIAL_CONFIG, true, false);
        // 送信ボタンを生成・取得
        $submit_button = get_submit_button(__('Save', GSSC));
        // タイトル
        $title = __('Initial setting', GSSC);
        // 公開キー
        $label_public_key = __('Public key', GSSC);
        // シークレットキー
        $label_secret_key = __('Secret key', GSSC);
        // HTMLを出力
        echo <<< EOM
            <div class="wrap">
            <h2>{$title}</h2>
            {$complete_message}
            {$invalid_public_key}
            {$invalid_secret_key}
            <form action="" method='post' id="simple-stripe-checkout-initial-config-form">
                {$nonce_field}
                <p>
                    <label for="{$param_stripe_public_key}">{$label_public_key}：</label>
                    <input type="text" name="{$param_stripe_public_key}" value="{$stripe_public_key}" style="width:100%"/>
                </p>
                <p>
                    <label for="{$param_stripe_secret_key}">{$label_secret_key}：</label>
                    <input type="password" name="{$param_stripe_secret_key}" value="{$stripe_secret_key}" style="width:100%"/>
                </p>
                {$submit_button}
            </form>
            </div>
EOM;
    }

    /**
     * 初期設定を保存するcallback関数
     */
    function save_initial_config() {
        // nonceで設定したcredentialをPOST受信した場合
        if (isset($_POST[self::CREDENTIAL_NAME__INITIAL_CONFIG]) && $_POST[self::CREDENTIAL_NAME__INITIAL_CONFIG]) {
            // nonceで設定したcredentialのチェック結果が問題ない場合
            if (check_admin_referer(self::CREDENTIAL_ACTION__INITIAL_CONFIG, self::CREDENTIAL_NAME__INITIAL_CONFIG)) {
                // STRIPEの公開キーをPOSTから取得
                $stripe_public_key = trim(sanitize_text_field($_POST[self::PARAMETER__STRIPE_PUBLIC_KEY]));
                // STRIPEのシークレットキーをPOSTから取得
                $stripe_secret_key = trim(sanitize_text_field($_POST[self::PARAMETER__STRIPE_SECRET_KEY]));
                $valid = true;
                // STRIPEの公開キーが正しくない場合
                if (!preg_match("/^[0-9a-zA-Z_]+$/", $stripe_public_key)) {
                    // STRIPEの公開キーの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_PUBLIC_KEY, __('Stripe\'s public key is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // STRIPEのシークレットキーが正しくない場合
                if (!preg_match("/^[0-9a-zA-Z_]+$/", $stripe_secret_key)) {
                    // STRIPEのシークレットキーの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_SECRET_KEY, __('Stripe\'s secret key is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 有効フラグがTrueの場合(STRIPEの公開キーとシークレットキーが正しい場合)
                if ($valid) {
                    // 保存処理
                    // Stripeの公開キーをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__STRIPE_PUBLIC_KEY, self::encrypt($stripe_public_key, self::ENCRYPT_PASSWORD));
                    // Stripeのシークレットキーをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__STRIPE_SECRET_KEY, self::encrypt($stripe_secret_key, self::ENCRYPT_PASSWORD));
                    // 保存が完了したら、完了メッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__SAVE_INITIAL_CONFIG, __('The initial settings have been saved.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // (一応)STRIPEの公開キーの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_PUBLIC_KEY);
                    // (一応)STRIPEのシークレットキーの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_SECRET_KEY);
                    // (一応)ユーザが入力したSTRIPEの公開キーをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_PUBLIC_KEY);
                    // (一応)ユーザが入力したSTRIPEのシークレットキーをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_SECRET_KEY);
                }
                // 有効フラグがFalseの場合(STRIPEの公開キーとシークレットキーが不正の場合)
                else {
                    // ユーザが入力したSTRIPEの公開キーをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PUBLIC_KEY, $stripe_public_key, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力したSTRIPEのシークレットキーをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_SECRET_KEY, $stripe_secret_key, self::TRANSIENT_TIME_LIMIT);
                    // (一応)初期設定の保存完了メッセージを削除
                    delete_transient(self::TRANSIENT_KEY__SAVE_INITIAL_CONFIG);
                }
                // 設定画面にリダイレクト
                wp_safe_redirect(menu_page_url(self::SLUG__INITIAL_CONFIG_FORM, false), 303);
            }
        }
    }

    /**
     * サブメニュー「商品一覧」押下時の画面を表示するcallback関数
     */
    function show_product_list() {
        // OPTIONSから取得した商品情報リストを商品情報リストクラスにセット
        $this->product_list_table->set_product_list(self::get_product_list());
        // 商品情報リストを表示
        echo '<div class="wrap">';
        echo '<h2>' . __('Product list', GSSC) . '&nbsp;&nbsp;<a class="button action" href="?page=' . self::SLUG__PRODUCT_EDIT_FORM . '">' . __('Product registration', GSSC) . '</a></h2>';
        $this->product_list_table->prepare_items();
        $page = esc_attr(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '');
        echo $this->product_list_table->views();
        echo '<form method="get">';
        $this->product_list_table->search_box(__('Search', GSSC), 'items');
        printf('<input type="hidden" name="page" value="%s" />', $page);
        $this->product_list_table->display();
        echo '</form>';
        echo '</div>';
    }

    /**
     * 商品情報リストテーブルの左上のアクション押下時の処理
     */
    function act_to_products() {
        // アクション名を取得
        $action = $this->product_list_table->current_action();
        // 対象の商品コードリストを取得
        $product_code_list = isset($_REQUEST['checked']) ? $_REQUEST['checked'] : null;
        switch ($action) {
            // 一括削除の場合
            case 'delete':
                // 商品情報の削除処理を実行
                $this->delete_products($product_code_list);
                break;
        }
    }

    /**
     * 商品情報の削除処理
     */
    function delete_products($code_list) {
        // 登録済みの商品情報リストをOPTIONSテーブルから取得
        $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
        // 商品情報リストがある場合
        if (!is_null($product_list)) {
            // 商品情報リストをアンシリアライズ
            $product_list = unserialize($product_list);
            // アンシリアライズした商品情報リストが正しく配列の場合
            if (is_array($product_list)) {
                // STRIPEのAPIを初期化
                $this->initStripeApi();

                for ($i = 0; $i < count($product_list); $i++) {
                    if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                        // 商品コードが一致する場合
                        if (is_array($code_list) && in_array($product_list[$i]->code, $code_list)) {
                            if ($product_list[$i]->billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                                // Stripeに商品と価格を新規登録
                                try {
                                    // StripeからPlanを取得
                                    $plan = $this->stripe->plans->retrieve($product_list[$i]->stripe_plan_id);
                                    if (!isset($plan)) {
                                        die(__('Failed to delete Stripe Plan.', GSSC));
                                    }
                                    // Stripeから商品を取得
                                    $product = $this->stripe->products->retrieve($plan->product);
                                    // 商品情報が存在しない場合
                                    if (!isset($product)) {
                                        die(__('Failed to get Stripe products.', GSSC));
                                    }
                                    $plan->delete();
                                    $product->delete();
                                } catch (\Stripe\Exception\ApiErrorException $e) {
                                    // 既に手動でStripe側のプラン、商品を削除済みの場合もあり、
                                    // ここでは判断付かないため無視することにする
                                    // print_r($e);
                                    // $error = $e->getError();
                                    // die(__('Failed to delete Stripe products.', GSSC) . ' [ {$error->type} : {$error->message} ]');
                                }
                            }
                            // 削除フラグを立てる
                            unset($product_list[$i]);
                        }
                    }
                }
            }
            //indexを詰める
            $product_list = array_values($product_list);
            // 商品情報リストをシリアライズ
            $product_list = serialize($product_list);
            // 更新した商品情報リストをOPTIONSテーブルにセット
            update_option(self::OPTION_KEY__PRODUCT_LIST, $product_list);
        }
    }

    /**
     * サブメニュー「新規登録」押下時、又は、
     * サブメニュー「商品一覧」より任意の商品の選択時、の画面を表示するcallback関数
     */
    function show_product_edit_form() {
        // 商品情報の保存完了メッセージ
        if (false !== ($complete_message = get_transient(self::TRANSIENT_KEY__SAVE_PRODUCT_INFO))) {
            $complete_message = self::getNotice($complete_message, self::NOTICE_TYPE__SUCCESS);
        }

        // 商品価格の不正メッセージ
        if (false !== ($invalid_product_price = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PRICE))) {
            $invalid_product_price = self::getNotice($invalid_product_price, self::NOTICE_TYPE__ERROR);
        }
        // 商品提供者名の不正メッセージ
        if (false !== ($invalid_product_provider_name = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PROVIDER_NAME))) {
            $invalid_product_provider_name = self::getNotice($invalid_product_provider_name, self::NOTICE_TYPE__ERROR);
        }
        // 商品名の不正メッセージ
        if (false !== ($invalid_product_name = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_NAME))) {
            $invalid_product_name = self::getNotice($invalid_product_name, self::NOTICE_TYPE__ERROR);
        }
        // 商品通貨の不正メッセージ
        if (false !== ($invalid_product_currency = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_CURRENCY))) {
            $invalid_product_currency = self::getNotice($invalid_product_currency, self::NOTICE_TYPE__ERROR);
        }
        // 商品ボタン名の不正メッセージ
        if (false !== ($invalid_product_button_name = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BUTTON_NAME))) {
            $invalid_product_button_name = self::getNotice($invalid_product_button_name, self::NOTICE_TYPE__ERROR);
        }
        // 商品請求タイミングの不正メッセージ
        if (false !== ($invalid_product_billing_timing = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_TIMING))) {
            $invalid_product_billing_timing = self::getNotice($invalid_product_billing_timing, self::NOTICE_TYPE__ERROR);
        }
        // 商品請求間隔の不正メッセージ
        if (false !== ($invalid_product_billing_frequency = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_FREQUENCY))) {
            $invalid_product_billing_frequency = self::getNotice($invalid_product_billing_frequency, self::NOTICE_TYPE__ERROR);
        }
        // 支払回数の不正メッセージ
        if (false !== ($invalid_product_billing_cycle = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_CYCLE))) {
            $invalid_product_billing_cycle = self::getNotice($invalid_product_billing_cycle, self::NOTICE_TYPE__ERROR);
        }
        // 商品無料トライアルの不正メッセージ
        if (false !== ($invalid_product_free_trial_days = get_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_FREE_TRIAL_DAYS))) {
            $invalid_product_free_trial_days = self::getNotice($invalid_product_free_trial_days, self::NOTICE_TYPE__ERROR);
        }

        // 商品コードのパラメータ名
        $param_product_code = self::PARAMETER__PRODUCT_CODE;
        // 商品価格のパラメータ名
        $param_product_price = self::PARAMETER__PRODUCT_PRICE;
        // 商品提供者名のパラメータ名
        $param_product_provider_name = self::PARAMETER__PRODUCT_PROVIDER_NAME;
        // 商品名のパラメータ名
        $param_product_name = self::PARAMETER__PRODUCT_NAME;
        // 商品通貨のパラメータ名
        $param_product_currency = self::PARAMETER__PRODUCT_CURRENCY;
        // 商品ボタン名のパラメータ名
        $param_product_button_name = self::PARAMETER__PRODUCT_BUTTON_NAME;
        // 商品請求タイミングのパラメータ名
        $param_product_billing_timing = self::PARAMETER__PRODUCT_BILLING_TIMING;
        // 商品請求間隔のパラメータ名
        $param_product_billing_frequency = self::PARAMETER__PRODUCT_BILLING_FREQUENCY;
        // 支払回数のパラメータ名
        $param_product_billing_cycle = self::PARAMETER__PRODUCT_BILLING_CYCLE;
        // 商品無料トライアルのパラメータ名
        $param_product_free_trial_days = self::PARAMETER__PRODUCT_FREE_TRIAL_DAYS;

        // 商品請求タイミングの「分割」のパラメータ値
        $param_value_product_billing_timing_subscription = self::STRIPE_BILLING_TIMING__SUBSCRIPTION;

        // 商品コードをURLクエリー又はTRANSIENTから取得
        if (
          (isset($_REQUEST[self::PARAMETER__PRODUCT_CODE]) && $product_code = $_REQUEST[self::PARAMETER__PRODUCT_CODE]) ||
          ($product_code = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_CODE))
        ) {
            // OPTIONSテーブルから商品情報を取得
            $product = $this->getProduct($product_code);
        }

        // 商品価格をTRANSIENTから取得
        if (false === ($product_price = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PRICE))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品価格から取得
                $product_price = $product->price;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_PRICE])) {
                $product_price = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_PRICE]));
            }
        }
        // 商品提供者名をoptionsテーブルから取得
        if (false === ($product_provider_name = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PROVIDER_NAME))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品提供者名から取得
                $product_provider_name = $product->provider_name;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_PROVIDER_NAME])) {
                $product_provider_name = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_PROVIDER_NAME]));
            }
        }
        // 商品名をoptionsテーブルから取得
        if (false === ($product_name = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_NAME))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品名から取得
                $product_name = $product->name;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_NAME])) {
                $product_name = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_NAME]));
            }
        }
        // 商品通貨をoptionsテーブルから取得
        if (false === ($product_currency = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_CURRENCY))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品通貨から取得
                $product_currency = $product->currency;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_CURRENCY])) {
                $product_currency = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_CURRENCY]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_currency = 'JPY';
            }
        }
        // 商品ボタン名をoptionsテーブルから取得
        if (false === ($product_button_name = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BUTTON_NAME))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品ボタン名から取得
                $product_button_name = $product->button_name;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_BUTTON_NAME])) {
                $product_button_name = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_BUTTON_NAME]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_button_name = __('Buy now', GSSC);
            }
        }
        // 商品請求タイミングをoptionsテーブルから取得
        if (false === ($product_billing_timing = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_TIMING))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品請求タイミングから取得
                $product_billing_timing = $product->billing_timing;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_BILLING_TIMING])) {
                $product_billing_timing = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_BILLING_TIMING]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_billing_timing = '';
            }
        }
        // 商品請求間隔をoptionsテーブルから取得
        if (false === ($product_billing_frequency = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_FREQUENCY))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品請求間隔から取得
                $product_billing_frequency = $product->billing_frequency;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_BILLING_FREQUENCY])) {
                $product_billing_frequency = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_BILLING_FREQUENCY]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_billing_frequency = '';
            }
        }
        // 支払回数をoptionsテーブルから取得
        if (false === ($product_billing_cycle = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_CYCLE))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の支払回数から取得
                $product_billing_cycle = $product->billing_cycle;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_BILLING_CYCLE])) {
                $product_billing_cycle = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_BILLING_CYCLE]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_billing_cycle = '';
            }
        }
        // 商品無料トライアルをoptionsテーブルから取得
        if (false === ($product_free_trial_days = get_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_FREE_TRIAL_DAYS))) {
            // 登録済み商品情報がある場合
            if (isset($product) && $product) {
                // 登録済み商品情報の商品無料トライアルから取得
                $product_free_trial_days = $product->free_trial_days;
            }
            // 商品複製による初期値を受け取る場合
            else if (isset($_REQUEST[self::PARAMETER__PRODUCT_FREE_TRIAL_DAYS])) {
                $product_free_trial_days = trim(sanitize_text_field($_REQUEST[self::PARAMETER__PRODUCT_FREE_TRIAL_DAYS]));
            }
            // 登録済み商品情報がない場合
            else {
                // デフォルト
                $product_free_trial_days = '';
            }
        }
        // StripeのプランID
        $stripe_plan_id = '';
        if (isset($product) && $product && property_exists($product, 'stripe_plan_id') && $product->stripe_plan_id) {
            $stripe_plan_id =
                '<tr><th scope="row"><label>' . __('Strip Plan ID / Price ID', GSSC) . '：</label></th>' .
                '<td>' . $product->stripe_plan_id . '</td></tr>';
        }
        $product_code_hide_style = '';
        $disable_billing_timing = '';
        $disable_billing_frequency = '';
        $disable_billing_cycle = '';
        $disable_free_trial_days = '';
        // 商品がある場合
        if (isset($product) && $product) {
            // 定期の場合
            if ($product->billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                $subscription_edit_mode = true;
                $disable_product_price = 'disabled';
                $disable_product_provider_name = 'disabled';
                $disable_product_name = 'disabled';
                $disable_product_currency = 'disabled';
                // $disable_product_button_name = 'disabled';
            }
            $disable_billing_timing = 'disabled';
            $disable_billing_frequency = 'disabled';
            $disable_billing_cycle = 'disabled';
            $disable_free_trial_days = 'disabled';
        }
        // 商品がない場合
        else {
            if ($product_billing_timing != self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                $disable_billing_frequency = 'disabled';
                $disable_billing_cycle = 'disabled';
                $disable_free_trial_days = 'disabled';
            }
            $product_code_hide_style = 'style="display: none;"';
        }

        // nonceフィールドを生成・取得
        $nonce_field = wp_nonce_field(self::CREDENTIAL_ACTION__PRODUCT_EDIT, self::CREDENTIAL_NAME__PRODUCT_EDIT, true, false);

        // STRIPEの通貨リストのOPTIONタグを生成・取得
        $product_currency_options = self::makeHtmlSelectOptions($this->stripe_currencies, $product_currency, self::LABEL);

        // 商品請求タイミングリストのRADIOタグを生成・取得
        $product_billing_timing_options = self::makeHtmlSelectOptions($this->stripe_billing_timing_list, $product_billing_timing, self::LABEL);

        // 商品請求頻度リストのOPTIONタグを生成・取得
        $product_billing_frequency_options = self::makeHtmlSelectOptions($this->stripe_billing_frequency_list, $product_billing_frequency, self::LABEL);

        // 送信ボタンを生成・取得
        $submit_button = get_submit_button(__('Save', GSSC));

        $title = __('Product registration', GSSC); // 新規登録
        // 登録済み商品情報がある場合
        if (isset($product) && $product) {
            $title = __('Product edit', GSSC);
        }

        // HTMLを出力
        echo <<< EOM
            <div class="wrap">
            <h2>{$title}</h2>
            {$complete_message}
            {$invalid_product_price}
            {$invalid_product_provider_name}
            {$invalid_product_name}
            {$invalid_product_currency}
            {$invalid_product_button_name}
            {$invalid_product_billing_timing}
            {$invalid_product_billing_frequency}
            {$invalid_product_billing_cycle}
            {$invalid_product_free_trial_days}
            <form action="" method='post' id="simple-stripe-checkout-product-edit-form">
                {$nonce_field}
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr {$product_code_hide_style}>
                            <th scope="row">
EOM;
        echo __('Short code for this product', GSSC);
        echo <<< EOM
                            </th>
                            <td><kbd>[gssc code={$product_code}]</kbd><input type="hidden" name="{$param_product_code}" value="{$product_code}"/></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_price}">
EOM;
        echo __('Price', GSSC);
        echo <<< EOM
                              ：</label></th>
                            <td>
EOM;
        if (isset($subscription_edit_mode) && $subscription_edit_mode === true) {
            echo $product_price . '<input type="hidden" name="' . $param_product_price . '" value="' . $product_price . '" />';
        } else {
            echo '<input type="text" name="' . $param_product_price . '" value="' . $product_price . '" class="regular-text" required />';
        }
        echo <<< EOM
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_provider_name}">
EOM;
        echo __('Provider', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
EOM;
        if (isset($subscription_edit_mode) && $subscription_edit_mode === true) {
            echo $product_provider_name . '<input type="hidden" name="' . $param_product_provider_name . '" value="' . $product_provider_name . '" />';
        } else {
            echo '<input type="text" name="' . $param_product_provider_name . '" value="' . $product_provider_name . '" class="regular-text" required />';
        }
        $label_product_name = __('Product name', GSSC);
        echo <<< EOM
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_name}">{$label_product_name}：</label></th>
                            <td>
EOM;
        if (isset($subscription_edit_mode) && $subscription_edit_mode === true) {
            echo $product_name . '<input type="hidden" name="' . $param_product_name . '" value="' . $product_name . '" />';
        } else {
            echo '<input type="text" name="' . $param_product_name . '" value="' . $product_name . '" class="regular-text" required />';
        }
        echo <<< EOM
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_currency}">
EOM;
        echo __('Currency', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
EOM;
        if (isset($subscription_edit_mode) && $subscription_edit_mode === true) {
            echo $product_currency . '<input type="hidden" name="' . $param_product_currency . '" value="' . $product_currency . '" />';
        } else {
            echo '<select name="' . $param_product_currency . '" class="postform">' . $product_currency_options . '</select>';
        }
        echo <<< EOM
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_button_name}">
EOM;
        echo __('Button label', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td><input type="text" name="{$param_product_button_name}" value="{$product_button_name}" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_billing_timing}">
EOM;
        echo __('Billing timing', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td><select name="{$param_product_billing_timing}" class="postform" {$disable_billing_timing} onchange="
                              var elmBillingFrequency = document.getElementById('{$param_product_billing_frequency}');
                              var elmBillingCycle = document.getElementById('{$param_product_billing_cycle}');
                              var elmFreeTrialDays = document.getElementById('{$param_product_free_trial_days}');
                              if (this.value != '{$param_value_product_billing_timing_subscription}') {
                                elmBillingFrequency.disabled = true;
                                elmBillingFrequency.value = null;
                                elmBillingCycle.disabled = true;
                                elmBillingCycle.value = null;
                                elmFreeTrialDays.disabled = true;
                                elmFreeTrialDays.value = null;
                              } else {
                                elmBillingFrequency.disabled = false;
                                elmBillingCycle.disabled = false;
                                elmFreeTrialDays.disabled = false;
                              }
                            "><option></option>{$product_billing_timing_options}</select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_billing_frequency}">
EOM;
        echo __('Billing frequency', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td><select id="{$param_product_billing_frequency}" name="{$param_product_billing_frequency}" class="postform" {$disable_billing_frequency}><option></option>{$product_billing_frequency_options}</select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_billing_cycle}">
EOM;
        echo __('Billing cycle', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td><input id="{$param_product_billing_cycle}" type="number" step="1" min="0" max="1200" name="{$param_product_billing_cycle}" value="{$product_billing_cycle}" class="regular-text" {$disable_billing_frequency}/><br>
EOM;
        echo __('Set 0 for indefinite period', GSSC);
        echo <<< EOM
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_product_free_trial_days}">
EOM;
        echo __('Free trial', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
                                <input type="number" step="1" min="0" max="365" id="{$param_product_free_trial_days}" name="{$param_product_free_trial_days}" value="{$product_free_trial_days}" class="regular-text" {$disable_free_trial_days}/>日<br/>
                                ※
EOM;
        echo __('If you want to start immediately, set it to 0 days.', GSSC);
        echo <<< EOM
                             </td>
                        </tr>
                        {$stripe_plan_id}
                    </tbody>
                </table>
                {$submit_button}
            </form>
            </div>
EOM;
    }

    /**
     * 商品情報を保存するcallback関数
     */
    function save_product() {
        // nonceで設定したcredentialをPOST受信した場合
        if (isset($_POST[self::CREDENTIAL_NAME__PRODUCT_EDIT]) && $_POST[self::CREDENTIAL_NAME__PRODUCT_EDIT]) {
            // nonceで設定したcredentialのチェック結果が問題ない場合
            if (check_admin_referer(self::CREDENTIAL_ACTION__PRODUCT_EDIT, self::CREDENTIAL_NAME__PRODUCT_EDIT)) {
                // 商品コードをPOSTから取得
                $product_code = intval(trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_CODE])));
                // 商品価格をPOSTから取得
                $product_price = floatval(trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_PRICE])));
                // 商品提供者名をPOSTから取得
                $product_provider_name = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_PROVIDER_NAME]));
                // 商品名をPOSTから取得
                $product_name = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_NAME]));
                // 商品通貨をPOSTから取得
                $product_currency = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_CURRENCY]));
                // 商品ボタン名をPOSTから取得
                $product_button_name = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_BUTTON_NAME]));
                // 商品請求タイミングをPOSTから取得
                $product_billing_timing = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_BILLING_TIMING]));
                // 商品請求間隔をPOSTから取得
                $product_billing_frequency = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_BILLING_FREQUENCY]));
                // 支払回数をPOSTから取得
                $product_billing_cycle = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_BILLING_CYCLE]));
                // 商品無料トライアルをPOSTから取得
                $product_free_trial_days = trim(sanitize_text_field($_POST[self::PARAMETER__PRODUCT_FREE_TRIAL_DAYS]));
                $valid = true;
                // 商品価格が正しくない場合
                if (array_key_exists($product_currency, $this->stripe_currencies)) {
                    if ($product_price < $this->stripe_currencies[$product_currency]['min']) {
                        // 商品価格の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                        set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PRICE, __('The item price is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                        // 有効フラグをFalse
                        $valid = false;
                    }
                }
                // 商品提供者名が正しくない場合
                if (strlen($product_provider_name) === 0) {
                    // 商品提供者名の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PROVIDER_NAME, __('The product provider name is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 商品名が正しくない場合
                if (strlen($product_name) === 0) {
                    // 商品名の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_NAME, __('The product name is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 商品通貨が正しくない場合
                if (!array_key_exists($product_currency, $this->stripe_currencies)) {
                    // 商品通貨の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_CURRENCY, 'e ' . __('The product currency is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 商品ボタン名が正しくない場合
                if (strlen($product_button_name) === 0) {
                    // 商品ボタン名の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BUTTON_NAME, 'd ' . __('The product button name is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 新規の場合(商品コードがない場合)
                if ($product_code <= 0) {
                    // 商品請求タイミングが正しくない場合
                    if (strlen($product_billing_timing) === 0) {
                        // 商品請求タイミングの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                        set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_TIMING, __('The product billing timing is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                        // 有効フラグをFalse
                        $valid = false;
                    }
                    if ($product_billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                        // 商品請求間隔が正しくない場合
                        if (strlen($product_billing_frequency) === 0) {
                            // 商品請求間隔の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                            set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_FREQUENCY, 'b ' . __('The product billing interval is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                            // 有効フラグをFalse
                            $valid = false;
                        }
                        // 支払回数が正しくない場合
                        if (strlen($product_billing_cycle) === 0) {
                            // 支払回数の設定し直しを促すメッセージをTRANSIENTに5秒間保持
                            set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_CYCLE, 'b ' . __('The product billing cycle is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                            // 有効フラグをFalse
                            $valid = false;
                        }
                        // 商品無料トライアルが正しくない場合
                        if (strlen($product_free_trial_days) === 0) {
                            // 商品無料トライアルの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                            set_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_FREE_TRIAL_DAYS, 'a '.__('The product free trial days are incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                            // 有効フラグをFalse
                            $valid = false;
                        }
                    }
                }
                // 有効フラグがTrueの場合(商品情報が正しい場合)
                if ($valid) {
                    // 保存処理

                    // 登録済みの商品情報リストをoptionsテーブルから取得
                    $product_list = get_option(self::OPTION_KEY__PRODUCT_LIST);
                    // 登録済みの商品情報リストが0件の場合
                    if (is_null($product_list)) {
                        // 商品情報リストを初期化
                        $product_list = array();
                    }
                    // 登録済みの商品情報リストが1件以上ある場合
                    else {
                        // シリアライズされている商品情報リストをアンシリアライズ
                        $product_list = unserialize($product_list);
                    }
                    // 更新の場合(商品コードがある場合)
                    if ($product_code > 0) {
                        // 有効フラグを一旦FALSEにする
                        // 以下で更新対象があればTRUEに、無ければFALSEのまま不正時処理に進む
                        $valid = false;
                        for ($i = 0; $i < count($product_list); $i++) {
                            if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                                // 商品コードが一致する場合(更新対象の商品情報の場合)
                                if ($product_list[$i]->code == $product_code) {
                                    // 商品情報の各値をセット
                                    $product_list[$i]->price                     = $product_price;
                                    $product_list[$i]->provider_name             = $product_provider_name;
                                    $product_list[$i]->name                      = $product_name;
                                    $product_list[$i]->currency                  = $product_currency;
                                    $product_list[$i]->button_name               = $product_button_name;
                                    // $product_list[$i]->billing_timing    = $product_billing_timing;
                                    // $product_list[$i]->billing_frequency = $product_billing_frequency;
                                    // $product_list[$i]->billing_cycle = $product_billing_cycle;
                                    // $product_list[$i]->free_trial_days   = $product_free_trial_days;
                                    // 更新対象があったので有効フラグをTRUEに戻す
                                    $valid = true;
                                    break;
                                }
                            }
                        }
                    }
                    // 新規の場合(商品コードがない場合)
                    else {
                        // 定期の場合
                        if ($product_billing_timing === self::STRIPE_BILLING_TIMING__SUBSCRIPTION) {
                            // STRIPEのAPIを初期化
                            $this->initStripeApi();
                            // Stripeに商品と価格を新規登録
                            try {
                                $stripe_product = $this->stripe->products->create([
                                    'name' => $product_name,
                                ]);
                                if ($product_billing_frequency === self::STRIPE_BILLING_FREQUENCY__MONTHLY) {
                                    $recurring_interval = 'month';
                                } else if ($product_billing_frequency === self::STRIPE_BILLING_FREQUENCY__YEARLY) {
                                    $recurring_interval = 'year';
                                }
                                $stripe_plan = $this->stripe->plans->create([
                                    'amount' => $product_price,
                                    'currency' => $product_currency,
                                    'interval' => $recurring_interval,
                                    'product' => $stripe_product->id,
                                    'trial_period_days' => $product_free_trial_days,
                                ]);
                            } catch (\Stripe\Exception\ApiErrorException $e) {
                                // ユーザが入力した商品無料トライアルをTRANSIENTに5秒間保持
                                $error = $e->getError();
                                set_transient(self::TRANSIENT_KEY__ERROR_STRIPE_PRODUCT_REGISTER, __('Failed to register the product on Stripe.', GSSC) . "<br>[{$error->type}]<br>{$error->message}", self::TRANSIENT_TIME_LIMIT);
                                $valid = false;
                            }
                            // StripeにWebhookを登録
                            $this->registerWebhook();
                        }
                        // print_r($stripe_plan);
                        // die(' finish ');
                        if ($valid) {
                            /*
                            $stripe->prices->create([
                              'unit_amount' => 2000,
                              'currency' => 'jpy',
                              'recurring' => ['interval' => 'month'],
                              'product' => 'prod_HFwiPNJo6Kgp99',
                            ]);
                            */
                            // 最も大きい商品コードを取得
                            $max_product_code = 0;
                            for ($i = 0; $i < count($product_list); $i++) {
                                if ($product_list[$i] instanceof SimpleStripeCheckout_Product) {
                                    if ($product_list[$i]->code > $max_product_code) {
                                        $max_product_code = $product_list[$i]->code;
                                    }
                                }
                            }
                            // 商品情報の各値をセット
                            $product = new SimpleStripeCheckout_Product();
                            $product->code              = $max_product_code + 1;
                            $product->price             = $product_price;
                            $product->provider_name     = $product_provider_name;
                            $product->name              = $product_name;
                            $product->currency          = $product_currency;
                            $product->button_name       = $product_button_name;
                            $product->billing_timing    = $product_billing_timing;
                            $product->billing_frequency = $product_billing_frequency;
                            $product->billing_cycle     = $product_billing_cycle;
                            $product->free_trial_days   = $product_free_trial_days;
                            $product->stripe_plan_id    = $stripe_plan->id;
                            // 商品情報リストに追加
                            $product_list[] = $product;
                        }
                    }
                    // 有効フラグがTrueの場合(商品情報の保存が完了した場合)
                    if ($valid) {
                        // 商品情報リストをシリアライズしてoptionsテーブルに保存
                        update_option(self::OPTION_KEY__PRODUCT_LIST, serialize($product_list));

                        // 保存完了メッセージをTRANSIENTに5秒間保持
                        set_transient(self::TRANSIENT_KEY__SAVE_PRODUCT_INFO, __('The product information has been saved.', GSSC), self::TRANSIENT_TIME_LIMIT);

                        // (一応)商品価格の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PRICE);
                        // (一応)商品提供者名の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_PROVIDER_NAME);
                        // (一応)商品名の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_NAME);
                        // (一応)商品通貨の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_CURRENCY);
                        // (一応)商品ボタン名の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BUTTON_NAME);
                        // (一応)商品請求タイミングの不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_TIMING);
                        // (一応)商品請求間隔の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_FREQUENCY);
                        // (一応)支払回数の不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_BILLING_CYCLE);
                        // (一応)商品無料トライアルの不正メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__INVALID_PRODUCT_FREE_TRIAL_DAYS);

                        // (一応)ユーザが入力した商品価格をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PRICE);
                        // (一応)ユーザが入力した商品提供者名をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PROVIDER_NAME);
                        // (一応)ユーザが入力した商品名をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_NAME);
                        // (一応)ユーザが入力した商品通貨をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_CURRENCY);
                        // (一応)ユーザが入力した商品ボタン名をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BUTTON_NAME);
                        // (一応)ユーザが入力した商品請求タイミングをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_TIMING);
                        // (一応)ユーザが入力した商品請求間隔をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_FREQUENCY);
                        // (一応)ユーザが入力した支払回数をTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_CYCLE);
                        // (一応)ユーザが入力した商品無料トライアルをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_FREE_TRIAL_DAYS);

                        // (一応)STRIPE商品登録失敗メッセージをTRANSIENTから削除
                        delete_transient(self::TRANSIENT_KEY__ERROR_STRIPE_PRODUCT_REGISTER);
                    }
                }
                // 有効フラグがFalseの場合(商品情報が不正の場合)
                if (!$valid) {
                    // ユーザが入力した商品価格をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PRICE, $product_price, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品提供者名をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_PROVIDER_NAME, $product_provider_name, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品名をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_NAME, $product_name, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品通貨をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_CURRENCY, $product_currency, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品ボタン名をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BUTTON_NAME, $product_button_name, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品請求タイミングをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_TIMING, $product_billing_timing, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品請求間隔をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_FREQUENCY, $product_billing_frequency, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した支払回数をTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_BILLING_CYCLE, $product_billing_cycle, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した商品無料トライアルをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_PRODUCT_FREE_TRIAL_DAYS, $product_free_trial_days, self::TRANSIENT_TIME_LIMIT);
                }
                // 設定画面にリダイレクト
                wp_safe_redirect(menu_page_url(self::SLUG__PRODUCT_EDIT_FORM, false), 303);
            }
        }
    }

    /**
     * サブメニュー「メール設定」押下時の画面を表示するcallback関数
     */
    function show_mail_config_form() {
        // メール設定の保存完了メッセージ
        if (false !== ($complete_message = get_transient(self::TRANSIENT_KEY__SAVE_MAIL_CONFIG))) {
            $complete_message = self::getNotice($complete_message, self::NOTICE_TYPE__SUCCESS);
        }
        // 販売者向け受信メルアドの不正メッセージ
        if (false !== ($invalid_seller_receive_address = get_transient(self::TRANSIENT_KEY__INVALID_SELLER_RECEIVE_ADDRESS))) {
            $invalid_seller_receive_address = self::getNotice($invalid_seller_receive_address, self::NOTICE_TYPE__ERROR);
        }
        // 販売者向け送信元メルアドの不正メッセージ
        if (false !== ($invalid_seller_from_address = get_transient(self::TRANSIENT_KEY__INVALID_SELLER_FROM_ADDRESS))) {
            $invalid_seller_from_address = self::getNotice($invalid_seller_from_address, self::NOTICE_TYPE__ERROR);
        }
        // 購入者向け送信元メルアドの不正メッセージ
        if (false !== ($invalid_buyer_from_address = get_transient(self::TRANSIENT_KEY__INVALID_BUYER_FROM_ADDRESS))) {
            $invalid_buyer_from_address = self::getNotice($invalid_buyer_from_address, self::NOTICE_TYPE__ERROR);
        }
        // 即時決済フラグの不正メッセージ
        if (false !== ($invalid_immediate_settlement = get_transient(self::TRANSIENT_KEY__INVALID_IMMEDIATE_SETTLEMENT))) {
            $invalid_immediate_settlement = self::getNotice($invalid_immediate_settlement, self::NOTICE_TYPE__ERROR);
        }
        // StripeカスタマーポータルログインURLの不正メッセージ
        if (false !== ($invalid_stripe_customer_portal_login_url = get_transient(self::TRANSIENT_KEY__INVALID_STRIPE_CUSTOMER_PORTAL_LOGIN_URL))) {
            $invalid_stripe_customer_portal_login_url = self::getNotice($invalid_stripe_customer_portal_login_url, self::NOTICE_TYPE__ERROR);
        }

        // 販売者向け受信メルアドのパラメータ名
        $param_seller_receive_address = self::PARAMETER__SELLER_RECEIVE_ADDRESS;
        // 販売者向け送信元メルアドのパラメータ名
        $param_seller_from_address = self::PARAMETER__SELLER_FROM_ADDRESS;
        // 購入者向け送信元メルアドのパラメータ名
        $param_buyer_from_address = self::PARAMETER__BUYER_FROM_ADDRESS;
        // 即時決済フラグのパラメータ名
        $param_immediate_settlement = self::PARAMETER__IMMEDIATE_SETTLEMENT;
        // StripeカスタマーポータルログインページのURLのパラメータ名
        $param_stripe_customer_portal_login_url = self::PARAMETER__STRIPE_CUSTOMER_PORTAL_LOGIN_URL;

        // 販売者向け受信メルアドをTRANSIENTから取得
        if (false === ($seller_receive_address = get_transient(self::TRANSIENT_KEY__TEMP_SELLER_RECEIVE_ADDRESS))) {
            // 無ければoptionsテーブルから取得
            $seller_receive_address = get_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS);
        }
        // 販売者向け送信元メルアドをTRANSIENTから取得
        if (false === ($seller_from_address = get_transient(self::TRANSIENT_KEY__TEMP_SELLER_FROM_ADDRESS))) {
            // 無ければoptionsテーブルから取得
            $seller_from_address = get_option(self::OPTION_KEY__SELLER_FROM_ADDRESS);
        }
        // 購入者向け送信元メルアドをTRANSIENTから取得
        if (false === ($buyer_from_address = get_transient(self::TRANSIENT_KEY__TEMP_BUYER_FROM_ADDRESS))) {
            // 無ければoptionsテーブルから取得
            $buyer_from_address = get_option(self::OPTION_KEY__BUYER_FROM_ADDRESS);
        }
        // 即時決済フラグをTRANSIENTから取得
        if (false === ($immediate_settlement = get_transient(self::TRANSIENT_KEY__TEMP_IMMEDIATE_SETTLEMENT))) {
            // 無ければoptionsテーブルから取得
            $immediate_settlement = get_option(self::OPTION_KEY__IMMEDIATE_SETTLEMENT);
        }
        // StripeカスタマーポータルログインURLをTRANSIENTから取得
        if (false === ($stripe_customer_portal_login_url = get_transient(self::TRANSIENT_KEY__TEMP_STRIPE_CUSTOMER_PORTAL_LOGIN_URL))) {
            // 無ければoptionsテーブルから取得
            $stripe_customer_portal_login_url = self::getStripeCustomerPortalLoginURL();
        }

        $checked_immediate_settlement_on = '';
        if ($immediate_settlement === 'ON') {
            $checked_immediate_settlement_on = 'checked="checked"';
        }
        $checked_immediate_settlement_off = '';
        if ($immediate_settlement === 'OFF') {
            $checked_immediate_settlement_off = 'checked="checked"';
        }

        // nonceフィールドを生成・取得
        $nonce_field = wp_nonce_field(self::CREDENTIAL_ACTION__MAIL_CONFIG, self::CREDENTIAL_NAME__MAIL_CONFIG, true, false);
        // 送信ボタンを生成・取得
        $submit_button = get_submit_button(__('Save', GSSC));
        $title = __('Mail setting', GSSC); // メール設定
        // HTMLを出力
        echo <<< EOM
            <div class="wrap">
            <h2>{$title}</h2>
            {$complete_message}
            {$invalid_seller_receive_address}
            {$invalid_seller_from_address}
            {$invalid_buyer_from_address}
            {$invalid_immediate_settlement}
            {$invalid_stripe_customer_portal_login_url}
            <form action="" method='post' id="simple-stripe-checkout-initial-config-form">
                {$nonce_field}
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row" colspan=2><h3>
EOM;
        echo '▼' . __('Email settings for seller', GSSC);
        echo <<< EOM
                            </h3></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_seller_receive_address}">&nbsp;&nbsp;&nbsp;&nbsp;
EOM;
        echo __('Received email address', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
                                <input type="text" name="{$param_seller_receive_address}" value="{$seller_receive_address}" class="regular-text"/>
                                <p class="description" id="tagline-description">※
EOM;
        echo __('If there are more than one, separate them with commas.', GSSC);
        echo <<< EOM
                                  </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_seller_from_address}">&nbsp;&nbsp;&nbsp;&nbsp;
EOM;
        echo __('Sender email address', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
                                <input type="text" name="{$param_seller_from_address}" value="{$seller_from_address}" class="regular-text"/>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" colspan=2><h3>▼
EOM;
        echo __('Email settings for buyer', GSSC);
        echo <<< EOM
                            </h3></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="{$param_buyer_from_address}">&nbsp;&nbsp;&nbsp;&nbsp;
EOM;
        echo __('Sender email address', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
                                <input type="text" name="{$param_buyer_from_address}" value="{$buyer_from_address}" class="regular-text"/>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" colspan=2>
                                <h3>▼
EOM;
        echo __('Do you want to make an immediate payment?', GSSC);
        echo <<< EOM
                                </h3>
                                <div style="padding-left: 21px;">
                                    <fieldset>
                                        <legend class="screen-reader-text"><span>
EOM;
        echo __('Do you want to make an immediate payment?', GSSC);
        echo <<< EOM
                                        </span></legend>
                                        <label>　<input type="radio" name="{$param_immediate_settlement}" value="ON" {$checked_immediate_settlement_on}> <span class="date-time-text format-i18n">ON</span></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <label>　<input type="radio" name="{$param_immediate_settlement}" value="OFF" {$checked_immediate_settlement_off}> <span class="date-time-text format-i18n">OFF</span><p style="margin-top: 12px;" class="description" id="tagline-description">※
EOM;
        echo __('If it is OFF, an email will be sent to confirm the payment after 24 hours, but in reality, a link to confirm the payment will be sent and you will need to confirm it manually. If you do not receive the e-mail due to unsolicited e-mail, you need to check it regularly on the Stripe management screen.', GSSC);
        echo <<< EOM
                                        </p></label><br>
                                    </fieldset>
                                </div>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row" colspan=2><h3>
EOM;
        echo '▼' . __('Stripe Customer Portal', GSSC);
        echo <<< EOM
                            </h3></th>
                        </tr>
                        <tr>
                            <th scope="row" style="white-space: nowrap;"><label for="{$param_stripe_customer_portal_login_url}">&nbsp;&nbsp;&nbsp;&nbsp;
EOM;
        echo __('Stripe customer portal login url', GSSC);
        echo <<< EOM
                            ：</label></th>
                            <td>
                                <input type="text" name="{$param_stripe_customer_portal_login_url}" value="{$stripe_customer_portal_login_url}" class="regular-text"/>
                            </td>
                        </tr>
                    </tbody>
                </table>
                {$submit_button}
            </form>
            </div>
EOM;
    }

    /**
     * メール設定を保存するcallback関数
     */
    function save_mail_config() {
        // nonceで設定したcredentialをPOST受信した場合
        if (isset($_POST[self::CREDENTIAL_NAME__MAIL_CONFIG]) && $_POST[self::CREDENTIAL_NAME__MAIL_CONFIG]) {
            // nonceで設定したcredentialのチェック結果が問題ない場合
            if (check_admin_referer(self::CREDENTIAL_ACTION__MAIL_CONFIG, self::CREDENTIAL_NAME__MAIL_CONFIG)) {
                // 販売者向け受信メルアドをPOSTから取得
                $seller_receive_address = trim(sanitize_text_field($_POST[self::PARAMETER__SELLER_RECEIVE_ADDRESS]));
                // 販売者向け送信元メルアドをPOSTから取得
                $seller_from_address = trim(sanitize_text_field($_POST[self::PARAMETER__SELLER_FROM_ADDRESS]));
                // 購入者向け送信元メルアドをPOSTから取得
                $buyer_from_address = trim(sanitize_text_field($_POST[self::PARAMETER__BUYER_FROM_ADDRESS]));
                // 即時決済フラグをPOSTから取得
                $immediate_settlement = trim(sanitize_text_field($_POST[self::PARAMETER__IMMEDIATE_SETTLEMENT]));
                // StripeカスタマーポータルログインURLをPOSTから取得
                $stripe_customer_portal_login_url = trim(sanitize_text_field($_POST[self::PARAMETER__STRIPE_CUSTOMER_PORTAL_LOGIN_URL]));

                $valid = true;

                // 販売者向け受信メルアドが正しくない場合
                if (!preg_match(self::REGEXP_MULTIPLE_ADDRESS, $seller_receive_address)) {
                    // 販売者向け受信メルアドの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_SELLER_RECEIVE_ADDRESS, __('The received email address for the seller is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 販売者向け送信元メルアドが正しくない場合
                if (!preg_match(self::REGEXP_SINGLE_ADDRESS, $seller_from_address)) {
                    // 販売者向け送信元メルアドの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_SELLER_FROM_ADDRESS, __('The sender email address for the seller is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 購入者向け送信元メルアドが正しくない場合
                if (!preg_match(self::REGEXP_SINGLE_ADDRESS, $buyer_from_address)) {
                    // 購入者向け送信元メルアドの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_BUYER_FROM_ADDRESS, __('The sender email address for the purchaser is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // 即時決済フラグが正しくない場合
                if ($immediate_settlement !== 'ON' && $immediate_settlement !== 'OFF') {
                    // 即時決済フラグの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_IMMEDIATE_SETTLEMENT, __('The ON / OFF of immediate payment is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }
                // StripeカスタマーポータルURLが正しくない場合
                if (strlen($stripe_customer_portal_login_url) === 0) {
                    // StripeカスタマーポータルURLの設定し直しを促すメッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__INVALID_STRIPE_CUSTOMER_PORTAL_LOGIN_URL, __('The stripe customer portal login url is incorrect.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // 有効フラグをFalse
                    $valid = false;
                }

                // 有効フラグがTrueの場合(各メルアド、即時決済フラグが正しい場合)
                if ($valid) {
                    // 保存処理
                    // 販売者向け受信メルアドをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__SELLER_RECEIVE_ADDRESS, $seller_receive_address);
                    // 販売者向け受信メルアドをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__SELLER_FROM_ADDRESS, $seller_from_address);
                    // 購入者向け送信元メルアドをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__BUYER_FROM_ADDRESS, $buyer_from_address);
                    // 即時決済フラグをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__IMMEDIATE_SETTLEMENT, $immediate_settlement);
                    // StripeカスタマーポータルログインURLをoptionsテーブルに保存
                    update_option(self::OPTION_KEY__STRIPE_CUSTOMER_PORTAL_LOGIN_URL, $stripe_customer_portal_login_url);
                    // 保存が完了したら、完了メッセージをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__SAVE_MAIL_CONFIG, __('Your email settings have been saved.', GSSC), self::TRANSIENT_TIME_LIMIT);
                    // (一応)販売者向け受信メルアドの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_SELLER_RECEIVE_ADDRESS);
                    // (一応)販売者向け送信元メルアドの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_SELLER_FROM_ADDRESS);
                    // (一応)購入者向け送信元メルアドの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_BUYER_FROM_ADDRESS);
                    // (一応)即時決済フラグの不正メッセージをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__INVALID_IMMEDIATE_SETTLEMENT);
                    // (一応)ユーザが入力した販売者向け受信メルアドをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_SELLER_RECEIVE_ADDRESS);
                    // (一応)ユーザが入力した販売者向け送信元メルアドをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_SELLER_FROM_ADDRESS);
                    // (一応)ユーザが入力した購入者向け送信元メルアドをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_BUYER_FROM_ADDRESS);
                    // (一応)ユーザが入力した即時決済フラグをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_IMMEDIATE_SETTLEMENT);
                    // (一応)ユーザが入力したStripeカスタマーポータルログインURLをTRANSIENTから削除
                    delete_transient(self::TRANSIENT_KEY__TEMP_STRIPE_CUSTOMER_PORTAL_LOGIN_URL);
                }
                // 有効フラグがFalseの場合(何れかのメルアドが不正の場合)
                else {
                    // ユーザが入力した販売者向け受信メルアドをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_SELLER_RECEIVE_ADDRESS, $seller_receive_address, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した販売者向け送信元メルアドをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_SELLER_FROM_ADDRESS, $seller_from_address, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した購入者向け送信元メルアドをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_BUYER_FROM_ADDRESS, $buyer_from_address, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力した即時決済フラグをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_IMMEDIATE_SETTLEMENT, $immediate_settlement, self::TRANSIENT_TIME_LIMIT);
                    // ユーザが入力したStripeカスタマーポータルログインURLをTRANSIENTに5秒間保持
                    set_transient(self::TRANSIENT_KEY__TEMP_STRIPE_CUSTOMER_PORTAL_LOGIN_URL, $stripe_customer_portal_login_url, self::TRANSIENT_TIME_LIMIT);
                    // (一応)メール設定の保存完了メッセージを削除
                    delete_transient(self::TRANSIENT_KEY__SAVE_MAIL_CONFIG);
                }
                // 設定画面にリダイレクト
                wp_safe_redirect(menu_page_url(self::SLUG__MAIL_CONFIG_FORM, false), 303);
            }
        }
    }

    /**
     * サブメニュー「Webhook設定」押下時の画面を表示するcallback関数
     */
    function show_hook_config_form() {
        // メール設定の保存完了メッセージ
        if (false !== ($complete_message = get_transient(self::TRANSIENT_KEY__SAVE_HOOK_CONFIG))) {
            $complete_message = self::getNotice($complete_message, self::NOTICE_TYPE__SUCCESS);
        }

        // nonceフィールドを生成・取得
        $nonce_field = wp_nonce_field(self::CREDENTIAL_ACTION__HOOK_CONFIG, self::CREDENTIAL_NAME__HOOK_CONFIG, true, false);

        // Home URL
        $home_url = home_url();

        // Webhook URL
        $expected_webhook_url = $home_url . '/?' . self::SLUG__PAY_SUBSCRIPTION . '=true';

        // STRIPEのAPIを初期化
        $this->initStripeApi();

        // StripeのWebhookに登録されているか
        $has_registered_webhook = false;
        try {
            $webhook_list = $this->stripe->webhookEndpoints->all();
            if (isset($webhook_list) && $webhook_list->data && is_array($webhook_list->data)) {
                for ($i = 0; $i < count($webhook_list->data); $i++) {
                    if ($webhook_list->data[$i]->url === $expected_webhook_url) {
                        $has_registered_webhook = true;
                    }
                }
            }
        } catch (Exception $e) {
            die(__('Unable to get Stripe webhook information.', GSSC));
        }

        // 登録ボタンを生成・取得
        if (!isset($has_registered_webhook) || $has_registered_webhook !== true) {
            $submit_button = get_submit_button(__('Registration', GSSC));
        } else {
            $submit_button = $expected_webhook_url;
        }

        // HTMLを出力
        echo <<< EOM
            <div class="wrap">
            <h2>
EOM;
        echo __('Webhook setting', GSSC);
        echo <<< EOM
            </h2>
            {$complete_message}
            <form action="" method='post' id="simple-stripe-checkout-hook-config-form">
                {$nonce_field}
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="home_url">Home URL：</label></th>
                            <td>{$home_url}</td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="stripe_webhook_url">Stripe Webhook URL：</label></th>
                            <td>
                                <input type="hidden" name="dummy" value="dummy" />
                                {$submit_button}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
            </div>
EOM;
    }

    /**
     * Webhook設定を保存するcallback関数
     */
    function save_hook_config() {
        // nonceで設定したcredentialをPOST受信した場合
        if (isset($_POST[self::CREDENTIAL_NAME__HOOK_CONFIG]) && $_POST[self::CREDENTIAL_NAME__HOOK_CONFIG]) {
            // nonceで設定したcredentialのチェック結果が問題ない場合
            if (check_admin_referer(self::CREDENTIAL_ACTION__HOOK_CONFIG, self::CREDENTIAL_NAME__HOOK_CONFIG)) {

                // STRIPEのAPIを初期化
                $this->initStripeApi();

                // StripeにWebhookを登録
                $this->registerWebhook();

                // 保存が完了したら、完了メッセージをTRANSIENTに5秒間保持
                set_transient(self::TRANSIENT_KEY__SAVE_HOOK_CONFIG, __('Completed registering Webhook on Stripe.', GSSC), self::TRANSIENT_TIME_LIMIT);

                // 設定画面にリダイレクト
                wp_safe_redirect(menu_page_url(self::SLUG__HOOK_CONFIG_FORM, false), 303);
            }
        }
    }

    /**
     * StripeにWebhookを登録
     */
    function registerWebhook() {
        $expected_webhook_url = home_url() . '/?' . self::SLUG__PAY_SUBSCRIPTION . '=true';
        // StripeのWebhookに登録されているか
        $has_registered_webhook = false;
        try {
            $webhook_list = $this->stripe->webhookEndpoints->all();
            if (isset($webhook_list) && $webhook_list->data && is_array($webhook_list->data)) {
                for ($i = 0; $i < count($webhook_list->data); $i++) {
                    if ($webhook_list->data[$i]->url === $expected_webhook_url) {
                        $has_registered_webhook = true;
                    }
                }
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = $e->getError();
            die(__('Unable to get Stripe webhook information.', GSSC) . ' [' . $error->type . ':' . $error->message . ']');
        }
        // StripeにWebhookを登録
        try {
            if ($has_registered_webhook !== true) {
                $webhook_list = $this->stripe->webhookEndpoints->create(array(
                    'url' => home_url() . '/?' . self::SLUG__PAY_SUBSCRIPTION . '=true',
                    'enabled_events' => array(
                      'invoice.payment_succeeded',
                    ),
                ));
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = $e->getError();
            die(__('Unable to register Webhook with Stripe.', GSSC) . ' [' . $error->type . ':' . $error->message . ']');
        }
    }

} // end of class


?>
