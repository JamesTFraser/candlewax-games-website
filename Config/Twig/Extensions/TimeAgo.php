<?php

namespace CandlewaxGames\Config\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TimeAgo extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('ago', [$this, 'timeAgo'])];
    }

    /**
     * Takes a unix timestamp of a moment in the past and returns a string describing how long ago that moment
     * happened relative to the current time.
     *
     * @param string $pastTime The date-time formatted string to be converted.
     * @return string The formatted date string.
     */
    public function timeAgo(string $pastTime): string
    {
        // Get the number of seconds that have passed since the given time.
        $deltaTime = time() - strtotime($pastTime);

        // Time increments sorted by seconds descending.
        $timeIncrements = array(
            12 * 30 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second'
        );

        // Construct the string describing how long ago the given time happened.
        foreach ($timeIncrements as $seconds => $descriptor) {
            // Calculate the number of increments.
            $increment = $deltaTime / $seconds;

            // If the delta time amounts to less than one of the current increments, skip to the next increment.
            if ($increment < 1) {
                continue;
            }

            // Remove any decimals from the increment.
            $increment = floor($increment);

            // Return the description string.
            return $increment . ' ' . $descriptor . ($increment > 1 ? 's' : '') . ' ago';
        }

        // If delta time couldn't divide more than 1 into any increment, the given time must have happened now.
        return 'A moment ago';
    }
}
