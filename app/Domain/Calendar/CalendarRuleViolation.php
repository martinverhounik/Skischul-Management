<?php

namespace App\Domain\Calendar;

use RuntimeException;

/** A date choice that breaks the season or closed-day rules (US-30). */
class CalendarRuleViolation extends RuntimeException {}
