<?php

namespace App\Licensing\Enums;

/**
 * The result of a licensing check as seen by the application.
 */
enum LicenseStatus: string
{
    /** The installation holds a cryptographically valid, unexpired token. */
    case Valid = 'valid';

    /** No license key is configured yet. The app must be activated. */
    case NotActivated = 'not_activated';

    /** The license key / product slug does not match this installation. */
    case Invalid = 'invalid';

    /** The signed token could not be verified (signature / issuer / binding). */
    case InvalidToken = 'invalid_token';

    /** The license has been revoked or suspended on the server. */
    case Revoked = 'revoked';

    /** The subscription/perpetual window has ended. */
    case Expired = 'expired';

    /** No reachable network and no usable cached token. */
    case Offline = 'offline';

    /** Server rejected the key (bad key, wrong product, too many seats). */
    case ActivationFailed = 'activation_failed';

    /** A transport/config error occurred while reaching the server. */
    case Error = 'error';
}
