<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Utils\Tools;
use DateTime;
use function is_numeric;
use function is_object;
use function json_decode;
use function time;

final class OrderActivation
{
    /**
     * Activate one paid order.
     *
     * The order and user rows are locked in the same transaction so payment
     * callbacks and the cron fallback can safely call this method together.
     */
    public static function activate(int $orderId): bool
    {
        return DB::connection('default')->transaction(static function () use ($orderId): bool {
            $order = (new Order())->where('id', $orderId)->lockForUpdate()->first();

            if ($order === null || $order->status !== 'pending_activation') {
                return false;
            }

            $user = (new User())->where('id', $order->user_id)->lockForUpdate()->first();

            if ($user === null) {
                return false;
            }

            $content = json_decode($order->product_content);

            if (! is_object($content)) {
                return false;
            }

            $activated = match ($order->product_type) {
                'tabp' => self::activateTabp($order, $user, $content),
                'bandwidth' => self::activateBandwidth($user, $content),
                'time' => self::activateTime($user, $content),
                'topup' => self::activateTopup($order, $content),
                default => false,
            };

            if (! $activated) {
                return false;
            }

            $order->status = 'activated';
            $order->update_time = time();
            $order->save();

            return true;
        }, 3);
    }

    /**
     * Keep TABP expiry processing in the cron fallback, but make it safe to
     * run concurrently with an incoming payment callback.
     */
    public static function expireTabp(int $orderId): bool
    {
        return DB::connection('default')->transaction(static function () use ($orderId): bool {
            $order = (new Order())->where('id', $orderId)->lockForUpdate()->first();

            if ($order === null || $order->status !== 'activated' || $order->product_type !== 'tabp') {
                return false;
            }

            $content = json_decode($order->product_content);

            if (! is_numeric($content->time ?? null) || $order->update_time + $content->time * 86400 >= time()) {
                return false;
            }

            $order->status = 'expired';
            $order->update_time = time();
            $order->save();

            return true;
        }, 3);
    }

    private static function activateTabp(Order $order, User $user, object $content): bool
    {
        if (! is_numeric($content->bandwidth ?? null) || ! is_numeric($content->time ?? null)
            || ! self::hasClassSettings($content)) {
            return false;
        }

        $activeOrder = (new Order())->where('user_id', $order->user_id)
            ->where('status', 'activated')
            ->where('product_type', 'tabp')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeOrder !== null) {
            $activeContent = json_decode($activeOrder->product_content);

            if (! is_numeric($activeContent->time ?? null) || $activeOrder->update_time + $activeContent->time * 86400 >= time()) {
                return false;
            }

            $activeOrder->status = 'expired';
            $activeOrder->update_time = time();
            $activeOrder->save();
        }

        $user->u = 0;
        $user->d = 0;
        $user->transfer_today = 0;
        $user->transfer_enable = Tools::gbToB($content->bandwidth);
        $user->class = $content->class;
        $user->class_expire = (new DateTime())
            ->modify('+' . $content->class_time . ' days')->format('Y-m-d H:i:s');
        $user->node_group = $content->node_group;
        $user->node_speedlimit = $content->speed_limit;
        $user->node_iplimit = $content->ip_limit;
        $user->save();

        return true;
    }

    private static function activateBandwidth(User $user, object $content): bool
    {
        if (! is_numeric($content->bandwidth ?? null)) {
            return false;
        }

        $user->transfer_enable += Tools::gbToB($content->bandwidth);
        $user->save();

        return true;
    }

    private static function activateTime(User $user, object $content): bool
    {
        if (! self::hasClassSettings($content) || ($user->class !== (int) $content->class && $user->class > 0)) {
            return false;
        }

        $user->class = $content->class;
        $user->class_expire = (new DateTime($user->class_expire))
            ->modify('+' . $content->class_time . ' days')->format('Y-m-d H:i:s');
        $user->node_group = $content->node_group;
        $user->node_speedlimit = $content->speed_limit;
        $user->node_iplimit = $content->ip_limit;
        $user->save();

        return true;
    }

    private static function hasClassSettings(object $content): bool
    {
        return is_numeric($content->class_time ?? null)
            && isset($content->class, $content->node_group, $content->speed_limit, $content->ip_limit);
    }

    private static function activateTopup(Order $order, object $content): bool
    {
        if (! is_numeric($content->amount ?? null) || $content->amount <= 0) {
            return false;
        }

        Wallet::credit($order->user_id, $content->amount, "充值订单 #{$order->id}");

        return true;
    }
}
