<?php

namespace App\Services\Suppliers\Sabre\Ndc;

/**
 * Build Sabre NDC REST payloads (Offer Price, OrderCreate, OrderRetrieve, OrderChange).
 */
final class SabreNdcPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $offerContext
     * @return array<string, mixed>
     */
    public function buildOfferPrice(array $offerContext): array
    {
        return array_filter([
            'party' => [
                'sender' => [
                    'travelAgency' => array_filter([
                        'iataNumber' => $offerContext['iata_number'] ?? null,
                        'pseudoCityCode' => $offerContext['pcc'] ?? null,
                    ]),
                ],
            ],
            'request' => [
                'pricedOffer' => [
                    'selectedOffer' => [
                        [
                            'offerId' => (string) ($offerContext['offer_id'] ?? ''),
                            'selectedOfferItems' => is_array($offerContext['selected_offer_items'] ?? null)
                                ? $offerContext['selected_offer_items']
                                : [],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $orderContext
     * @param  list<array<string, mixed>>  $passengers
     * @return array<string, mixed>
     */
    public function buildOrderCreate(array $orderContext, array $passengers): array
    {
        return array_filter([
            'party' => [
                'sender' => [
                    'travelAgency' => array_filter([
                        'pseudoCityCode' => $orderContext['pcc'] ?? null,
                    ]),
                ],
            ],
            'createOrders' => [
                [
                    'offerId' => (string) ($orderContext['offer_id'] ?? ''),
                    'selectedOfferItems' => is_array($orderContext['selected_offer_items'] ?? null)
                        ? $orderContext['selected_offer_items']
                        : [],
                ],
            ],
            'passengers' => $passengers,
            'contactInfos' => is_array($orderContext['contact_infos'] ?? null) ? $orderContext['contact_infos'] : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOrderRetrieve(string $orderId): array
    {
        return ['orderId' => $orderId];
    }

    /**
     * Binham orders/view shape.
     *
     * @return array<string, mixed>
     */
    public function buildOrderView(string $orderId): array
    {
        return ['id' => $orderId];
    }

    /**
     * Binham repriceOrder shape.
     *
     * @return array<string, mixed>
     */
    public function buildRepriceOrder(string $orderId): array
    {
        return ['request' => ['orderId' => $orderId]];
    }

    /**
     * Binham orders/change acceptOffers shape.
     *
     * @param  list<array<string, mixed>>  $selectedOfferItems
     * @return array<string, mixed>
     */
    public function buildOrderChange(string $orderId, string $offerId, array $selectedOfferItems): array
    {
        if ($offerId === '' || $selectedOfferItems === []) {
            return ['id' => $orderId];
        }

        return [
            'id' => $orderId,
            'orderItemUpdates' => [[
                'acceptOffers' => [[
                    'offerId' => $offerId,
                    'selectedOfferItems' => $selectedOfferItems,
                ]],
            ]],
        ];
    }
}
