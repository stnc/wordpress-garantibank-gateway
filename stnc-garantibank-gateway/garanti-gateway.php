<?php
if (!defined('ABSPATH')) {
    exit;
}

/*xx
 * Plugin Name: WooCommerce STNC Garanti Payment Gateway
 * Plugin URI: selmantunc.com.tr
 * Description: Take credit card payments on your store.
 * Author: stnc
 * Author URI: http://selmantunc.com.tr
 * Version: 1.0.0
 *
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */


function stnc_load_textdomain()
{
    load_plugin_textdomain('stnc', false, dirname(plugin_basename(__FILE__)) . '/i18n/languages/');
}

add_filter('woocommerce_payment_gateways', 'stnc_add_gateway_class');
function stnc_add_gateway_class($gateways)
{
    $gateways[] = 'WC_stnc_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'stnc_init_gateway_class');
function stnc_init_gateway_class()
{
    class WC_stnc_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {


            $this->id = 'stnc_garanti_gateway_kredi'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Garanti  3D Gateway';
            $this->method_description = 'Garanti 3d Ödeme'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->talimatlar = $this->settings['talimatlar'];
            $this->working_mode = $this->settings['working_mode'];


            $this->terminalID = $this->settings['terminalID'];
            $this->terminalUserID = $this->settings['terminalUserID'];

            $this->terminalMerchantID = $this->settings['terminalMerchantID'];
            $this->storeKey = $this->settings['storeKey'];
            $this->ProvUser = $this->settings['ProvUser'];
            $this->provUserPassword = $this->settings['provUserPassword'];


            $this->msg['message'] = "";
            $this->msg['class'] = "";

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array(&$this, 'payment_scripts'));

            // You can also register a webhook here
            add_action('woocommerce_api_{webhook name}', array(&$this, 'webhook'));

        }

        /**
         *form options
         */
        function init_form_fields()
        {

            $this->form_fields = array(

                'enabled' => array(
                    'title' => __('Enable/Disable', 'stnc'),
                    'type' => 'checkbox',
                    'label' => __('Eklentiyi Aktif et/etme', 'stnc'),
                    'default' => 'no',
                ),

                'working_mode' => array(
                    'title' => __('Çalışma Ortamı'),
                    'type' => 'select',
                    'options' => array(
                        'TEST' => 'Test Ortamı',
                        'PROD' => 'Çalışma Ortamı',
                    ),
                    'description' => "Sanalp post u test için kullanıyorsanız test oramını seçiniz ",
                ),


                'title' => array(
                    'title' => __('Başlık:', 'stnc'),
                    'type' => 'text',
                    'description' => __('Önyüzde görünecek başlık bilgisini giriniz', 'stnc'),
                    'default' => __('Kredi Kartı (3D Secure)', 'stnc'),
                ),


                'description' => array(
                    'title' => __('Açıklama:', 'stnc'),
                    'type' => 'textarea',
                    'description' => __('Kredi kartı ile güvenli ödeme', 'stnc'),
                    'default' => __('Kredi kartı ile güvenli ödeme', 'stnc'),
                ),

                'talimatlar' => array(
                    'title' => __('Talimatlar/Notlar:', 'stnc'),
                    'type' => 'textarea',
                    'description' => __('', 'stnc'),
                    'default' => __('Aşağıdaki bilgiler test ortamı için geçerlidir, lütfen kendi bilgilerinizi girmeyi unutmayın', 'stnc')),

                'terminalID' => array(
                    'title' => __('Terminal  ID', 'stnc'),
                    'type' => 'text',
                    'description' => __('Terminal  ID'),
                    'default' => '30691298',
                ),

                'terminalUserID' => array(
                    'title' => __('Terminal user  ID', 'stnc'),
                    'type' => 'text',
                    'description' => __('Terminal user ID'),
                    'default' => 'PROVAUT',
                ),

                'terminalMerchantID' => array(
                    'title' => __('Terminal Merchant ID', 'stnc'),
                    'type' => 'text',
                    'description' => __('Üye iş yeri numarası'),
                    'default' => '7000679',
                    
                ),

                'ProvUser' => array(
                    'title' => __(' Terminal Provison User ', 'stnc'),
                    'type' => 'text',
                    'description' => __('Terminal Provison User '),
                    'default' => 'PROVAUT',
                ),

                'provUserPassword' => array(
                    'title' => __(' Provision user Password', 'stnc'),
                    'type' => 'text',
                    'description' => __(' Provision User Password '),
                    'default' => '123qweASD/',
                ),


                'storeKey' => array(
                    'title' => __('Store Key', 'stnc'),
                    'type' => 'text',
                    'description' => __('3D Secure şifreniz'),
                    'default' => '12345678',
                ),
            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

            // ok, let's display some description before the payment form
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->working_mode == 'TEST') {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in 
 <a href="#" target="_blank">documentation</a><br>';
                    $this->description = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);
//https://designmodo.com/demo/creditcardform/
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '
      <div class="credit-card">
      <div class="form-header">
        <h4 class="title">Kredi Kartı Bilgileri</h4>
      </div>

      <div class="form-body">
     
           <input type="text" class="card-number" name="card[holder]" autocomplete="off" placeholder="Ad Soyad">
        <!-- Card Number -->
        <input type="text" autocomplete="off" class="card-number" name="card[pan]" placeholder="Kart Numarası">

        <!-- Date Field -->
        <div class="date-field">
          <div class="month">
            <select name="card[month]" autocomplete="off">
              <option value="1">01</option>
              <option value="2">02</option>
              <option value="3">03</option>
              <option value="4">04</option>
              <option value="5">05</option>
              <option value="6">06</option>
              <option value="7">07</option>
              <option value="8">08</option>
              <option value="9">09</option>
              <option value="10">10</option>
              <option value="11">11</option>
              <option value="12">12</option>
            </select>
          </div>
          <div class="year">
            <select name="card[year]" autocomplete="off">
              <option value="18">2018</option>
              <option value="19">2019</option>
              <option value="20">2020</option>
              <option value="21">2021</option>
              <option value="22">2022</option>
              <option value="23">2023</option>
              <option value="24">2024</option>
              <option value="25">2025</option>
			   <option value="26">2026</option>
			   <option value="27">2027</option>
			    <option value="28">2028</option>
				 <option value="29">2029</option>
            </select>
          </div>
        </div>

        <!-- Card Verification Field -->
        <div class="card-verification">
          <div class="cvv-input">
            <input type="text" autocomplete="off" name="card[cvc]" placeholder="CVV">
          </div>
       
        </div>

        <!-- Buttons -->
      </div>
    </div>';


            do_action('woocommerce_credit_card_form_end', $this->id);


        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }

            // TODO: sonraında açılacak
            /*
                        // no reason to enqueue JavaScript if API keys are not set
                        if (empty($this->private_key) || empty($this->publishable_key)) {
                            return;
                        }

                        // do not work with card detailes without SSL unless your website is in a test mode
                        if (!$this->testmode && !is_ssl()) {
                            return;
                        }
            */


            wp_register_style('card_css', plugins_url('/assets/card.css', __FILE__));
            wp_enqueue_style('card_css');


            /*
            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('stnc_lo', 'stnc_params', array(
                'publishableKey' => $this->publishable_key
            ));

            wp_enqueue_script( 'stnc_lo' );
            */


        }

        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = $_POST;
                array_walk_recursive($data, function (&$item) {
                    $item = sanitize_text_field($item);
                });

                if ($this->working_mode != 'TEST') {
                    $errors = $this->stnc_validatePaymentForm($data);
                    if ($errors !== true) {
                        foreach ($errors as $err) {
                            wc_add_notice($err, 'error');
                        }
                    } else {
                        return true;
                    }
                }


            }
        }

        protected function stnc_validatePaymentForm($form)
        {
            $errors = [];
            if (!isset($form['card']['holder']) || empty($form['card']['holder'])) {
                //    $errors[] = __('Holder name cannot be empty.', 'stnc');
                $errors[] = __('Lütfen kredi kartı sahibinin ad ve soyadını giriniz.', 'stnc');
            }

            if (!isset($form['card']['pan']) || empty($form['card']['pan'])) {
                // $errors[] = __('Card number cannot be empty.', 'stnc');
                $errors[] = __('Lütfen kart numaranızı giriniz', 'stnc');
            } elseif (!$this->stnc_checkCCNumber($form['card']['pan'])) {
                //      $errors[] = __('Please enter a valid credit card number.', 'stnc');
                $errors[] = __('Lütfen geçerli bir kredi kartı numarası giriniz', 'stnc');
            }

            if (!isset($form['card']['year']) || empty($form['card']['year'])) {
                //    $errors[] = __('Card expiration year cannot be empty.', 'stnc');
                $errors[] = __('Kartınızın son kullanım yılı alanı boş olmamalıdır', 'stnc');
            } else {
                $y = intval($form['card']['year']);
                $y += ($y > 0 && $y < 99) ? 2000 : 0;
                if ($y < date('Y')) {
                    $errors[] = __('Kartınızın son kullanım yılı geçersizdir', 'stnc');
                    //$errors[] = __('The expiration year is invalid', 'stnc');
                }
            }

            if (!isset($form['card']['month']) || empty($form['card']['month'])) {
                //   $errors[] = __('Card expiration month cannot be empty.', 'stnc');
                $errors[] = __('Kartınızın son kullanım ayı boş olmamalıdır', 'stnc');
            } /*else {
                $m = intval($form['card']['month']);
                if ($m < 1 || $m > 12) {
                    $errors[] = __('Kartınızın son kullanma ayı geçersizdir ' . var_export($form['card']['month'], 1), 'stnc');
                }
            }*/
            /*
                        if (!$this->stnc_checkCCEXPDate($form['card']['month'], $form['card']['year'])) {
                            // $errors[] = __('The expiration month is invalid: ' . var_export($form['card']['month'], 1), 'stnc');
                            $errors[] = __('Kartınızın son kullanma ayı geçersizdir.' . var_export($form['card']['month'], 1), 'stnc');
                        }*/

            if (!isset($form['card']['cvc']) || empty($form['card']['cvc'])) {
                // $errors[] = __('Card CVC cannot be empty.', 'stnc');
                $errors[] = __('Kartınızın CVC numarası boş olmamalıdır.', 'stnc');
            } elseif (isset($form['card']['pan']) AND !$this->stnc_checkCCCVC($form['card']['pan'], $form['card']['cvc'])) {
                // $errors[] = __('Please enter a valid credit card verification number.', 'stnc');
                $errors[] = __('Lütfen Geçerli Bir Güvenlik Kodu Giriniz.', 'stnc');
            }


            return count($errors) ? $errors : true;
        }

        protected function stnc_checkCCEXPDate($month, $year)
        {
            if (strtotime('01-' . $month . '-' . $year) <= time()) {
                return false;
            }
            return true;
        }

        protected function stnc_checkCCNumber($cardNumber)
        {
            $cardNumber = preg_replace('/\D/', '', $cardNumber);
            $len = strlen($cardNumber);
            if ($len < 15 || $len > 16) {
                return false;
            } else {
                switch ($cardNumber) {
                    case(preg_match('/^4/', $cardNumber) >= 1):
                        return true;
                        break;
                    case(preg_match('/^5[1-5]/', $cardNumber) >= 1):
                        return true;
                        break;
                    default:
                        return false;
                        break;
                }
            }
        }

        protected function stnc_checkCCCVC($cardNumber, $cvc)
        {
            $firstNumber = (int)substr($cardNumber, 0, 1);
            if ($firstNumber === 3) {
                if (!preg_match("/^\d{4}$/", $cvc)) {
                    return false;
                }
            } else if (!preg_match("/^\d{3}$/", $cvc)) {
                return false;
            }

            return true;
        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            require_once 'class/vendor/autoload.php';
            global $woocommerce, $wpdb;

            // we need it to get any order detailes
            $order = wc_get_order($order_id);



            $total = str_replace(',', ".",$woocommerce->cart->total);
            /*
                        $pos = new \SanalPos\Garanti\SanalPosGaranti('7000679', '30691297', 'PROVAUT', '123qweASD/', 'PROVAUT');
            */


            $pos = new \SanalPos\Garanti\SanalPosGaranti($this->settings['terminalMerchantID'], $this->settings['terminalID'], $this->settings['terminalUserID'], $this->settings['provUserPassword'], $this->settings['ProvUser']);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formdata = $_POST;
            }

            //   $pos->setCard('375622005485014', '10', '20', '123');
            $pos->setCard($formdata['card']['pan'], $formdata['card']['month'], $formdata['card']['year'], $formdata['card']['cvc']);

            $pos->setOrder($order_id, 'eticaret@eticaret.com', $total);

            $pos->setMode($this->settings['working_mode']); //test or production mode


            $results = $pos->pay();
//echo '<pre>';
            //      print_r($results);


            $mypix = simplexml_load_string($results);
//            print_r($mypix);


            $strReasonCodeValue = $mypix->Transaction[0]->Response->ReasonCode;
            $TransactionCode = $mypix->Transaction[0]->RetrefNum;


            if ($strReasonCodeValue == "00") {
                $status = 1;
                $errormsg = "";
            } else {
                $status = 0;
                $errormsg = $mypix->Transaction[0]->Response->Message;
            }

            $table_name = $wpdb->prefix . 'stnc_garanti_gateway';
            $wpdb->insert(
                $table_name,
                array(
                    'transaction_id' => $TransactionCode,
                    'order_id' => $order_id,
                    'user_id' => 01,//get_current_user_id(),
                    'response_code' => $strReasonCodeValue,
                    'response_code_desc' => $mypix->Transaction[0]->Response->Message,
                    'message' => $mypix->Transaction[0]->Response->ErrorMsg,
                    'errormsg' => $errormsg,
                    'amount' => $woocommerce->cart->total,
                    'or_date' => date("Y-m-d"),
                    'status' => $status,
                )
            );

            if ($strReasonCodeValue == "00") {


                // we received the payment
                $order->payment_complete();
                //  wc_reduce_stock_levels();

                // some notes to customer (replace true with false to make it private)
                $order->add_order_note('Sipariş başarı ile kaydedilmiştir.[Garanti Pos]Transaction Kodu ' . $TransactionCode);

                $woocommerce->cart->empty_cart();

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );

            } else {
                //http://www.mustafabugra.com/web-egitimleri/php-egitimi/sanal-pos-hata-mesajlari/
                //  wc_add_notice('Please try again.', 'error');
                $order->add_order_note( 'Ödemede Sorun oluştu '.$mypix->Transaction[0]->Response->ErrorMsg.'  Hata Nedeni=='. ($this->hataKodlar($strReasonCodeValue)));
                // wc_add_notice('Bir sorun oluştu: ' . $mypix->Transaction[0]->Response->ErrorMsg, 'error');
                wc_add_notice('Bir sorun oluştu: ' .'Hata Nedeni = '. $this->hataKodlar($strReasonCodeValue), 'error');
                return false;
            }


        }

        private function hataKodlar($ProcReturnCode)
        {

//dönen $ProcReturnCode koduna göre

            $gecersiz_islem = array(3, 4, 5, 6, 7, 13, 15, 17, 19, 21, 25, 28, 29, 30, 31, 32, 37, 38, 39, 51, 52, 53, 63, 68, 75, 76, 77, 78, 80, 81, 82, 83, 85, 86, 88, 89, 91, 92, 94, 95, 96, 98);
            /*
            if($ProcReturnCode=="99" and $ErrMsg=="The card failed compliancy checks")
                $msg = "Kredi kart numarası geçerli değil";
            elseif($ProcReturnCode=="99" and $ErrMsg=="The card has expired")
                $msg = "Kartın son kullanım tarihi geçmiş.";
            elseif($ProcReturnCode=="99" and $ErrMsg=="Insufficient permissions to perform requested operation")
                $msg = "Kullanıcı hatası (belirli bir işlem yaparken o işleme yetkisi olmayan bir kullanıcı kullanılmış. Mağaza kodunu, kullanıcı adını ve şifresini gözden geçiriniz.)";
            elseif($ProcReturnCode=="99" and $ErrMsg=="Value for element 'Total' is not valid.")
                $msg = "Currency kodu hatalı.";
            elseif($ProcReturnCode=="99" and ($ErrMsg=="Unable to determine card type. ('length' is '16')" or $ErrMsg=="The card failed compliancy checks"))
                $msg = "Kart Tanımlanamadı. Geçersiz kredi kartı.";
            elseif($ProcReturnCode=="93" and $ErrMsg=="Transaction cannot be completed (violation of law)")
                $msg = "İşlem tamamlanamadı. İşleyiş kurallarından biri çiğnendi.";
            elseif($ProcReturnCode=="54" and $ErrMsg=="Expired Card")
                $msg = "Kart son kullanım tarihi hatalı.";
            elseif($ProcReturnCode=="51" and $ErrMsg=="Not sufficient funds")
                $msg = "Kredi kartınızın bakiyesi yetersiz. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="82" and $ErrMsg=="Incorrect CVV")
                $msg = "Geçersiz CVV kodu girildi.";
            elseif($ProcReturnCode=="10" and $ErrMsg=="Reserved")
                $msg = "Yetkisiz işlem.";
            elseif($ProcReturnCode=="1" or $ProcReturnCode=="2")
                $msg = "Kredi kartınız için bankanız provizyon talep etmektedir. İşlem sonuçlanmamıştır.";*/
            if($ProcReturnCode=="8")
                $msg = "Kart üzerindeki bilgileri kontrol ederek tekrar deneyiniz.";
            elseif($ProcReturnCode=="9")
                $msg = "Kredi kartınız yenilenmiş. Lütfen yeni kredi kartı bilgileriniz ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="12")
                $msg = "Kartınızın arka yüzünde bulunan CVV kodu yanlış.";
            elseif($ProcReturnCode=="14")
                $msg = "Girmiş olduğunuz kart numarası hatalı.";
            elseif($ProcReturnCode=="16")
                $msg = "Kredi kartınızın bakiyesi yetersiz. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="18")
                $msg = "Kartınız kullanıma kapanmış durumdadır.";
            elseif($ProcReturnCode=="33")
                $msg = "Kartınızın süresi dolmuş durumdadır.";
            elseif($ProcReturnCode=="34" or $ProcReturnCode=="43")
                $msg = "Kartınız çalıntı olarak saptanmış durumda olduğu için işleminizi gerçekleştiremedik.";
            elseif($ProcReturnCode=="36")
                $msg = "Kartınız sınırlandırılmış olduğu için işleminizi gerçekleştiremedik.";
            elseif($ProcReturnCode=="41")
                $msg = "Kartınız kayıp olarak saptanmış durumda olduğu için işleminizi gerçekleştiremedik.";
            elseif($ProcReturnCode=="51")
                $msg = "Kredi kartınızın bakiyesi yetersiz. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="54")
                $msg = "Kredi kartınızın son kullanma tarihi hatalı yada eksik. Bilgileri kontrol edip tekrar deneyiniz.";
            elseif($ProcReturnCode=="56")
                $msg = "Girmiş olduğunuz bilgilerle eşleşen kredi kartı bulunmamaktadır. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="57")
                $msg = "Bu işlem için kredi kartınıza izin verilmedi. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="58")
                $msg = "Yetkisiz bir işlem yapıldı. Örn: Kredi kartınızın ait olduğu banka dışında bir bankadan taksitlendirme yapıyor olabilirsiniz. Başka bir kredi kartı ile işlem yapmayı deneyiniz.";
            elseif($ProcReturnCode=="61")
                $msg = "Kartınızın para çekme limiti üst sınırdadır. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="62")
                $msg = "Kartınız kısıtlandırılmıştır. Kartınız sadece kendi ülkenizde geçerlidir. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="65")
                $msg = "Kredi kartınızın günlük işlem limiti dolmuştur. Başka bir kredi kartı ile deneyiniz.";
            elseif($ProcReturnCode=="90")
                $msg = "Gün sonu işlemi yapılıyor. Kısa bir süre sonra tekrar deneyiniz.";
            elseif($ProcReturnCode=="91")
                $msg = "Bankanıza ulaşılamıyor. Başka bir kredi kartı ile tekrar deneyiniz.";
            elseif($ProcReturnCode=="93")
                $msg = "Hukuki nedenlerden dolayı işleminiz reddedildi.Kartınız 3d işlemlere kapalı olabilir.";
            elseif(in_array($ProcReturnCode, $gecersiz_islem))
                $msg = "İşleminiz onaylanmadı. Lütfen kısa bir süre sonra tekrar deneyiniz.(" . $ProcReturnCode . ")";
            else
                $msg = 'Bir hata oluştu (Hata Kodu:'.$ProcReturnCode.') Tekrar deneyiniz. Sorun devam ederse lütfen bizimle temasa geçiniz.';

            return $msg;


        }

        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook()
        {

            $order = wc_get_order($_GET['id']);
            $order->payment_complete();
            $order->reduce_order_stock();

            update_option('webhook_debug', $_GET);

        }
    }
}


global $jal_db_version;
$jal_db_version = '1.0';

function stnc_install_hnb()
{
    global $wpdb;
    global $jal_db_version;

    $table_name = $wpdb->prefix . 'stnc_garanti_gateway';
    $charset_collate = '';

    if (!empty($wpdb->charset)) {
        $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
    }

    if (!empty($wpdb->collate)) {
        $charset_collate .= " COLLATE {$wpdb->collate}";
    }

    $sql = "CREATE TABLE $table_name (
					id int(9) NOT NULL AUTO_INCREMENT,
					transaction_id bigint  NULL,					
					order_id int(9)  NULL,					
					user_id int(9)  NULL,					
					response_code VARCHAR(20)  NULL,
					response_code_desc VARCHAR(250)   NULL,										
							messsage text  NULL,			
							errormsg text  NULL,			
                  amount DECIMAL(6,2),
                    or_date DATE  NULL,                    
                    status int(6)  NULL,					
					UNIQUE KEY id (id)
				) $charset_collate;";


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('jal_db_version', $jal_db_version);
}

function stnc_install_data_hnb()
{
    global $wpdb;

    $welcome_name = 'Garanti IPG';
    $welcome_text = 'Congratulations, you just completed the installation!';

    $table_name = $wpdb->prefix . 'hnb_ipg';

    $wpdb->insert(
        $table_name,
        array(
            'time' => current_time('mysql'),
            'name' => $welcome_name,
            'text' => $welcome_text,
        )
    );
}

register_activation_hook(__FILE__, 'stnc_install_hnb');
register_activation_hook(__FILE__, 'stnc_install_data_hnb');


add_action('plugins_loaded', 'stnc_load_textdomain');
