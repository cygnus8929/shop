<?php
/**
 * This file contains the Stripe IPN class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     shop
 * @version     v0.7.1
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 *  Class to provide IPN for internal-only transactions,
 *  such as zero-balance orders.
 *
 *  @package shop
 */
class stripe extends \Shop\IPN
{
    /** Event object obtained from the IPN payload.
     * @var object */
    private $_event;

    /** Payment Intent object obtained from the ID in the Event object.
     * @var object */
    private $_payment;

    /** Currency object, used for formatting numbers.
     * @var object */
    private $_currency;


    /**
     * Constructor.
     *
     * @param   string  $A      Payload provided by Stripe
     */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->gw_id = 'stripe';

        parent::__construct();  // construct without IPN data.

        $this->_event = $A;        
        $session = $this->_event->data->object;
        $order_id = $session->client_reference_id;
        $this->ipn_data['order_id'] = $order_id;
        $this->txn_id = $session->payment_intent;

        if (!empty($order_id)) {
            $this->Order = $this->getOrder($order_id);
        }
        if ($this->Order->isNew) return NULL;

        $this->order_id = $this->Order->order_id;
        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto) && !empty($billto)) {
            $shipto = $billto;
        }

        $this->payer_email = $this->Order->buyer_email;
        $this->payer_name = $_USER['fullname'];
        $this->pmt_date = $_CONF['_now']->toMySQL(true);
        $this->gw_name = $this->gw->Name();;
        $this->status = $status;

        $this->shipto = array(
            'name'      => SHOP_getVar($shipto, 'name'),
            'company'   => SHOP_getVar($shipto, 'company'),
            'address1'  => SHOP_getVar($shipto, 'address1'),
            'address2'  => SHOP_getVar($shipto, 'address2'),
            'city'      => SHOP_getVar($shipto, 'city'),
            'state'     => SHOP_getVar($shipto, 'state'),
            'country'   => SHOP_getVar($shipto, 'country'),
            'zip'       => SHOP_getVar($shipto, 'zip'),
        );

        $this->custom = array(
            'transtype' => $this->gw->Name(),
            'uid'       => $this->Order->uid,
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        );

        //$total_shipping = $this->Order->shipping;
        //$total_handling = $this->Order->handling;
        //$total_tax = $this->Order->tax;

        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->product_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->getShortDscp(),
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->AddItem($args);
            //$total_shipping += $item->shipping;
            //$total_handling += $item->handling;
        }
    }


    /**
     * Verify the transaction.
     * This just checks that a valid cart_id was received along with other
     * variables.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        // Get the payment intent from Stripe
        $trans = $this->gw->getPayment($this->txn_id);
        $this->_payment = $trans;

        $this->status = 'pending';
        if (!$trans || $trans->status != 'succeeded') {
            // Payment verification failed.
            return false;
        }

        // Verification succeeded, get payment info.
        $this->status = 'paid';
        $this->currency = strtoupper($trans->currency);
        $this->_currency = \Shop\Currency::getInstance($this->currency);
        $this->pmt_gross = $this->_currency->fromInt($trans->amount_received);

        $session = $this->_event->data->object;
        $pmt_shipping = 0;
        $pmt_tax = 0;
        foreach ($session->display_items as $item) {
            if ($item->custom->name == '__tax') {
                $pmt_tax += $this->_currency->fromInt($item->amount);
            } elseif ($item->custom->name == '__shipping') {
                $pmt_shipping += $this->_currency->fromInt($item->amount);
            } elseif ($item->custom->name == '__gc') {
                // TODO when Stripe supports coupons
                $this->addCredit('gc', $item->amount);
            }
        }
        $this->pmt_tax = $pmt_tax;
        $this->pmt_shipping = $pmt_shipping;
        $this->ipn_data['pmt_shipping'] = $this->pmt_shipping;
        $this->ipn_data['pmt_tax'] = $this->pmt_tax;
        $this->ipn_data['pmt_gross'] = $this->pmt_gross;
        $this->ipn_data['status'] = $this->status;  // to get into handlePurchase()
        return true;
    }


    /**
     * Process an incoming IPN transaction.
     * Do the following:
     *  - Verify IPN
     *  - Log IPN
     *  - Check that transaction is complete
     *  - Check that transaction is unique
     *  - Check for valid receiver email address
     *  - Process IPN
     *
     * @uses   IPN::AddItem()
     * @uses   IPN::handleFailure()
     * @uses   IPN::handlePurchase()
     * @uses   IPN::isUniqueTxnId()
     * @uses   IPN::Log()
     * @uses   Verify()
     * @param  array   $in     POST variables of transaction
     * @return boolean true if processing valid and completed, false otherwise
     */
    public function Process()
    {
        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                IPN_FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        } elseif (!$this->isUniqueTxnId()) {
            COM_errorLog("Duplicate Txn ID " . $this->txn_id);
            $logId = $this->Log(false);
            return false;
        } else {
            $logId = $this->Log(true);
        }

        // If no data has been received, then there's nothing to do.
        if (empty($this->_payment)) {
            return false;
        }
        // Backward compatibility, get custom data into IPN for plugin
        // products.
        //$this->ipn_data['custom'] = $this->custom;

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($this->Order as $idx=>$item) {
            $args = array(
                'item_id'   => $item->item_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->name,
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->AddItem($args);
        }

        return $this->handlePurchase();
    }

}

?>
