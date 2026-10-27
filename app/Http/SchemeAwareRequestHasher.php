<?php

namespace App\Http;

use Illuminate\Http\Request;
use Webkul\FPC\Hasher\DefaultHasher;

class SchemeAwareRequestHasher extends DefaultHasher
{
    /**
     * Include the scheme in the cache key so pages cached over https (whose asset
     * URLs use https) are never served to http visitors, and vice versa.
     */
    public function getHashFor(Request $request): string
    {
        return $request->getScheme().'-'.parent::getHashFor($request);
    }
}
