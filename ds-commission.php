<?php
/**
 * Plugin Name: DrugStoc Commissions  
 * Plugin URI: http://integrahealth.com.ng/integraitlabs.php
 * Description: Display and manage DrugStoc Commissions per order / item.
 * Version: 1.0.0
 * Author: Caleb Chinga | Drugstoc
 * Author URI: http://integrahealth.com.ng
 * Text Domain: cpac
 * Domain Path: /languages
 * License: GPL2
 */

/*  Copyright 2014  DRUGSTOC_COMMISSIONS  (email : info@drugstoc.biz)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined('ABSPATH') or die("No script kiddies please!"); 

if(!class_exists('DrugstocCommission')):
/**
 * DrugstocCommission class.
 * Display and manage DrugStoc Commissions per order / item.
 *
 * @since 1.0.0
 */
class DrugstocCommission
{ 
    private static $instance;
    const VERSION = '1.0.0';

    private static function has_instance() {
        return isset(self::$instance) && self::$instance != null;
    }

    public static function get_instance() {
        if (!self::has_instance())
            self::$instance = new DrugstocCommission;
        return self::$instance;
    }

    public static function setup() {
        self::get_instance();
    }

    protected function __construct() {
        if (!self::has_instance()) {
            add_action('init', array(&$this, 'init'));
        }
    } 

    // Plug into all necessary actions and filters
    function init(){
        // Actions 
        add_action( 'admin_head', array( $this, 'ds_commission_scripts' ) ); 
        add_action( 'admin_menu', array( $this, 'register_ds_commission') );  
        add_action( 'admin_notices', array( $this, 'notices' ) ); 
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'ds_add_commission_to_order') );
       
        // Add Bulk Action to Woocommerce Order page
        add_action( 'admin_footer-edit.php', array( $this, 'bulk_footer_paid_comm'));
        add_action( 'admin_action_has_paid_commission', array( $this, 'bulk_request_paid_comm' ));
        add_action( 'admin_action_has_unpaid_commission', array( $this, 'bulk_request_unpaid_comm' ));
    
        // Default DS Commission per Order
        // update_option('drugstoc_commission_per_order', 0.05);
    }

    function ds_commission_scripts()
    {     
        wp_enqueue_style( 'dsc-datatable-css', "//cdn.datatables.net/1.10.4/css/jquery.dataTables.min.css");
        wp_enqueue_script('jquery');   
        wp_enqueue_script('dsc-datatable-js', "//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.3/js/jquery.dataTables.min.js",  array('jquery' )); 
        wp_enqueue_script('dsc-comm-js', plugins_url("/drugstoc-commission/js/ds-commission.js"), array('jquery'), '1.0.0', true); 
    }

    function register_ds_commission(){
         // DrugStoc Commissions
        add_menu_page(
            'DrugStoc Commissions', 
            'DrugStoc Commissions', 
            'manage_options', 
            'ds-commission', 
            array($this,'ds_commission_summary'), 
            'dashicons-tag', 
            8 
        ); 

        add_submenu_page(
            'ds-commission',
            'Commission per Order',
            'Commission per Order',
            'manage_options',
            'ds-commission-item',
            array($this,'ds_commission_details')
        ); 

        add_options_page(
            'Drugstoc Commission Settings',
            __('DS Commission', 'ds-commission'),
            'manage_options',
            __FILE__,
            array($this,'ds_commission_settings')
        );
    }

    // Add Values to Bulk Action Dropdown
    function bulk_footer_paid_comm() 
    { 
      global $post_type;
     
      if($post_type == 'shop_order') { // If Order 
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('<option>').val('has_paid_commission').text('Set Comm. as Paid').prop("title","Updates Commission status to Paid")
                    .appendTo("select[name='action'], select[name='action2']");
                $('<option>').val('has_unpaid_commission').text('Set Comm. as Un-paid').prop("title","Updates Commission status to Un-paid")
                    .appendTo("select[name='action'], select[name='action2']");
            });
        </script>
        <?php
      }
    }

    // Update Paid Status to Yes
    function bulk_request_paid_comm() 
    {
        # Array with the selected Order IDs
        $order =  $_REQUEST['post'];  

        if (count($order) > 0) {
            foreach ($order as $key => $value) { 
                update_post_meta($value, '_ds_commission_status','Yes'); 
            } 
        } 

        wp_redirect(home_url('/')."wp-admin/edit.php?post_type=shop_order");
    }

    // Update Paid Status to No
    function bulk_request_unpaid_comm() 
    {
        # Array with the selected Order IDs
        $order =  $_REQUEST['post'];  

        if (count($order) > 0) {
            foreach ($order as $key => $value) { 
                update_post_meta($value, '_ds_commission_status','No'); 
            } 
        } 

        wp_redirect(home_url('/')."wp-admin/edit.php?post_type=shop_order");
    }

    // Change Number to Money format (#1234 to #1,234.00)
    function show_price($value){
        return number_format((float)$value, 2);
    }

    // Check if an Item is routed to a distributor
    function isRouted($orderid, $itemid){
        global $wpdb;

        $item = $wpdb->get_row("SELECT distributor, notes, in_stock FROM {$wpdb->prefix}routed_order_items
            WHERE item_id = $itemid and order_id = $orderid ORDER BY created_at DESC LIMIT 1");

        // return strtoupper ( substr( get_user_meta( $item->distributor, 'primary_distributor', true ), 0, -6) );

        return isset($item->distributor)? array($item->distributor, $item->notes, $item->in_stock): array("None","None",0);//$item->distributor":"None";
    }

    // Get DrugStoc's Commission per order/order_item
    function ds_comm()
    {
        return (float) get_option('drugstoc_commission_per_order');
    }

    // Add drugstoc's Commission to order meta
    function ds_add_commission_to_order($order_id){
        global $woocommerce;
        
        $order = new WC_Order($order_id);
        $user = get_user_by( 'id', $order->customer_user );
        $user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true); 

        $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) ); 
        $total_commission = $commission = 0;
        foreach ($order_items as $item) {   
            if($user_primary_distributor == ""){ 
                $total_commission+= $item['line_total'] * $this->ds_comm();// 0.05;
            }
        }

        // Calculate and attach commission to order
        add_post_meta( $order_id, '_ds_commission', $total_commission, true);
        add_post_meta( $order_id, '_ds_commission_status', 'No', true);
    }

    // Commission Summary 
    function ds_commission_summary(){
        global $wpdb; 

        $args = array(
          'post_type'   => 'shop_order',
          'post_status' => 'publish',
          'meta_key'    => '_customer_user',
          'posts_per_page' => '-1',
          'orderby'=> 'ID',
              'order' => 'desc'
        );

        $my_query = new WP_Query($args); 
        $customer_orders = $my_query->posts; // Display all customer orders
        $total_commission = $_commission = 0;
        foreach ($customer_orders as $customer_order) {
            $order = new WC_Order(); 
            $order->populate($customer_order);  
            // User
            $user = get_user_by( 'id', $order->customer_user );
            $user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true); 
            if($user_primary_distributor == ""){
                $commission = $order->get_total() * $this->ds_comm();// 0.05; 
                $total_commission+= $commission;
            }
        }
        ?>
        <h3>DrugStoc Commissions</h3>
        <i><h4>Select an order to view commissions per line item </h4></i>
        <p>Overall Commission: <h3>&#8358;<?php echo $this->show_price($total_commission);?></h3></p>
        <div id="woocommerce-order-items" class="postbox"> 
            <h3 class="hndle"><span id="orderitems">&nbsp;&nbsp;Commission per Order</span></h3> 
            <div class="inside" style="padding: 15px;">
            <table class="table table-hover" id="ds-commission">
            <thead>
                <tr style="background-color: asliceblue;"> 
                    <th>#</th>
                    <th>Order</th>
                    <th>Purchased</th>
                    <th>Primary Distributor</th>
                    <th style="width:200px">Customer</th>
                    <th>Date</th>
                    <th>Total (&#8358;)</th>
                    <th>Commission (&#8358;)</th>
                    <th>Paid</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $total_commission = $commission = 0;
            foreach ($customer_orders as $key => $customer_order) {
                $order = new WC_Order(); 
                $order->populate($customer_order);  
                // User
                $user = get_user_by( 'id', $order->customer_user );
                $user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true); 
                if($user_primary_distributor == ""){
                    $commission = $order->get_total() * $this->ds_comm();// 0.05; 
                    $total_commission+= $commission;
                }?>
                <tr>
                    <th><?php echo ($key+1);?></th>
                    <td><a href="<?php echo menu_page_url('ds-commission-item',false).'&order='.$order->id;?>"><b><?php echo esc_html( $order->get_order_number() );?><b/> by <?php echo esc_html( $user->display_name );?></a></td>
                    <td><?php echo count($order->get_items());?></td>
                    <td><?php echo ($user_primary_distributor != "")? $user_primary_distributor:"None";?></td>
                    <td style="width:200px"><?php echo get_user_meta($user->ID,'institution',true);?></td>
                    <td><?php echo date('d M Y h:m:s A', strtotime($order->order_date));?></td>
                    <td><?php echo $this->show_price($order->get_total());?></td> 
                    <td style="color: green; font-weight:bold"><?php echo $this->show_price($commission); ?></td>
                    <td><?php echo get_post_meta($order->id, '_ds_commission_status', true);?></td>
                    <td>
                        <a title="View PDF Invoice" alt="View PDF Invoice" target="_blank" href='<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $order->id ), 'generate_wpo_wcpdf' );?>'>
                            <img src="<?php echo plugins_url('/drugstoc-commission/images/invoice.png');?>" alt="View PDF Invoice" width="16px" />
                        </a>
                    </td>
                </tr>
            <?php   
            } ?>
            </tbody>
            <tfoot> 
                <tr style="background-color: aliceblue;">
                    <th>#</th>
                    <th>Order</th>
                    <th>Purchased</th>
                    <th>Primary Distributor</th>
                    <th style="width:200px">Customer</th>
                    <th>Date</th>
                    <th>Total (&#8358;)</th>
                    <th>Commission (&#8358;)</th>
                    <th>Paid</th>
                    <th></th>
                </tr> 
            </tfoot>
        </table>
        </div>
        </div>
        <p>Overall Commission: <h3>&#8358;<?php echo $this->show_price($total_commission);?></h3></p>
        <?php  
    }

    // Commission per order
    function ds_commission_details($id) 
    {
        if(isset($_GET['order']) && $_GET['order'] > 0){
            $id = $_GET['order']; 
            $orderid = $_GET['order'];  

            global $wpdb, $woocommerce;

            $order = new WC_Order($orderid);
            $distributors = new WP_Query('post_type=redistributor'); 
     
            $user = get_user_by( 'id', $order->customer_user );
            $user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true); ?>
                <div id="order_data" class="panel">
                    <h2>DrugStoc Commission for Order #<?php echo $order->id;?></h2> 
                </div>
                <?php   
                    $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) ); 
                    $total_commission = $commission = 0;
                    foreach ($order_items as $item) {   
                        if($user_primary_distributor == ""){ 
                            $total_commission+= $item['line_total'] * $this->ds_comm();//0.05;
                        }
                    }
                ?> 
                <p>Order Date: <b><?php echo date('d M Y h:m:s A', strtotime($order->order_date)); ?></b></p>
                <p>Customer: <b><?php echo get_user_meta($user->ID,'institution',true);?></b></p> 
                <p>Total Commission:</p><h3>&#8358;<?php echo $this->show_price($total_commission);?></h3>
                <p>Status: <b><?php echo (get_post_meta($order->id, '_ds_commission_status', true) == "Yes")? "Paid":"Un-paid";?></b></p>
                <div id="woocommerce-order-items" class="postbox " > 
                    <h3 class="hndle"><span id="orderitems">&nbsp;&nbsp;Commission per Line Item</span></h3> 
                    <div class="inside" style="padding: 15px;">
                    <table class="table table-hover" id="ds-commission">
                        <thead>
                            <tr style="background-color: aliceblue;"> 
                                <th>#</th> 
                                <th>Item</th>
                                <th>Routed to</th> 
                                <th>Price (&#8358;)</th> 
                                <th>Quantity</th>
                                <th>Total (&#8358;)</th> 
                                <th title="Amount payable to Distributor per line item">Dist. Amount (&#8358;) </th>
                                <th>Commission (&#8358;)</th>   
                            </tr>
                        </thead>
                        <tbody>
                    <?php   
                    $i=1;
                    foreach ($order_items as $key => $item) {  
                        $route = strtoupper ( substr( get_user_meta( $item->distributor, 'primary_distributor', true ), 0, -6) );
                        if($user_primary_distributor == ""){
                            $commission = $item['line_total'] * $this->ds_comm();//0.05; 
                        }?>
                        <tr class="item" data-item-id="<?php echo $item['product_id']; ?>"> 
                            <td><?php echo $i;?></td>
                            <td class="name"><a href="<?php echo admin_url('post.php?post='.$item['product_id']);?>"><?php echo $item['name'];?></a></td>
                            <td class="dist"><?php echo $route;?></td>
                            <td class="price"><?php echo $this->show_price(get_post_meta($item['product_id'],'_price',true));?></td>
                            <td class="quantity"><?php echo $item['qty'];?></td>
                            <td class="line_cost price"><?php echo $this->show_price($item['line_total']);?></td>
                            <td class="distr_pay" style="color: blue"><?php echo $this->show_price($item['line_total'] - $commission); ?></td>
                            <td class="commission" style="color: green; font-weight: bold"><?php echo $this->show_price($commission); ?></td>  
                        </tr>
                    <?php $i+=1; 
                    }?> 
                    </tbody>
                    </table> 
                    Total Commission:<h3>&#8358;<?php echo $this->show_price($total_commission);?></h3>
                </div>  
            </div> 
        <?php 
        }else{
            echo '<h3>Select an Order to view your commission!</h3>';
        }?>  
    <?php
    }

    // Display Admin Notice
    function notices()
    {
        if (isset($_POST['recalculate']) && $_POST['recalculate'] != "" && is_string($_POST['recalculate'])) {
            echo '<div class="updated"><p>';
            echo __('Prices updated successfully');
            echo "</p></div>";
        }

        if (isset($_POST['dscommission']) && $_POST['dscommission'] != "" && $_POST['dscommission'] > 0){
            echo '<div class="updated"><p>';
            echo __('Commission updated successfully');
            echo "</p></div>";
        }
    }

    // Update Drugstoc Price
    function update_ds_price($id)
    { 
        global $wpdb, $woocommerce; 
        
        $distributors = $wpdb->get_results("SELECT meta_id, meta_key FROM `wp_postmeta` WHERE `meta_key` LIKE '%_price' and meta_key NOT IN ('_price','_regular_price','_sale_price') group by meta_key");

        $products = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE ID = $id AND post_type = 'product' AND post_status LIKE 'publish'");
       
        foreach ($products as $key => $value) { 
            $id = $value->ID;
            $product = new WC_Product($id); 
             
            $price = 0; // Temporary price variable

            foreach ($distributors as $key => $distributor) { 
                // Get Distributor Price and Store in temp var 
                $temp_price = (float) get_post_meta($id, $distributor->meta_key, true);  

                if($price == 0) $price = ($temp_price != 0)? $temp_price: 0; // Assign a value to price 

                if($temp_price != 0 && $temp_price < $price) $price = $temp_price;   
            }     
            $price = $price * 1.05;

            update_post_meta( $id, '_regular_price', $price);
            update_post_meta( $id, '_sale_price', 0);
            update_post_meta( $id, '_price', $price); 
        }   
    }

    // Settings Page
    function ds_commission_settings()
    {
        global $wpdb, $woocommerce;
        //
        // Update Drugstoc Prices
        // 
        if (isset($_POST['recalculate']) && $_POST['recalculate'] != "" && is_string($_POST['recalculate'])) {
            $val = sanitize_text_field($_POST['recalculate']);
            $products = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status LIKE '{$val}'");
 
            // echo "$val, Count: ".count($products);
            foreach ($products as $key => $value) { 
                $this->update_ds_price($value->ID); 
            }  
        }   

        if (isset($_POST['dscommission']) && $_POST['dscommission'] != "" && $_POST['dscommission'] > 0) {
            update_option( 'drugstoc_commission_per_order', sanitize_text_field($_POST['dscommission'])); 
            // wp_admin_notice
        }?>
        <form method="post">
            <h3>DrugStoc Commissions</h3> 
            <p>Set Commission per Order at : <input type="textfield" name="dscommission" id="dscommission" value="<?php echo get_option('drugstoc_commission_per_order');?>" />%<p>
            <input type="submit" value="Update Commission" name="dsc_settings" id="dsc_settings" class="button-primary" />
        </form>
        <br/>
        <form method="post">
            <h3>Recalculate Prices for </h3> 
            <input type="radio" name="recalculate" id="recalculate" value="publish"/> Published Drugs<br/>
            <input type="radio" name="recalculate" id="recalculate" value="draft"/> Draft Drugs<br/><br/>
            <input type="submit" value="Recalculate" name="dsc_settings" id="dsc_settings" class="button-primary" />
        </form>

    <?php 
    }

    function __destruct()
    {
        // update_option( 'drugstoc_commission_per_order',0);
    }
}
endif;

// Activate Plugin 
DrugstocCommission::setup();







