<?php

namespace App\Domain\Pricing;

use RuntimeException;

/** A booking choice that has no valid price (US-30). */
class PricingException extends RuntimeException {}
