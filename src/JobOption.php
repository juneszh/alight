<?php

declare(strict_types=1);

/*
 * This file is part of the Alight package.
 *
 * (c) June So <june@alight.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alight;

class JobOption
{
    /**
     *
     */
    public function __construct(private readonly int $index)
    {
    }

    /**
     * Execute the job minutely
     */
    public function minutely(): static
    {
        return $this->setRule('*');
    }

    /**
     * Execute the job hourly
     */
    public function hourly(int $minute = 0): static
    {
        return $this->setRule(Utility::numberPad($minute));
    }

    /**
     * Execute the job daily
     */
    public function daily(int $hour = 0, int $minute = 0): static
    {
        return $this->setRule(Utility::numberPad($hour) . ':' . Utility::numberPad($minute));
    }

    /**
     * Execute the job weekly
     *
     * @param int $dayOfWeek Sunday is 0
     */
    public function weekly(int $dayOfWeek, int $hour = 0, int $minute = 0): static
    {
        return $this->setRule($dayOfWeek . ' ' . Utility::numberPad($hour) . ':' . Utility::numberPad($minute));
    }

    /**
     * Execute the job monthly
     */
    public function monthly(int $dayOfMonth, int $hour = 0, int $minute = 0): static
    {
        return $this->setRule(Utility::numberPad($dayOfMonth) . ' ' . Utility::numberPad($hour) . ':' . Utility::numberPad($minute));
    }

    /**
     * Execute the job yearly
     */
    public function yearly(int $month, int $dayOfMonth, int $hour = 0, int $minute = 0): static
    {
        return $this->setRule(Utility::numberPad($month) . '-' . Utility::numberPad($dayOfMonth) . ' ' . Utility::numberPad($hour) . ':' . Utility::numberPad($minute));
    }

    /**
     * Execute the job every {n} minutes
     */
    public function everyMinutes(int $minutes): static
    {
        return $this->setRule('*/' . $minutes);
    }

    /**
     * Execute the job every {n} hours
     */
    public function everyHours(int $hours, int $minute = 0): static
    {
        return $this->setRule('*/' . Utility::numberPad($hours) . ':' . Utility::numberPad($minute));
    }

    /**
     * Execute the job once at the specified time
     */
    public function date(string $date): static
    {
        return $this->setRule(date('Y-m-d H:i', strtotime($date)));
    }

    /**
     * Set the job rule
     */
    private function setRule(string $rule): static
    {
        Job::$config[$this->index]['rule'] = $rule;
        return $this;
    }

    /**
     * Set the maximum number of seconds to execute the job (Does not force quit until next same job starts)
     *
     * @param int $seconds The default is 3600, 0 for run persistently
     */
    public function timeLimit(int $seconds): static
    {
        Job::$config[$this->index][__FUNCTION__] = $seconds;
        return $this;
    }
}
