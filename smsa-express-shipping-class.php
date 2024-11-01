<?php 


function register_smsa_shipping_menu_page() {
    add_menu_page('SMSA Shipping Order Page', 'SMSA Shipping', 'add_users', 'smsa-shipping-official/order-list.php', '', plugins_url( 'smsa-shipping-official/images/icon.svg' ), 6); 
}
add_action('admin_menu', 'register_smsa_shipping_menu_page');



    function smsa_shipping_method()
    {

        if (!class_exists('SMSA_Shipping_Method'))
        {

            class SMSA_Shipping_Method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */

                public function __construct()
                {
                   
                        wp_register_style( 'smsa_style', 'css/smsa.css' );
                        wp_enqueue_style( 'smsa_style' );

                    global $woocommerce;
                    $this->id = 'smsa-express-integration';
                    $this->method_title = __('SMSA Shipping Integration');
                    $this->method_description = __('<h3>SMSA PLUGIN INSTALLATION GUIDE</h3><br>
SMSA Express Shipping (Official) Plugin requires an API Key.
Please go to ecom.smsaexpress.com, login, and copy the "Production" API Key exists under Documentation.
If you don’t have an account number, please send us an email at info@smsaexpress.com to have your account number created.
<br><h3>تعليمات التحميل لتطبيق سمسا إكسبريس الرسمي</h3>
.يتطلب هذا التطبيق إدخال مفتاح الربط الخاص بحسابكم, يرجى تسجيل الدخول على منصة سمسا والحصول على المفتاح من هناك
 
 ecom.smsaexpress.com رابط المنصة

إذا لا يوجد لديكم رقم حساب, يرجى مراسلتنا عبر الإيميل أدناه ليتم إنشاء رقم حسابكم لدى سمسا إكسبريس
info@smsaexpress.com');

                    
                    $this->init();

                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';



                }



                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array(
                        $this,
                        'process_admin_options'
                    ));
                }

                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields()
                {

                    $this->form_fields = array(

                       
                        'smsa_account_no' => array(
                            'title' => __('SMSA API Key') ,
                            'type' => 'password',
                            'description' => __('Navigate to ecom.smsaexpress.com and copy the "Production" API Key exists under Documentation.') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                       
                        'store_phone' => array(
                            'title' => __('Store Phone Number') ,
                            'type' => 'number',
                            'description' => __('Your contact phone number will be printed on shipping labels.') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                    );

                }

               
    //*********************Check SMSA details is valid or not***************//
                
            }
        }
        }
    
   //*********************Add SMSA EXpress shipping option***************//
    add_action('woocommerce_shipping_init', 'smsa_shipping_method');
    
    

    function smsa_add_smsa_shipping_method($methods)
    {
         
         $methods['smsa-express-integration'] = 'SMSA_Shipping_Method';
        
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'smsa_add_smsa_shipping_method');


   

       //*********************Add shipping phone number field***************//
    add_filter('woocommerce_checkout_fields', 'smsa_bbloomer_shipping_phone_checkout');

    function smsa_bbloomer_shipping_phone_checkout($fields)
    {
        $fields['shipping']['shipping_phone'] = array(
            'label' => 'Phone',
            'required' => true,
            'class' => array(
                'form-row-wide'
            ) ,
            'priority' => 25,
        );
        return $fields;
    }

