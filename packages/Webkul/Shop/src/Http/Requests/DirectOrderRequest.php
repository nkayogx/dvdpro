<?php

namespace Webkul\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Rules\PhoneNumber;

class DirectOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return core()->isDirectOrderEnabled();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new PhoneNumber],
            'address' => ['required', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    /**
     * Get the validation attribute names.
     */
    public function attributes(): array
    {
        return [
            'full_name' => trans('shop::app.products.view.direct-order.full-name'),
            'phone' => trans('shop::app.products.view.direct-order.phone'),
            'address' => trans('shop::app.products.view.direct-order.address'),
        ];
    }
}
