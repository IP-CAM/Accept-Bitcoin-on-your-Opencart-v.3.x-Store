<?php

use GcpSdk\Api;

require_once(DIR_SYSTEM . 'library/greencryptopay/vendor/autoload.php');
require_once(DIR_APPLICATION . 'model/extension/payment/greencryptopay.php');

class ControllerExtensionPaymentGreencryptopay extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/greencryptopay');
        $this->load->model('checkout/order');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/greencryptopay/checkout', '', true);

        return $this->load->view('extension/payment/greencryptopay_checkout_button', $data);
    }

    public function checkout()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/greencryptopay');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $to_currency = ModelExtensionPaymentGreencryptopay::TO_CURRENCIES;
        $from_currency = ModelExtensionPaymentGreencryptopay::FROM_CURRENCIES;
        $total = $order_info['total'] * $this->currency->getvalue($order_info['currency_code']);

        $client = $this->make_client();

        $response = $client->paymentAddress(
            $to_currency[0],
            $this->url->link('extension/payment/greencryptopay/callback', null, true),
            (string) $order_info['order_id'],
            $from_currency[0],
            (float) $total
        );

        $this->model_extension_payment_greencryptopay->addOrder([
            'order_id' => $order_info['order_id'],
            'callback_secret' => $response['callback_secret'],
            'payment_currency' => $to_currency[0],
            'payment_amount' => $response['amount'],
            'payment_address' => $response['payment_address']
        ]);

        $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('payment_greencryptopay_pending_status'));
        $this->cart->clear();

        $query_string = [
            'order_id' => $this->session->data['order_id']
        ];

        $query_string['signature'] = $this->makeSignature($query_string);
        $this->response->redirect($this->url->link('extension/payment/greencryptopay/payment', null, true) . '&' . http_build_query($query_string));
    }

    public function payment()
    {
        $this->checkSignature($_GET);

        $this->load->model('checkout/order');
        $this->load->model('extension/payment/greencryptopay');

        $orderId = (int) $_GET['order_id'];

        $order = $this->model_checkout_order->getOrder($orderId);
        $greenCryptoPayData = $this->model_extension_payment_greencryptopay->getOrder($orderId);

        $data = [
            'order_id' => $orderId,
            'payment_address' => $greenCryptoPayData['payment_address'],
            'total' => $order['total'],
            'amount' => $greenCryptoPayData['payment_amount'],
            'payment_method' => $greenCryptoPayData['payment_currency'],
            'currency' => $order['currency_code'],
            'wallet_link' => $this->config->get('payment_greencryptopay_wallet_link'),
            'time_to_pay' => $this->config->get('payment_greencryptopay_time_to_pay'),
            'assets_path' => rtrim($this->config->get('config_url'), '/') . '/catalog/view/javascript/greencryptopay/'
        ];

        $this->response->setOutput($this->load->view('extension/payment/greencryptopay_payment_page', $data));
    }

    public function callback()
    {
        $result = [];

        $this->load->model('checkout/order');
        $this->load->model('extension/payment/greencryptopay');

        $data = json_decode(file_get_contents('php://input'), true);

        $order = $this->model_checkout_order->getOrder((int) $data['order_id']);

        if (empty($order) || $order['order_id'] != $data['order_id']) {
            throw new Exception('Order #' . $data['order_id'] . ' does not exists');
        }

        if ($order['payment_code'] != 'greencryptopay') {
            throw new Exception('Order #' . $data['order_id'] . ' payment method is not ' . $order['payment_method']);
        }

        $greencryptopayData = $this->model_extension_payment_greencryptopay->getOrder($order['order_id']);

        if ($data['callback_secret'] != $greencryptopayData['callback_secret']) {
            throw new Exception('Order #' . $order['order_id'] . ' unknown error');
        }

        if ($data['currency'] != $greencryptopayData['payment_currency']) {
            throw new Exception('Order #' .  $order['order_id'] . ' currency does not match');
        }

        if (strtolower($order['order_status']) == 'pending') {
            if ($data['amount_received'] >= $greencryptopayData['payment_amount'] && $data['confirmations'] >= $this->config->get('payment_greencryptopay_number_of_confirmations')) {
                $this->model_checkout_order->addOrderHistory($order['order_id'], $this->config->get('payment_greencryptopay_paid_status'), 'Order is paid', true);
                $this->notification($order['order_id']);
                $result['stop'] = true;
            }
        }

        $this->response->addHeader('HTTP/1.1 200 OK');
        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($result));
    }

    private function make_client()
    {
        $merchant_id = $this->config->get('payment_greencryptopay_merchant_id');
        $secret_key = $this->config->get('payment_greencryptopay_secret_key');
        $testnet = $this->config->get('payment_greencryptopay_testnet');

        if (empty($merchant_id)) {
            throw new Exception('The "Merchant id" parameter must be filled in the plugin settings.');
        }

        if (empty($secret_key)) {
            throw new Exception('The "Secret Key" parameter must be filled in the plugin settings.');
        }

        $client = Api::make('standard', $testnet);

        $client->setMerchantId($merchant_id);
        $client->setSecretKey($secret_key);

        return $client;
    }

    /**
     * @param array $requestParams
     * @return string
     */
    private function makeSignature(array $requestParams)
    {
        unset($requestParams['signature']);
        unset($requestParams['route']);
        return sha1(http_build_query($requestParams) . $this->request_signature);
    }

    /**
     * @param array $requestParams
     * @throws Exception
     */
    private function checkSignature(array $requestParams)
    {
        if ($requestParams['signature'] !== $this->makeSignature($requestParams)) {
            throw new Exception('Bad Request', 400);
        }
    }

    /**
     * @param $orderId
     * @throws Exception
     */
    private function notification($orderId)
    {
        $this->load->language('extension/payment/greencryptopay');

        $mail = new Mail($this->config->get('config_mail_engine'));
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $emails = $this->config->get('config_mail_alert_email');
        $notification_text = $this->language->get('notification_text_start') . $orderId .  $this->language->get('notification_text_end');

        foreach (explode(',', $emails) as $email) {
            $mail->setTo(trim($email));
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender($this->config->get('config_name'));
            $mail->setSubject($this->config->get('config_name') . ' - ' . $this->language->get('notification_subject'));
            $mail->setText($this->load->view('extension/payment/mail/greencryptopay_order_paid', [
                'notification_text' => $notification_text
            ]));
            $mail->send();
        }
    }

}
