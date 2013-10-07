<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Spyc;

class PayDollar extends PaymentGateway {

    const FEED_NEW      = 'new';
    const FEED_INVALID  = 'invalid';
    const FEED_REJECTED = 'rejected';
    const FEED_ACCEPTED = 'accepted';

    protected function init() {
        $this->loadConfig('pay-dollar');
    }

    public function buildParams(PaymentInterface $payment) {
        $params = array(
            'merchantId' => $payment->getMerchantId(),
            'amount'     => $payment->getAmount(),
            'orderRef'   => $payment->getRefCode(),
            'currCode'   => $this->currency_code[$payment->getCurrency()],
            'mpsMode'    => $this->mps_mode,
            'successUrl' => $this->success_url,
            'failUrl'    => $this->fail_url,
            'cancelUrl'  => $this->cancel_url,
            'payType'    => $this->pay_type,
            'lang'       => $this->lang,
            'payMethod'  => $this->pay_method            
        );        
        $params['secureHash'] = $this->generateSecureHash($params);
        return $params;
    }

    public function getPostUrl() { return $this->post_url; }

    public function generateSecureHash(array $params) {
        $hash = "{$params['merchantId']}|{$params['orderRef']}|{$params['currCode']}|{$params['amount']}|{$params['payType']}|{$this->secure_hash_secret}";
        return sha1($hash);
    }

    public function verifyDataFeed($data) {
        $params = array(
            'src',
            'prc',
            'successcode',
            'Ref',
            'PayRef',
            'Cur',
            'Amt',
            'payerAuth'
        );
        $str = '';
        foreach ($params as $param) {
            $str .= $data[$param] . '|';
        }
        $str .= $this->secure_hash_secret;
        return $data['secureHash'] == sha1($str);
    }

    public function verifyPaymentData($currency, $amount, $data) {
        // check amount and currency
        return $amount == $data['Amt'] && $this->currency_code[$currency] == $data['Cur'];
    }

    public function acceptDataFeed(PayDollarDataFeedInterface $feed, $comment='') {
        $this->updateDataFeed($feed, self::FEED_ACCEPTED, $comment);
    }

    public function rejectDataFeed(PayDollarDataFeedInterface $feed, $comment='') {
        $this->updateDataFeed($feed, self::FEED_REJECTED, $comment);
    }

    public function invalidateDataFeed(PayDollarDataFeedInterface $feed, $comment='') {
        $this->updateDataFeed($feed, self::FEED_INVALID, $comment);
    }

    public function updateDataFeed(PayDollarDataFeedInterface $feed, $status, $comment='') {
        $feed->setStatus($status);
        $feed->setComment($comment);
        return $feed->save();
    }

}