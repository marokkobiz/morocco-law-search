<?php

namespace App\Support;

use App\Mail\ShopOrderAdminNotificationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the internal admin payment alert (info@marocloi.com, hra@marokkobiz.com)
 * whenever a shop order transitions to paid. Mirrors AdvisorNotifier but targets
 * the configured admin recipients instead of advisor-role users.
 */
class ShopAdminNotifier
{
    /**
     * @param array $recipients
     */
    public static function orderPaid(Order $order, ?array $recipients = null): void
    {
        $recipients = $recipients ?: (array) config('legal_aid.admin_payment_emails', []);

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)
                ->locale($order->locale ?: app()->getLocale())
                ->queue(new ShopOrderAdminNotificationMail($order->loadMissing('items.service')));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
