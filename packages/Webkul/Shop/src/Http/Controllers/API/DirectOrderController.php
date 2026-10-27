<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Webkul\Checkout\Facades\Cart;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shipping\Facades\Shipping;
use Webkul\Shop\Http\Requests\DirectOrderRequest;

class DirectOrderController extends APIController
{
    /**
     * Payment method used for direct orders.
     */
    public const PAYMENT_METHOD = 'cashondelivery';

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected OrderRepository $orderRepository
    ) {}

    /**
     * Place an order for a single product straight from the product page, without the cart.
     */
    public function store(DirectOrderRequest $request): JsonResponse
    {
        $product = $this->productRepository->find($request->input('product_id'));

        /**
         * Downloadable products need a customer account to deliver the files, so they cannot be ordered this way.
         */
        if (
            ! $product
            || ! $product->status
            || ! $product->visible_individually
            || $product->type === 'downloadable'
        ) {
            return $this->errorResponse(trans('shop::app.products.view.direct-order.product-unavailable'));
        }

        if (
            $product->type === 'configurable'
            && ! $request->filled('selected_configurable_option')
        ) {
            return $this->errorResponse(trans('shop::app.products.view.direct-order.select-options'));
        }

        if (! core()->getConfigData('sales.payment_methods.'.self::PAYMENT_METHOD.'.active')) {
            return $this->errorResponse(trans('shop::app.products.view.direct-order.payment-unavailable'));
        }

        /**
         * Start from an empty cart so nothing the visitor added earlier ends up in this order.
         */
        if (Cart::getCart()) {
            Cart::deActivateCart();
        }

        try {
            Cart::addProduct($product, array_merge(
                $request->except(['full_name', 'phone', 'address', 'region', 'latitude', 'longitude']),
                ['quantity' => (int) $request->input('quantity', 1), 'is_buy_now' => 1]
            ));

            Cart::saveAddresses(['billing' => $this->buildAddress($request)]);

            Cart::collectTotals();

            $cart = Cart::getCart();

            if ($cart->needsShippingMethod() && ! $this->selectFirstShippingMethod()) {
                throw new \Exception(trans('shop::app.products.view.direct-order.shipping-unavailable'));
            }

            Cart::savePaymentMethod(['method' => self::PAYMENT_METHOD]);

            Cart::collectTotals();

            if (Cart::hasError()) {
                throw new \Exception(trans('shop::app.products.view.direct-order.product-unavailable'));
            }

            $order = $this->orderRepository->create((new OrderResource(Cart::getCart()))->jsonSerialize());
        } catch (\Exception $exception) {
            if (Cart::getCart()) {
                Cart::deActivateCart();
            }

            return $this->errorResponse($exception->getMessage());
        }

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return new JsonResponse([
            'message' => trans('shop::app.products.view.direct-order.success'),
            'redirect_url' => route('shop.checkout.onepage.success'),
        ]);
    }

    /**
     * Build the cart address from the order form.
     *
     * @return array<string, mixed>
     */
    protected function buildAddress(DirectOrderRequest $request): array
    {
        $nameParts = preg_split('/\s+/', trim($request->input('full_name')), 2);

        $addressLines = [trim($request->input('address'))];

        if ($request->filled(['latitude', 'longitude'])) {
            $latitude = round((float) $request->input('latitude'), 6);

            $longitude = round((float) $request->input('longitude'), 6);

            $addressLines[] = trans('shop::app.products.view.direct-order.map-location').": https://www.openstreetmap.org/?mlat={$latitude}&mlon={$longitude}#map=17/{$latitude}/{$longitude}";
        }

        return [
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? $nameParts[0],
            'email' => null,
            'address' => $addressLines,
            'city' => $request->input('region') ?: '-',
            'country' => core()->getConfigData('sales.shipping.origin.country') ?: 'TZ',
            'phone' => $request->input('phone'),
            'use_for_shipping' => true,
        ];
    }

    /**
     * Choose the first available shipping rate, since the direct order form has no shipping step.
     */
    protected function selectFirstShippingMethod(): bool
    {
        $rates = Shipping::collectRates();

        foreach ($rates['shippingMethods'] ?? [] as $carrier) {
            foreach ($carrier['rates'] as $rate) {
                return Cart::saveShippingMethod($rate->method);
            }
        }

        return false;
    }

    /**
     * Return an error the order form can show to the customer.
     */
    protected function errorResponse(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
