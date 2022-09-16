<?php

namespace PayPal\Controller\Component;

use Cake\Core\Configure;
use Cake\Controller\Component;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Database\Exception\NestedTransactionRollbackException;
use Cake\Datasource\FactoryLocator;
use Cake\Event\EventInterface;
use InvalidArgumentException;
use Exception;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\ItemList;
use PayPal\Api\Item;
use PayPal\Model\Table\PayPalPaymentsTable;
use Throwable;

class PayPalComponent extends Component
{

    /** @var \PayPal\Api\Item[] */
    private $items;

    /** @var int shipping in cents */
    public $Shipping = "0";

    /** @var PayPalPaymentsTable */
    public $PayPalPayments;

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->config = Configure::read('PayPal');
        $this->items = [];
        $this->PayPalPayments = FactoryLocator::get('Table')->get('PayPal.PayPalPayments');
    }

    public function startup(EventInterface $event)
    {
        $this->Controller = $event->getSubject();
    }

    /**
     *
     * @param int $price in cents
     */
    public function AddArticle(string $name, int $quantity, int $price, string $id = null)
    {
        $item = new Item();
        $item->setName($name);
        $item->setQuantity($quantity);
        $item->setPrice($price / 100.0);
        $item->CentPrice = $price;
        $item->setCurrency($this->config['currency']);

        if ($id != null)
        {
            $item->setSku($id);
            $this->items[$id] = $item;
        } else
        {
            $this->items[] = $item;
        }
    }

    /**
     * @return int sum of all item prices (in cents) multiplied by quantity
     */
    public function GetItemTotal(): int
    {
        $itemSum = 0;
        foreach($this->items as $item)
            $itemSum += $item->CentPrice * $item->getQuantity();
        return $itemSum;
    }

    /**
     *
     * @param string $remittanceIdentifier id to be used for the callback function
     * @param string $okUrl Redirect URL on successful payment
     * @param string $cancelUrl Redirect URL on user abort
     * @param string|null $description Optional transaction description
     * @param int|null $tax Optional tax in cents. Will be calculated based on tax from config if not specified.
     * @throws InvalidArgumentException
     * @throws MissingDatasourceConfigException
     * @throws Exception
     * @throws Throwable
     * @throws NestedTransactionRollbackException
     */
    public function PaymentRedirect(string $remittanceIdentifier, string $okUrl, string $cancelUrl, string $description = null, int $tax = null): void
    {
        $payer = new Payer();

        $payer->setPaymentMethod("paypal");

        $itemSum = 0;
        $itemArray = array();
        foreach($this->items as $item)
        {
            $itemSum += $item->CentPrice * $item->getQuantity();
            unset ($item->CentPrice);
            $itemArray[] = $item;
        }

        $amountDetails = new \PayPal\Api\Details();
        $amountDetails->setSubtotal($this->FormatMonetaryDecimal($itemSum));
        if ($tax == null)
        {
            $taxPercent = $this->config['tax'];
            $tax = (int) round($itemSum * $taxPercent);
        }
        $amountDetails->setTax($this->FormatMonetaryDecimal($tax));
        $amountDetails->setShipping($this->FormatMonetaryDecimal($this->Shipping));


        $amount = new Amount();
        $amount->setDetails($amountDetails);
        $amount->setCurrency($this->config['currency']);
        $amount->setTotal($this->FormatMonetaryDecimal($itemSum + $tax + $this->Shipping));

        $itemList = new ItemList();
        $itemList->setItems($itemArray);

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        if ($description != null)
            $transaction->setDescription($description);
        $transaction->setItemList($itemList);

        $payment = new Payment();
        $payment->setPayer($payer);
        $payment->setTransactions([$transaction]);
        $payment->setIntent('sale');

        $this->PayPalPayments->createPayment($remittanceIdentifier, $payment, $okUrl, $cancelUrl);

        foreach ($payment->getLinks() as $link)
        {
            if ($link->getRel() == 'approval_url')
            {
                $redirectUrl = $link->getHref();
                break;
            }
        }

        if(isset($redirectUrl))
        {
            $this->Controller->redirect($redirectUrl);
        }
    }

    private function FormatMonetaryDecimal($val): string
    {
        if (is_string($val))
        {
            if (preg_match('/^[0-9]+$/', $val) > 0)
                $val += 0;
        }

        if (!is_int($val))
        {
            throw new \InvalidArgumentException(sprintf("Int value expected but %s received", gettype($val)));
        }

        if (strlen($val) < 3)
        {
            $prefix = '0.';
            if (strlen($val) < 2)
                $prefix = '0.0';

            if (strlen($val) < 1)
                    return '0.00';

            return $prefix . $val;
        }

        $intVal = substr($val, 0, -2);
        $centVal = substr($val, -2);
        return $intVal . '.' . $centVal;
    }

    /**
     *
     * @return int PayPal fee based on amount
     * @throws InvalidArgumentException if PayPal conditions are not set in config
     */
    public static function CalculateFee(int $amount): float
    {
        $conditions = self::_getConditionsFromConfig();
        return $amount * $conditions['fee_relative'] + $conditions['fee'];
    }

    /**
     *
     * @return int amount plus neutralization amount so when PayPal subtract it's fee
     * the intended amount will be received.
     * @throws InvalidArgumentException if PayPal conditions are not set in config
     */
    public static function NeutralizeFee(int $amountInCents): int
    {
        $conditions = self::_getConditionsFromConfig();
        return $amountInCents + ceil(self::CalculateFee($amountInCents) / ( 1 - $conditions['fee_relative'] ));
    }

    private static function _getConditionsFromConfig(): array
    {
        $config = Configure::read('PayPal');
        if (empty($config['conditions']))
            throw new \InvalidArgumentException('Missing PayPal conditions.');

        $conditions = $config['conditions'];
        if (!isset($conditions['fee']) || !isset($conditions['fee_relative']))
            throw new \InvalidArgumentException('Missing PayPal condition fees.');

        return $conditions;
    }
}