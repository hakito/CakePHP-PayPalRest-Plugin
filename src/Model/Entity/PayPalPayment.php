<?

namespace PayPal\Model\Entity;

use Cake\I18n\FrozenDate;
use Cake\ORM\Entity;

/**
 * @property int $id
 * @property FrozenDate $created
 * @property FrozenDate $modified
 * @property string $payment_id
 * @property string $payment_state
 * @property string $sale_state
 * @property string remittance_identifier
 * @property FrozenDate $remitted_moment
 */
class PayPalPayment extends Entity
{
}