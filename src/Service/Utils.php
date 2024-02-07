<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

class Utils
{
    /**
     * Extends the return URL passed from the client with extra data, so we can identify
     * the PSP data later on in extractCheckoutIdFromPspData().
     */
    public static function extendReturnUrl(string $returnUrl): string
    {
        if (substr($returnUrl, -1) !== '/') {
            $returnUrl .= '/';
        }
        $returnUrl .= 'payunity';

        return $returnUrl;
    }

    /**
     * Returns true if this PSP connector is responsible for the passed PSP data string.
     */
    public static function isPayunityPspData(string $pspData): bool
    {
        $path = parse_url($pspData, PHP_URL_PATH);

        // we right-pad the extended URL with "/", so allow both
        return $path !== null && ($path === 'payunity' || $path === '/payunity');
    }

    /**
     * Extracts the payment ID from the PSP data, which looks something like:
     *  "payunity?resourcePath=/v1/checkouts/CHECKOUTID/payment"
     * Returns false if the PSP data format isn't known, or parsing failed.
     *
     * @return bool|string
     */
    public static function extractCheckoutIdFromPspData(string $pspData)
    {
        if (!self::isPayunityPspData($pspData)) {
            return false;
        }
        $query = parse_url($pspData, PHP_URL_QUERY);
        if ($query === null) {
            return false;
        }
        // see https://www.payunity.com/tutorials/server-to-server
        // around "shopperResultUrl". There is also an "id" directly,
        // but it isn't documented, so better not depend on it
        parse_str($query, $output);
        $resourcePath = $output['resourcePath'] ?? null;
        if ($resourcePath === null) {
            return false;
        }
        $parts = explode('/', $resourcePath);

        return $parts[count($parts) - 2] ?? false;
    }
}
