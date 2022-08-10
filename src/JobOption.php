<?php

declare(strict_types=1);

/*
 * This file is part of the Alight package.
 *
 * (c) June So <alight@juneszh.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alight;

class JobOption
{
    private int $index;

    /**
     * 
     * @param int $index 
     * @return $this 
     */
    public function __construct(int $index)
    {
        $this->index = $index;
        return $this;
    }

    /**
     * Execute the job minutely
     * 
     * @return JobOption 
     */
    public function minutely(): JobOption
    {
        return $this->setRule('*');
    }

    /**
     * Execute the job hourly
     * 
     * @param int $minute 
     * @return JobOption 
     */
    public function hourly(int $minute = 0): JobOption
    {
        return $this->setRule(self::numberPad($minute));
    }

    /**
     * Execute the job daily
     * 
     * @param int $hour 
     * @param int $minute 
     * @return JobOption 
     */
    public function daily(int $hour = 0, int $minute = 0): JobOption
    {
        return $this->setRule(self::numberPad($hour) . ':' . self::numberPad($minute));
    }

    /**
     * Execute the job weekly
     * 
     * @param int $dayOfWeek Sunday is 0 
     * @param int $hour 
     * @param int $minute 
     * @return JobOption 
     */
    public function weekly(int $dayOfWeek, int $hour = 0, int $minute = 0): JobOption
    {
        return $this->setRule($dayOfWeek . ' ' . self::numberPad($hour) . ':' . self::numberPad($minute));
    }

    /**
     * Execute the job monthly
     * 
     * @param int $dayOfMonth 
     * @param int $hour 
     * @param int $minute 
     * @return JobOption 
     */
    public function monthly(int $dayOfMonth, int $hour = 0, int $minute = 0): JobOption
    {
        return $this->setRule(self::numberPad($dayOfMonth) . ' ' . self::numberPad($hour) . ':' . self::numberPad($minute));
    }

    /**
     * Execute the job yearly
     * 
     * @param int $month 
     * @param int $dayOfMonth 
     * @param int $hour 
     * @param int $minute 
     * @return JobOption 
     */
    public function yearly(int $month, int $dayOfMonth, int $hour = 0, int $minute = 0): JobOption
    {
        return $this->setRule(self::numberPad($month) . '-' . self::numberPad($dayOfMonth) . ' ' . self::numberPad($hour) . ':' . self::numberPad($minute));
    }

    /**
     * Execute the job every {n} minutes
     * 
     * @param int $minutes 
     * @return JobOption 
     */
    public function everyMinutes(int $minutes): JobOption
    {
        return $this->setRule('*/' . $minutes);
    }

    /**
     * Execute the job every {n} hours
     * 
     * @param int $hours 
     * @param int $minute 
     * @return JobOption 
     */
    public function everyHours(int $hours, int $minute = 0): JobOption
    {
        return $this->setRule('*/' . self::numberPad($hours) . ':' . self::numberPad($minute));
    }

    /**
     * Execute the job once at the specified time
     * 
     * @param string $date 
     * @return JobOption 
     */
    public function date(string $date): JobOption
    {
        return $this->setRule(date('Y-m-d H:i', strtotime($date)));
    }

    /**
     * Set the job rule
     * 
     * @param string $rule 
     * @return JobOption 
     */
    private function setRule(string $rule): JobOption
    {
        Job::$config[$this->index]['rule'] = $rule;
        return $this;
    }

    /**
     * Pad a leading zero to the number
     * 
     * @param int $number 
     * @param int $length 
     * @return string 
     */
    private static function numberPad(int $number, int $length = 2): string
    {
        return str_pad((string)$number, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Set the maximum number of seconds to execute the job (Does not force quit until next same job starts)
     * 
     * @param int $seconds 
     * @return JobOption 
     */
    public function timeLimit(int $seconds): JobOption
    {
        Job::$config[$this->index][__FUNCTION__] = $seconds;
        return $this;
    }
}
