<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Receipt;
use Exception;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

use function abs;
use function date;
use function filter_var;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function max;
use function mb_strlen;
use function min;
use function round;
use function sprintf;
use function str_pad;
use function time;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const STR_PAD_LEFT;

final class ReceiptController extends BaseController
{
    private const PAID_STATUSES = ['paid_gateway', 'paid_balance', 'paid_admin'];

    /**
     * @throws Exception
     */
    public function create(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        [$order, $invoice] = $this->getPaidOrder((int) $args['id']);

        if ($order === null || $invoice === null) {
            return $response->withRedirect('/user/order/' . (int) $args['id'] . '/view');
        }

        $receipt = (new Receipt())
            ->where('user_id', $this->user->id)
            ->where('order_id', $order->id)
            ->first();

        if ($receipt !== null) {
            return $response->withRedirect('/user/receipt/' . $receipt->id . '/view');
        }

        return $response->write(
            $this->view()
                ->assign('order', $order)
                ->assign('invoice', $invoice)
                ->assign('seller_name', $this->sellerConfig('receipt_seller_name', $_ENV['appName']))
                ->fetch('user/receipt/create.tpl')
        );
    }

    public function store(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        [$order, $invoice] = $this->getPaidOrder((int) $args['id']);

        if ($order === null || $invoice === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '仅已支付账单可以开具收据',
            ]);
        }

        $existing = (new Receipt())
            ->where('user_id', $this->user->id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing !== null) {
            return $response->withHeader('HX-Redirect', '/user/receipt/' . $existing->id . '/view');
        }

        $buyer = [
            'buyer_name' => $this->input($request, 'buyer_name'),
            'buyer_tax_id' => $this->input($request, 'buyer_tax_id'),
            'buyer_registration_no' => $this->input($request, 'buyer_registration_no'),
            'buyer_contact_no' => $this->input($request, 'buyer_contact_no'),
            'buyer_email' => $this->input($request, 'buyer_email'),
            'buyer_sst_no' => $this->input($request, 'buyer_sst_no'),
            'buyer_address' => $this->input($request, 'buyer_address'),
        ];

        if ($buyer['buyer_name'] === '') {
            return $response->withJson(['ret' => 0, 'msg' => '请填写收据抬头']);
        }

        $limits = [
            'buyer_name' => 255,
            'buyer_tax_id' => 255,
            'buyer_registration_no' => 255,
            'buyer_contact_no' => 64,
            'buyer_email' => 255,
            'buyer_sst_no' => 255,
            'buyer_address' => 1024,
        ];

        foreach ($limits as $field => $limit) {
            if (mb_strlen($buyer[$field]) > $limit) {
                return $response->withJson(['ret' => 0, 'msg' => '填写内容过长，请精简后重试']);
            }
        }

        if ($buyer['buyer_email'] !== '' && filter_var($buyer['buyer_email'], FILTER_VALIDATE_EMAIL) === false) {
            return $response->withJson(['ret' => 0, 'msg' => '邮箱格式不正确']);
        }

        [$unitPrice, $discount] = $this->invoiceAmounts($invoice);
        $taxRate = $this->taxRate();
        $total = round((float) $invoice->price, 2);
        $subtotal = round($total / (1 + $taxRate / 100), 2);
        $now = time();

        $receipt = new Receipt();
        $receipt->receipt_no = 'R' . date('Ymd', $invoice->pay_time > 0 ? $invoice->pay_time : $now)
            . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
        $receipt->user_id = $this->user->id;
        $receipt->order_id = $order->id;
        $receipt->invoice_id = $invoice->id;
        $receipt->seller_name = $this->sellerConfig('receipt_seller_name', $_ENV['appName']);
        $receipt->seller_registration_no = $this->sellerConfig('receipt_seller_registration_no');
        $receipt->seller_address = $this->sellerConfig('receipt_seller_address');
        $receipt->seller_tax_id = $this->sellerConfig('receipt_seller_tax_id');
        $receipt->seller_sst_no = $this->sellerConfig('receipt_seller_sst_no');
        $receipt->seller_logo = $this->sellerConfig(
            'receipt_seller_logo',
            '/images/uim-logo-round_768x768.png'
        );

        foreach ($buyer as $field => $value) {
            $receipt->{$field} = $value;
        }

        $receipt->item_name = $order->product_name;
        $receipt->quantity = 1;
        $receipt->unit_price = $unitPrice;
        $receipt->discount = $discount;
        $receipt->subtotal = $subtotal;
        $receipt->tax_rate = $taxRate;
        $receipt->tax_amount = round($total - $subtotal, 2);
        $receipt->total = $total;
        $receipt->currency = $this->sellerConfig('receipt_currency', 'CNY');
        $receipt->tax_region = $this->sellerConfig('receipt_tax_region', 'China');
        $receipt->tax_type = $this->sellerConfig('receipt_tax_type', 'Service Tax');
        $receipt->footer = $this->sellerConfig('receipt_footer', 'THANK YOU FOR SHOPPING');
        $receipt->create_time = $now;
        $receipt->update_time = $now;
        try {
            $receipt->save();
        } catch (QueryException $exception) {
            // 防止用户双击提交时因订单唯一索引产生 500 错误。
            $existing = (new Receipt())
                ->where('user_id', $this->user->id)
                ->where('order_id', $order->id)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $receipt = $existing;
        }

        return $response->withHeader('HX-Redirect', '/user/receipt/' . $receipt->id . '/view');
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $receipt = (new Receipt())
            ->where('user_id', $this->user->id)
            ->where('id', (int) $args['id'])
            ->first();

        if ($receipt === null) {
            return $response->withRedirect('/user/order');
        }

        $receipt->date = date('Y-m-d', $receipt->create_time);
        $receipt->unit_price_text = sprintf('%.2f', $receipt->unit_price);
        $receipt->discount_text = sprintf('%.2f', $receipt->discount);
        $receipt->subtotal_text = sprintf('%.2f', $receipt->subtotal);
        $receipt->tax_rate_text = sprintf('%.2f', $receipt->tax_rate);
        $receipt->tax_amount_text = sprintf('%.2f', $receipt->tax_amount);
        $receipt->total_text = sprintf('%.2f', $receipt->total);

        return $response->write(
            $this->view()
                ->assign('receipt', $receipt)
                ->fetch('user/receipt/view.tpl')
        );
    }

    private function getPaidOrder(int $orderId): array
    {
        $order = (new Order())
            ->where('user_id', $this->user->id)
            ->where('id', $orderId)
            ->first();

        if ($order === null) {
            return [null, null];
        }

        $invoice = (new Invoice())
            ->where('user_id', $this->user->id)
            ->where('order_id', $order->id)
            ->first();

        if ($invoice === null || ! in_array($invoice->status, self::PAID_STATUSES, true)) {
            return [null, null];
        }

        return [$order, $invoice];
    }

    private function input(ServerRequest $request, string $name): string
    {
        return trim((string) $this->antiXss->xss_clean($request->getParam($name) ?? ''));
    }

    private function sellerConfig(string $name, string $default = ''): string
    {
        return trim((string) ($_ENV[$name] ?? $default));
    }

    private function taxRate(): float
    {
        $value = $_ENV['receipt_tax_rate'] ?? 0;

        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(min(100, max(0, (float) $value)), 2);
    }

    private function invoiceAmounts(Invoice $invoice): array
    {
        $content = json_decode($invoice->content, true);
        $unitPrice = 0.0;
        $discount = 0.0;

        if (is_array($content)) {
            foreach ($content as $line) {
                $price = isset($line['price']) && is_numeric($line['price']) ? (float) $line['price'] : 0.0;

                if ($price >= 0) {
                    $unitPrice += $price;
                } else {
                    $discount += abs($price);
                }
            }
        }

        if ($unitPrice <= 0) {
            $unitPrice = (float) $invoice->price + $discount;
        }

        return [round($unitPrice, 2), round($discount, 2)];
    }
}
