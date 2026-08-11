<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Query\Builder;

/**
 * @property int    $id
 * @property string $receipt_no
 * @property int    $user_id
 * @property int    $order_id
 * @property int    $invoice_id
 * @property string $seller_name
 * @property string $seller_registration_no
 * @property string $seller_address
 * @property string $seller_tax_id
 * @property string $seller_sst_no
 * @property string $seller_logo
 * @property string $buyer_name
 * @property string $buyer_tax_id
 * @property string $buyer_registration_no
 * @property string $buyer_contact_no
 * @property string $buyer_email
 * @property string $buyer_sst_no
 * @property string $buyer_address
 * @property string $item_name
 * @property int    $quantity
 * @property float  $unit_price
 * @property float  $discount
 * @property float  $subtotal
 * @property float  $tax_rate
 * @property float  $tax_amount
 * @property float  $total
 * @property string $currency
 * @property string $tax_region
 * @property string $tax_type
 * @property string $footer
 * @property int    $create_time
 * @property int    $update_time
 *
 * @mixin Builder
 */
final class Receipt extends Model
{
    protected $connection = 'default';
    protected $table = 'receipt';
}
