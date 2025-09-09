<?php
/*
Plugin Name: UPN plačilni nalog
Plugin URI: https://matejzlatic.com
Description: Doda UPN plačilni nalog s QR kodo v vašo WooCommerce trgovino.
Version: 1.3.0
Author: Matej Zlatic
Author URI: https://matejzlatic.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woocommerce-upn
Domain Path: /languages
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Requires Plugins: woocommerce
WC requires at least: 7.9
WC tested up to: 10.2
Update URI: false
*/

namespace WooCart\UPNalog {

    require_once "vendor/autoload.php";

    // Declare HPOS compatibility
    add_action('before_woocommerce_init', function() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });

    class UPN
    {
        public $account_details;
        public $instructions;

        public function __construct()
        {

            // Get the gateways instance
            $gateways = \WC_Payment_Gateways::instance();

            // Get all available gateways, [id] => Object
            $available_gateways = $gateways->get_available_payment_gateways();
            if (isset($available_gateways['bacs'])) {
                // If the gateway is available, remove the action hooks
                remove_action('woocommerce_thankyou_bacs', array($available_gateways['bacs'], 'thankyou_page'));
                remove_action('woocommerce_email_before_order_table', array($available_gateways['bacs'], 'email_instructions'), 10, 3);
                $this->account_details = $available_gateways['bacs']->account_details;
                $this->instructions = $available_gateways['bacs']->instructions;
                add_action('woocommerce_email_before_order_table', array($this, 'upn_instructions'), 10, 3);
                add_action('woocommerce_order_details_after_customer_details', array($this, 'upn_page'), 20);
                add_shortcode('upn_order_awaiting_payment', array($this, 'upn_order_awaiting_payment'));
            }
        }

        public function genUPN($order, $printTag = true)
        {
            $pngBinary = (new \Media24si\UpnGenerator\UpnGenerator())
                ->setPayerName(sprintf("%s %s", $order->get_billing_first_name(), $order->get_billing_last_name()))
                ->setPayerAddress($order->get_billing_address_1())
                ->setPayerPost(sprintf("%s %s", $order->get_billing_postcode(), $order->get_billing_city()))
                ->setReceiverName($this->account_details[0]['account_name'])
                ->setReceiverAddress(WC()->countries->get_base_address())
                ->setReceiverPost(sprintf("%s %s", WC()->countries->get_base_city(), WC()->countries->get_base_postcode()))
                ->setReceiverIban(preg_replace('/\s+/', '', $this->account_details[0]['iban']))
                ->setAmount($order->get_total())
                ->setCode(apply_filters('upn_code', "OTHR"))
                ->setReference(sprintf(apply_filters('upn_reference', "SI00 %s"), $order->get_order_number()))
                ->setDueDate(new \DateTime($order->get_date_created()))
                ->setPurpose(sprintf(apply_filters('upn_purpose', 'Plačilo naročila %s'), $order->get_order_number()))
                ->png();

            if (empty($pngBinary)) {
                return '';
            }

            $base64 = base64_encode($pngBinary);

            if ($printTag) {
                echo "<br/><img src='data:image/png;base64,{$base64}'><br/>";
            }

            return $base64;
        }

        public function genUPNDescription($order)
        {
            if (empty($this->account_details)) {
                return;
            }

            $bacs_accounts = apply_filters('woocommerce_bacs_accounts', $this->account_details, $order->get_id());

            $bacs_account = (object) $bacs_accounts[0];

?>
            <table class="woocommerce-table shop_table">
                <tbody>
                    <tr>
                        <th>Prejemnik</th>
                        <td>

                            <?php echo wptexturize(wp_kses_post($bacs_account->account_name)); ?></br>
                            <?php echo wptexturize(wp_kses_post(WC()->countries->get_base_address())); ?></br>
                            <?php echo wptexturize(wp_kses_post(sprintf("%s %s", WC()->countries->get_base_city(), WC()->countries->get_base_postcode()))); ?>

                        </td>
                    </tr>
                    <tr>
                        <th>IBAN Prejemnika</th>
                        <td><?php echo wptexturize(wp_kses_post($bacs_account->iban)); ?></td>
                    </tr>
                    <tr>
                        <th>Namen</th>
                        <td><?php echo sprintf(apply_filters('upn_purpose', 'Plačilo naročila %s'), $order->get_order_number()); ?></td>
                    </tr>
                    <tr>
                        <th>Referenca Prejemnika</th>
                        <td><?php echo sprintf(apply_filters('upn_reference', "SI00 %s"), $order->get_order_number()); ?></td>
                    </tr>

                </tbody>
            </table>
<?php
        }

        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function upn_instructions($order, $sent_to_admin, $plain_text = false)
        {
    
            if (!$sent_to_admin && 'bacs' === $order->get_payment_method() && $order->has_status('on-hold')) {
                if ($this->instructions) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
                }
    
                $this->genUPNDescription($order);
    
                // Generate QR but do not print inline in the email
                $base64 = $this->genUPN($order, false);
                if ($base64) {
                    $png = base64_decode($base64);
    
                    add_filter('woocommerce_email_attachments', function ($attachments, $object) use ($png) {
                        if (!empty($png)) {
                            // Create a temporary file name
                            $filename = tempnam(sys_get_temp_dir(), '') . '.png';
                            $gdImg = imagecreatefromstring($png);
                            if ($gdImg !== false) {
                                imagepng($gdImg, $filename);
                                imagedestroy($gdImg);
                                $attachments[] = $filename;
                            }
                        }
                        return $attachments;
                    }, 10, 2);
    
                    echo wp_kses_post('<p>QR koda UPN je priložena kot slika v priponki tega e‑sporočila.</p>');
                }
            }
        }

        /**
         * Output for the order received page.
         *
         * @param object $order WC_Order.
         */
        public function upn_page($order)
        {
			if ('bacs' === $order->get_payment_method() && $order->has_status('on-hold')) {
                echo '</br>';
                echo '<h2 class="woocommerce-column__title">UPN Nalog</h2>';
                $this->genUPNDescription($order);
                $this->genUPN($order);
                if ($this->instructions) {
                    echo wp_kses_post(wpautop(wptexturize(wp_kses_post($this->instructions))));
                }
            }
        }

        /**
         * Shortcode helper.
         */
        public function upn_order_awaiting_payment()
        {
            // Only show if not paid yet
            $order_id = \WC()->session->get('order_awaiting_payment');
            if ($order_id != null) {
                $this->upn_page(wc_get_order($order_id));
            }
        }
    }

    \add_action("woocommerce_init", function () {
        return new UPN();
    });
}
