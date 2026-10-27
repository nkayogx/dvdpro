{!! view_render_event('bagisto.shop.products.view.direct_order.before', ['product' => $product]) !!}

<!-- Direct Order Form -->
<div class="mt-8 max-w-[470px] rounded-xl border border-zinc-200 p-5 max-sm:mt-4 max-sm:p-4">
    <p class="mb-4 text-center text-lg font-medium max-sm:text-base">
        @lang('shop::app.products.view.direct-order.title')
    </p>

    @if ($product->getTypeInstance()->showQuantityBox())
        <x-shop::quantity-changer
            name="quantity"
            value="1"
            class="mb-4 w-max gap-x-4 rounded-xl px-7 py-3 max-sm:gap-x-5 max-sm:rounded-lg max-sm:px-4 max-sm:py-1.5"
            @change="directOrder.quantity = $event"
        />
    @endif

    <!-- Full Name -->
    <x-shop::form.control-group>
        <x-shop::form.control-group.label class="required !mt-0">
            @lang('shop::app.products.view.direct-order.full-name')
        </x-shop::form.control-group.label>

        <x-shop::form.control-group.control
            type="text"
            name="full_name"
            rules="required"
            autocomplete="name"
            :label="trans('shop::app.products.view.direct-order.full-name')"
            :placeholder="trans('shop::app.products.view.direct-order.full-name')"
        />

        <x-shop::form.control-group.error control-name="full_name" />
    </x-shop::form.control-group>

    <!-- Phone Number -->
    <x-shop::form.control-group>
        <x-shop::form.control-group.label class="required !mt-0">
            @lang('shop::app.products.view.direct-order.phone')
        </x-shop::form.control-group.label>

        <x-shop::form.control-group.control
            type="text"
            name="phone"
            rules="required|phone"
            autocomplete="tel"
            :label="trans('shop::app.products.view.direct-order.phone')"
            placeholder="07XX XXX XXX"
        />

        <p class="mt-1 text-xs text-zinc-500">
            @lang('shop::app.products.view.direct-order.phone-hint')
        </p>

        <x-shop::form.control-group.error control-name="phone" />
    </x-shop::form.control-group>

    <!-- Address with map pin -->
    <x-shop::form.control-group>
        <x-shop::form.control-group.label class="required !mt-0">
            @lang('shop::app.products.view.direct-order.address')
        </x-shop::form.control-group.label>

        <div class="relative">
            <x-shop::form.control-group.control
                type="text"
                name="address"
                rules="required"
                v-model="directOrder.address"
                autocomplete="street-address"
                style="padding-left: 3rem;"
                :label="trans('shop::app.products.view.direct-order.address')"
                :placeholder="trans('shop::app.products.view.direct-order.address-placeholder')"
            />

            <button
                type="button"
                class="absolute flex items-center justify-center rounded-lg transition-all hover:bg-zinc-100"
                style="left: 0.375rem; top: calc(50% - 3px); transform: translateY(-50%); width: 2.25rem; height: 2.25rem;"
                :class="directOrder.latitude ? 'text-navyBlue' : 'text-zinc-500'"
                title="@lang('shop::app.products.view.direct-order.pin-location')"
                aria-label="@lang('shop::app.products.view.direct-order.pin-location')"
                @click="$refs.directOrderMap.open()"
            >
                <span class="icon-location text-2xl"></span>
            </button>
        </div>

        <p
            class="flex flex-wrap items-center gap-x-2 text-xs"
            v-if="directOrder.latitude"
        >
            <span class="font-medium text-navyBlue">
                @lang('shop::app.products.view.direct-order.location-pinned')<template v-if="directOrder.region"> · @{{ directOrder.region }}</template>
            </span>

            <button
                type="button"
                class="text-navyBlue underline"
                @click="$refs.directOrderMap.open()"
            >
                @lang('shop::app.products.view.direct-order.change')
            </button>

            <button
                type="button"
                class="text-zinc-500 underline"
                @click="clearDirectOrderLocation"
            >
                @lang('shop::app.products.view.direct-order.remove')
            </button>
        </p>

        <p
            class="text-xs text-zinc-500"
            v-else
        >
            @lang('shop::app.products.view.direct-order.pin-hint')
        </p>

        <x-shop::form.control-group.error control-name="address" />
    </x-shop::form.control-group>

    <v-direct-order-map
        ref="directOrderMap"
        @located="setDirectOrderLocation"
    ></v-direct-order-map>

    <input type="hidden" name="latitude" :value="directOrder.latitude">

    <input type="hidden" name="longitude" :value="directOrder.longitude">

    <input type="hidden" name="region" :value="directOrder.region">

    <x-shop::button
        type="submit"
        class="primary-button mt-5 w-full max-w-full max-md:py-3 max-sm:rounded-lg max-sm:py-2"
        button-type="primary-button"
        ::title="directOrderButtonTitle"
        :disabled="! $product->isSaleable(1)"
        ::loading="isStoring.directOrder"
        ::disabled="isStoring.directOrder"
        @click="showDirectOrderErrors"
    />
</div>

{!! view_render_event('bagisto.shop.products.view.direct_order.after', ['product' => $product]) !!}

@pushOnce('styles')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
@endPushOnce

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-direct-order-map-template"
    >
        <x-shop::modal
            ref="mapModal"
            @open="onOpen"
        >
            <x-slot:header>
                <h2 class="text-lg font-semibold max-md:text-base">
                    @lang('shop::app.products.view.direct-order.map-label')
                </h2>
            </x-slot>

            <x-slot:content style="padding-top: 1.25rem; padding-bottom: 1.25rem;">
                <div class="mb-2 flex gap-2">
                    <input
                        type="text"
                        class="w-full rounded-lg border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400"
                        v-model="searchQuery"
                        placeholder="@lang('shop::app.products.view.direct-order.map-search-placeholder')"
                        @keydown.enter.prevent="search"
                    >

                    <button
                        type="button"
                        class="secondary-button whitespace-nowrap px-4 py-2 text-sm"
                        :disabled="isSearching"
                        @click="search"
                    >
                        @lang('shop::app.products.view.direct-order.search')
                    </button>
                </div>

                <div
                    ref="map"
                    class="w-full overflow-hidden rounded-lg border"
                    style="height: 360px; max-height: 50vh; position: relative; z-index: 0;"
                ></div>

                <div class="mt-2 flex items-center justify-between gap-2">
                    <p class="text-xs text-zinc-500">
                        @lang('shop::app.products.view.direct-order.map-hint')
                    </p>

                    <button
                        type="button"
                        class="whitespace-nowrap text-xs font-medium text-navyBlue underline"
                        v-if="canUseGeolocation"
                        @click="useCurrentLocation"
                    >
                        @lang('shop::app.products.view.direct-order.use-my-location')
                    </button>
                </div>

                <p
                    class="mt-1 text-xs text-red-500"
                    v-if="searchError"
                    v-text="searchError"
                ></p>
            </x-slot>

            <x-slot:footer
                class="!mt-0"
                style="padding-top: 1rem; padding-bottom: 1rem;"
            >
                <button
                    type="button"
                    class="primary-button w-full max-w-full py-3 max-sm:rounded-lg max-sm:py-2"
                    :disabled="! pending"
                    @click="confirm"
                >
                    @lang('shop::app.products.view.direct-order.use-this-location')
                </button>
            </x-slot>
        </x-shop::modal>
    </script>

    <script type="module">
        app.component('v-direct-order-map', {
            template: '#v-direct-order-map-template',

            emits: ['located'],

            data() {
                return {
                    map: null,

                    marker: null,

                    pending: null,

                    searchQuery: '',

                    searchError: '',

                    isSearching: false,

                    canUseGeolocation: 'geolocation' in navigator,

                    countryCode: '{{ strtolower(core()->getConfigData('sales.shipping.origin.country') ?: 'TZ') }}',
                }
            },

            beforeUnmount() {
                this.map?.remove();
            },

            methods: {
                open() {
                    this.$refs.mapModal.open();
                },

                /**
                 * The map is built the first time the popup opens, and resized once the popup is visible.
                 */
                onOpen() {
                    this.loadLeaflet().then(() => {
                        if (! this.map) {
                            this.initMap();
                        }

                        setTimeout(() => this.map.invalidateSize(), 350);
                    });
                },

                loadLeaflet() {
                    if (window.L) {
                        return Promise.resolve();
                    }

                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');

                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                        script.crossOrigin = '';
                        script.onload = resolve;
                        script.onerror = reject;

                        document.head.appendChild(script);
                    });
                },

                initMap() {
                    /**
                     * Default view: Dar es Salaam.
                     */
                    this.map = L.map(this.$refs.map).setView([-6.7924, 39.2083], 12);

                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }).addTo(this.map);

                    this.map.on('click', (event) => this.setLocation(event.latlng.lat, event.latlng.lng));
                },

                setLocation(latitude, longitude, zoom = null) {
                    if (! this.marker) {
                        this.marker = L.marker([latitude, longitude], { draggable: true }).addTo(this.map);

                        this.marker.on('dragend', () => {
                            const position = this.marker.getLatLng();

                            this.setLocation(position.lat, position.lng);
                        });
                    } else {
                        this.marker.setLatLng([latitude, longitude]);
                    }

                    if (zoom) {
                        this.map.setView([latitude, longitude], zoom);
                    }

                    this.pending = { latitude, longitude, region: '', address: '' };

                    this.lookUpPlace(latitude, longitude);
                },

                lookUpPlace(latitude, longitude) {
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&lat=${latitude}&lon=${longitude}`, {
                        headers: { 'Accept-Language': document.documentElement.lang || 'en' },
                    })
                        .then(response => response.json())
                        .then(place => {
                            if (this.pending?.latitude !== latitude || this.pending?.longitude !== longitude) {
                                return;
                            }

                            const address = place.address || {};

                            const region = address.state || address.city || address.county || address.region || '';

                            this.pending.region = region;

                            this.pending.address = [
                                address.road,
                                address.neighbourhood || address.suburb || address.village || address.quarter,
                                region,
                            ].filter(Boolean).join(', ');
                        })
                        .catch(() => {});
                },

                confirm() {
                    this.$emit('located', { ...this.pending });

                    this.$refs.mapModal.close();
                },

                search() {
                    if (! this.searchQuery.trim() || this.isSearching) {
                        return;
                    }

                    this.isSearching = true;

                    this.searchError = '';

                    fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=${this.countryCode}&q=${encodeURIComponent(this.searchQuery)}`, {
                        headers: { 'Accept-Language': document.documentElement.lang || 'en' },
                    })
                        .then(response => response.json())
                        .then(results => {
                            if (! results.length) {
                                this.searchError = @json(trans('shop::app.products.view.direct-order.not-found'));

                                return;
                            }

                            this.setLocation(parseFloat(results[0].lat), parseFloat(results[0].lon), 16);
                        })
                        .catch(() => {
                            this.searchError = @json(trans('shop::app.products.view.direct-order.not-found'));
                        })
                        .finally(() => {
                            this.isSearching = false;
                        });
                },

                useCurrentLocation() {
                    navigator.geolocation.getCurrentPosition(
                        (position) => this.setLocation(position.coords.latitude, position.coords.longitude, 17),
                        () => {},
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                },

                clear() {
                    this.marker?.remove();

                    this.marker = null;

                    this.pending = null;
                },
            },
        });
    </script>
@endPushOnce
