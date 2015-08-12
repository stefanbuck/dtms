<?php

namespace alroniks\dtms;

use DateTimeZone;

class DateTime extends \DateTime
{
    const ISO8601 = 'Y-m-d\TH:i:s.u\Z';

    public $microseconds;

    /**
     * Set microseconds data to class
     *
     * @param $microcesonds
     */
    public function setMicroseconds($microcesonds)
    {
        $this->microseconds = $microcesonds;
    }


    /**
     * Get microseconds data to class
     *
     * @param boolean $inSeconds If defined, microseconds will be converted to seconds with fractions
     * @return string
     */
    public function getMicroseconds($inSeconds = false)
    {
        if ($inSeconds) {
            return $this->microseconds * 1/1e6;
        }

        return intval($this->microseconds);
    }

    public function __construct($time = 'now', \DateTimeZone $timezone = null)
    {
        if ($time == 'now') {
            $micro = microtime();
            list($u, $s) = explode(' ', $micro);
            $time = \DateTime::createFromFormat('U.u', join('.', array($s, sprintf('%6f', $u) * 1e6)));
            $this->microseconds = $time->format('u');
        }

        return parent::__construct($time instanceof \DateTime ? $time->format(self::ISO8601) : $time, $timezone);
    }

    public function getTimestamp($inMicroseconds = false)
    {
        $timestamp = parent::getTimestamp();

        if ($inMicroseconds) {
            $timestamp += $this->getMicroseconds(true);
        }

        return $timestamp;
    }

    // setTimestamp // with ms
//    public function setTimestamp($seconds)
//    {
//        if (false !== ($res = filter_var($seconds, FILTER_VALIDATE_INT))) {
//            return $datetime->add(new DateInterval('PT'.$res.'S'));
//        }
//        $timestamp = explode('.', sprintf('%6f', $seconds));
//        $seconds   = (int) $timestamp[0];
//        $micro     = $timestamp[1] + $datetime->format('u');
//        if ($micro > 1e6) {
//            $micro -= 1e6;
//            $seconds++;
//        }
//        $dateEnd = $datetime->add(new DateInterval('PT'.$seconds.'S'));
//        return new DateTimeImmutable(
//            $dateEnd->format('Y-m-d H:i:s').".".sprintf('%06d', $micro),
//            $datetime->getTimeZone()
//        );
//    }

//    public function setTime($hour, $minute, $second = 0, $microsecond = 0)
//    {
//        $second += $microsecond / 1e6;
//
//        $this->setMicroseconds($microsecond);
//
//        return parent::setTime($hour, $minute, $second);
//    }

    public function add($interval)
    {
        if ($interval instanceof DateInterval) {
            $this->modifyMicroseconds(intval($interval->u), $interval->invert);
        }

        return parent::add($interval);
    }

    public function sub($interval)
    {
        if ($interval instanceof DateInterval) {
            $this->modifyMicroseconds(intval($interval->u), !$interval->invert);
        }

        return parent::sub($interval);
    }

    protected function modifyMicroseconds($microseconds, $invert)
    {
        if ($invert) {
            $microseconds *= -1;
        }

        $diff = $this->getMicroseconds() + $microseconds;

        // todo: refactoring - move it to separated method, because code duplicated
        if ($diff > 1e6) {
            $this->setMicroseconds($diff - 1e6);
            parent::modify("+1 seconds"); // +1 sec
        } else {
            if ($diff < 0) {
                parent::modify("-1 seconds"); // -1 sec
                $this->setMicroseconds(1e6 + $diff);
            } else {
                $this->setMicroseconds($diff);
            }

        }
    }

    // modify
    public function modify($modify)
    {
        // add support of microseconds

        return parent::modify($modify);
    }

    public function diff($datetime, $absolute = false)
    {
        $d1 = $this;
        $d2 = clone $datetime;

        $diff = new DateInterval('PT0S');
        foreach (get_object_vars(parent::diff($datetime)) as $property => $value) {
            $diff->{$property} = $value;
        }

        $sdiff = 0;
        $udiff = $d1->getMicroseconds() - $d2->getMicroseconds();
        if ($udiff > 1e6) {
            $udiff = $udiff - 1e6;
            $sdiff++;
        } else {
            if ($udiff < 0) {
                $udiff = 1e6 + $udiff;
                $sdiff--;
            }
        }

        $diff->u = $udiff;
        $diff->s += $sdiff;

        return $diff;
    }

    public static function createFromFormat($format, $time, $timezone = null)
    {
        if ($timezone === null) {
            $timezone = new DateTimeZone(date_default_timezone_get());
        }

        $datetime = \DateTime::createFromFormat($format, $time, $timezone);
        $microseconds = $datetime->format('u');

        $datetime = new self($datetime);
        $datetime->setMicroseconds($microseconds);

        return $datetime;
    }

    public function format($format)
    {
        $format = str_replace('u', sprintf('%06d', $this->microseconds), $format);

        return parent::format($format);
    }

    public function __toString()
    {
        return $this->format(static::ISO8601);
    }
}
