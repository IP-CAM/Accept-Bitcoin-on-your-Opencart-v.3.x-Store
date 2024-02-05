<?php

class ModelExtensionPaymentGreencryptopay extends Model {

    public function install()
    {
        $this->db->query("
          CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "greencryptopay_orders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `callback_secret` VARCHAR(255) NOT NULL,
            `payment_currency` VARCHAR(255) NOT NULL,
            `payment_amount` VARCHAR(255) NOT NULL,
            `payment_address` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT COLLATE=utf8_general_ci;
        ");

        $this->load->model('setting/setting');

        $defaults = array();

        $defaults['payment_greencryptopay_status'] = false;
        $defaults['payment_greencryptopay_testnet'] = false;
        $defaults['payment_greencryptopay_merchant_id'] = '';
        $defaults['payment_greencryptopay_secret_key'] = '';
        $defaults['payment_greencryptopay_number_of_confirmations'] = 3;
        $defaults['payment_greencryptopay_request_signature'] = md5(time() . random_bytes(10));
        $defaults['payment_greencryptopay_sort_order'] = 0;
        $defaults['payment_greencryptopay_entry_total'] = false;
        $defaults['payment_greencryptopay_pending_status'] = '';
        $defaults['payment_greencryptopay_paid_status'] = '';
        $defaults['payment_greencryptopay_wallet_link'] = '';
        $defaults['payment_greencryptopay_time_to_pay'] = 10;

        $this->model_setting_setting->editSetting('payment_greencryptopay', $defaults);
  }

  public function uninstall() {
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "greencryptopay_orders`;");
  }

}
