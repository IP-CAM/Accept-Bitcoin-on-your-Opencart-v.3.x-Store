<?php

class ModelExtensionPaymentGreencryptopay extends Model {

    const TO_CURRENCIES = [
        'btc'
    ];

    const FROM_CURRENCIES = [
        'usd'
    ];

  public function addOrder($data) {
      $sql = 'INSERT INTO `' . DB_PREFIX . 'greencryptopay_orders` (`order_id`, `callback_secret`, `payment_currency`, `payment_amount`, `payment_address`) VALUES (' . (int) $data['order_id'] .', "' . $this->db->escape($data['callback_secret']) . '", "' . $this->db->escape($data['payment_currency']) . '", "' . $this->db->escape($data['payment_amount']) . '", "' . $this->db->escape($data['payment_address']) . '");';
      $this->db->query($sql);
  }

  public function getOrder($order_id) {
      $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "greencryptopay_orders` WHERE `order_id` = '" . (int) $order_id . "' LIMIT 1");
      return $query->row;
  }

  public function getMethod($address, $total) {

        $method_data = array();

        if (!$this->config->get('payment_greencryptopay_status')) {
            return $method_data;
        }

        $this->load->language('extension/payment/greencryptopay');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_greencryptopay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        $status = false;

        if (!in_array(strtolower($this->session->data['currency']), self::FROM_CURRENCIES)) {
            return $method_data;
        }

        if (empty($this->config->get('payment_greencryptopay_total')) || $this->config->get('payment_greencryptopay_total') <= $total) {
          $status = true;
          // todo разобраться с зонами
        } elseif (!$this->config->get('payment_greencryptopay_geo_zone_id')) {
          $status = true;
        } elseif ($query->num_rows) {
          $status = true;
        } else {
          $status = false;
        }

        if ($status) {
          $method_data = array(
            'code'		 => 'greencryptopay',
            'title'		 => $this->language->get('text_title'),
            'terms'		 => '',
            'sort_order' => $this->config->get('payment_greencryptopay_sort_order')
          );
        }

        return $method_data;
  }

}
