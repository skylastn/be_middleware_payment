<?php

namespace App\Enums;

/**
 * Represents the "direct" (non-hosted) card / PaymentIntent flow for Stripe.
 *
 * When any of these values is passed as `flow` or `payment_flow` on /order/create,
 * the gateway will create a Stripe PaymentIntent instead of a Checkout Session (hosted URL).
 *
 * All cases below are treated identically today (they all enable the direct flow + /stripe/confirm support).
 * They exist as separate cases mainly for:
 * - backward compatibility with different client integrations
 * - clearer naming in logs / future differentiation if needed
 *
 * Legacy/alternative strings (still accepted, case-insensitive):
 * - 'creditcard'        → CREDIT_CARD
 * - 'paymentintent'     → PAYMENT_INTENT
 * - 'intent'            → PAYMENT_INTENT   (common shorthand for "PaymentIntent")
 */
enum DirectFlow: string
{
    case DIRECT = 'direct';

    /** Paying with card using the direct (non-hosted) flow. */
    case CARD = 'card';

    /** Explicit credit card in direct flow (common variant of "card"). */
    case CREDIT_CARD = 'credit_card';

    /** Direct card flow, explicitly named. */
    case DIRECT_CARD = 'direct_card';

    /**
     * Direct Stripe PaymentIntent flow.
     *
     * "intent" and "payment_intent" (and the smashed "paymentintent") are historical
     * ways clients asked for the raw PaymentIntent instead of Stripe Checkout hosted page.
     */
    case PAYMENT_INTENT = 'payment_intent';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (DirectFlow $flow): string => $flow->value,
            self::cases(),
        );
    }

    /**
     * Parse an incoming flow value (from request) into a DirectFlow.
     * Accepts the canonical values above as well as common legacy aliases
     * (creditcard, paymentintent, intent, with or without underscores, any case).
     */
    public static function fromName(string|DirectFlow|null $value): ?DirectFlow
    {
        if ($value instanceof DirectFlow || $value === null) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'direct' => self::DIRECT,
            'card' => self::CARD,
            'credit_card', 'creditcard' => self::CREDIT_CARD,
            'direct_card' => self::DIRECT_CARD,
            'payment_intent', 'paymentintent', 'intent' => self::PAYMENT_INTENT,
            default => null,
        };
    }

    /**
     * Returns true if the given value (string or enum) should trigger the direct card/PaymentIntent flow.
     */
    public static function isDirect(string|DirectFlow|null $value): bool
    {
        return self::fromName($value) !== null;
    }
}
