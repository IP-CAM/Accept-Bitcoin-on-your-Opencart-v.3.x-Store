<?php

class ControllerExtensionPaymentGreencryptopay extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/greencryptopay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_greencryptopay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['action'] = $this->url->link('extension/payment/greencryptopay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/greencryptopay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $fields = array(
            'payment_greencryptopay_status',
            'payment_greencryptopay_testnet',
            'payment_greencryptopay_merchant_id',
            'payment_greencryptopay_secret_key',
            'payment_greencryptopay_number_of_confirmations',
            'payment_greencryptopay_request_signature',
            'payment_greencryptopay_sort_order',
            'payment_greencryptopay_entry_total',
            'payment_greencryptopay_geo_zone_id',
            'payment_greencryptopay_pending_status',
            'payment_greencryptopay_paid_status',
            'payment_greencryptopay_wallet_link',
            'payment_greencryptopay_time_to_pay',
        );

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        $data['payment_greencryptopay_sort_order'] = isset($this->request->post['payment_greencryptopay_sort_order']) ?
            $this->request->post['payment_greencryptopay_sort_order'] : $this->config->get('payment_greencryptopay_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/greencryptopay', $data));
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/greencryptopay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_greencryptopay_merchant_id'])) {
            $this->error['warning'] = $this->language->get('merchant_id_error');
        }

        if (empty($this->request->post['payment_greencryptopay_secret_key'])) {
            $this->error['warning'] = $this->language->get('secret_key_error');
        }

        return !$this->error;
    }

    public function install()
    {
        $this->load->model('extension/payment/greencryptopay');
        $this->model_extension_payment_greencryptopay->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/greencryptopay');
        $this->model_extension_payment_greencryptopay->uninstall();
    }
}
