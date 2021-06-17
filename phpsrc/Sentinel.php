<?php

/**
 * A sentinel character which can be used to produce explicit leaves for all
 * suffixes. The sentinel just has to be appended to the list before handing
 * it to the suffix tree. For the sentinel equality and object identity are
 * the same!
 */
class Sentinel implements JavaObjectInterface
{
    /** The hash value used. */
    private $hash;

    public function __construct()
    {
        $this->hash = (int) rand(0, PHP_INT_MAX);
    }

    public function hashCode(): int
    {
        return $this->hash;
    }

    public function equals(object $obj): bool
    {
        // Original code uses physical object equality, not present in PHP.
        return $obj instanceof Sentinel;
    }

    public function toString(): string
    {
        return "$";
    }
}
