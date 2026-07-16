<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\Config;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Paylist;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Services\DB;
use App\Services\OrderActivation;
use App\Services\Reward;
use App\Utils\Tools;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use voku\helper\AntiXSS;
use function bcadd;
use function bccomp;
use function get_called_class;
use function in_array;
use function json_decode;
use function time;

abstract class Base
{
    protected AntiXSS $antiXss;

    abstract public function purchase(ServerRequest $request, Response $response, array $args): ResponseInterface;

    abstract public function notify(ServerRequest $request, Response $response, array $args): ResponseInterface;

    /**
     * 支付网关的 codeName
     */
    abstract public static function _name(): string;

    /**
     * 是否启用支付网关
     */
    abstract public static function _enable(): bool;

    /**
     * 显示给用户的名称
     */
    abstract public static function _readableName(): string;

    public function getReturnHTML(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write('ok');
    }

    abstract public static function getPurchaseHTML(): string;

    public function postPayment(string $trade_no): void
    {
        $orderId = DB::connection('default')->transaction(static function () use ($trade_no): ?int {
            $paylist = (new Paylist())->where('tradeno', $trade_no)->lockForUpdate()->first();

            if ($paylist === null) {
                throw new RuntimeException('Payment record not found: ' . $trade_no);
            }

            $invoice = (new Invoice())->where('id', $paylist->invoice_id)->lockForUpdate()->first();

            if ($invoice === null) {
                throw new RuntimeException('Invoice not found for payment: ' . $trade_no);
            }

            if ((int) $paylist->status === 0) {
                $paylist->datetime = time();
                $paylist->status = 1;
                $paylist->save();
            }

            $newlyPaid = in_array($invoice->status, ['unpaid', 'partially_paid']) &&
                bccomp((string) $paylist->total, (string) $invoice->price, 2) >= 0;

            if ($newlyPaid) {
                $invoice->status = 'paid_gateway';
                $invoice->update_time = time();
                $invoice->pay_time = time();
                $invoice->save();
            }

            if (! $newlyPaid && $invoice->status !== 'paid_gateway') {
                return null;
            }

            $order = (new Order())->where('id', $invoice->order_id)->lockForUpdate()->first();

            if ($order !== null && $order->status === 'pending_payment') {
                $order->status = 'pending_activation';
                $order->update_time = time();
                $order->save();
            }

            if ($newlyPaid) {
                $user = (new User())->where('id', $paylist->userid)->lockForUpdate()->first();

                if ($user !== null && bccomp((string) $paylist->total, (string) $invoice->price, 2) > 0) {
                    $overpaid = bcadd((string) $paylist->total, '-' . (string) $invoice->price, 2);
                    $moneyBefore = (string) $user->money;
                    $user->money = bcadd($moneyBefore, $overpaid, 2);
                    $user->save();
                    (new UserMoneyLog())->add(
                        $user->id,
                        (float) $moneyBefore,
                        (float) $user->money,
                        (float) $overpaid,
                        '超额支付账单 #' . $invoice->id
                    );
                }

                if ($user !== null && $user->ref_by > 0 && Config::obtain('invite_mode') === 'reward') {
                    Reward::issuePaybackReward($user->id, $user->ref_by, $invoice->price, $paylist->invoice_id);
                }
            }

            return $order === null ? null : (int) $order->id;
        }, 3);

        if ($orderId !== null) {
            OrderActivation::activate($orderId);
        }
    }

    public static function generateGuid(): string
    {
        return Tools::genRandomChar();
    }

    protected static function getCallbackUrl(): string
    {
        return $_ENV['baseUrl'] . '/payment/notify/' . get_called_class()::_name();
    }

    protected static function getUserReturnUrl(): string
    {
        return $_ENV['baseUrl'] . '/user/payment/return/' . get_called_class()::_name();
    }

    protected static function getActiveGateway(string $key): bool
    {
        $payment_gateways = (new Config())->where('item', 'payment_gateway')->first();
        $active_gateways = json_decode($payment_gateways->value);

        if (in_array($key, $active_gateways)) {
            return true;
        }

        return false;
    }
}
